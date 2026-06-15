<?php

namespace App\Services;

use App\Models\CfWorker;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CfDeployService
{
    private $baseUrl = "https://api.cloudflare.com/client/v4";

    public function deploy(CfWorker $worker): bool
    {
        try {
            // 1. Create KV Namespace
            $kvId = $this->createKvNamespace($worker);
            $worker->update(["kv_namespace_id" => $kvId]);

            // 2. Create Xboard node to get node_id
            $nodeId = $this->createXboardNode($worker);
            $worker->update(["xboard_node_id" => $nodeId]);

            // 3. Upload Worker script
            $this->uploadWorkerScript($worker);

            // 4. Set Worker settings (env vars + KV binding)
            $this->setWorkerSettings($worker);

            // 5. Add domain route
            $this->addWorkerRoute($worker);

            // 6. Sync user-list to new KV
            $this->syncUserList($worker);

            $worker->update([
                "status" => CfWorker::STATUS_DEPLOYED,
                "deployed_at" => now(),
                "error_msg" => null
            ]);
            return true;
        } catch (\Exception $e) {
            $worker->update([
                "status" => CfWorker::STATUS_ERROR,
                "error_msg" => $e->getMessage()
            ]);
            return false;
        }
    }

    public function redeploy(CfWorker $worker): bool
    {
        try {
            $this->uploadWorkerScript($worker);
            $this->setWorkerSettings($worker);
            $worker->update([
                "status" => CfWorker::STATUS_DEPLOYED,
                "deployed_at" => now(),
                "error_msg" => null
            ]);
            return true;
        } catch (\Exception $e) {
            $worker->update([
                "status" => CfWorker::STATUS_ERROR,
                "error_msg" => $e->getMessage()
            ]);
            return false;
        }
    }

    public function destroy(CfWorker $worker, bool $cleanCf = true): void
    {
        if ($cleanCf && $worker->status === CfWorker::STATUS_DEPLOYED) {
            try {
                $this->deleteWorkerRoute($worker);
                $this->cfApi($worker, "DELETE", "/accounts/{$worker->cf_account_id}/workers/scripts/{$worker->worker_name}");
                if ($worker->kv_namespace_id) {
                    $this->cfApi($worker, "DELETE", "/accounts/{$worker->cf_account_id}/storage/kv/namespaces/{$worker->kv_namespace_id}");
                }
            } catch (\Exception $e) {
                // ignore cleanup errors
            }
        }
        if ($worker->xboard_node_id) {
            Server::where("id", $worker->xboard_node_id)->delete();
        }
        $worker->delete();
    }

    private function createKvNamespace(CfWorker $worker): string
    {
        $resp = $this->cfApi($worker, "POST", "/accounts/{$worker->cf_account_id}/storage/kv/namespaces", [
            "title" => "edgetunnel-{$worker->worker_name}"
        ]);
        return $resp["result"]["id"];
    }

    private function uploadWorkerScript(CfWorker $worker): void
    {
        $scriptContent = $this->getWorkerTemplate();

        $metadata = json_encode([
            "main_module" => "worker.js",
            "compatibility_date" => "2025-11-04",
            "compatibility_flags" => ["nodejs_compat"]
        ]);

        $boundary = "----FormBoundary" . Str::random(16);
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"metadata\"; filename=\"metadata.json\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $metadata . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"worker.js\"; filename=\"worker.js\"\r\n";
        $body .= "Content-Type: application/javascript+module\r\n\r\n";
        $body .= $scriptContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = Http::withHeaders([
            "Authorization" => "Bearer {$worker->cf_api_token}",
            "Content-Type" => "multipart/form-data; boundary={$boundary}"
        ])->withBody($body, "multipart/form-data; boundary={$boundary}")
          ->put("{$this->baseUrl}/accounts/{$worker->cf_account_id}/workers/scripts/{$worker->worker_name}");

        if (!$response->successful() || !$response->json("success")) {
            throw new \Exception("Upload worker failed: " . $response->body());
        }
    }

    private function setWorkerSettings(CfWorker $worker): void
    {
        $adminUuid = "a3ee222b-9c34-4081-a777-7dd71a312ee4";
        $xboardToken = admin_setting("server_token", "607007a595036b8e75ab27b069cc8584");
        $xboardOrigin = admin_setting("app_url", "https://ferrari.cc.cd");

        $settings = [
            "bindings" => [
                ["type" => "kv_namespace", "name" => "KV", "namespace_id" => $worker->kv_namespace_id],
                ["type" => "plain_text", "name" => "UUID", "text" => $adminUuid],
                ["type" => "plain_text", "name" => "ADMIN", "text" => "a5052337"],
                ["type" => "plain_text", "name" => "XBOARD_NODE_ID", "text" => (string)$worker->xboard_node_id],
                ["type" => "plain_text", "name" => "XBOARD_TOKEN", "text" => $xboardToken],
                ["type" => "plain_text", "name" => "XBOARD_ORIGIN", "text" => $xboardOrigin],
            ]
        ];

        $this->cfApi($worker, "PATCH", "/accounts/{$worker->cf_account_id}/workers/scripts/{$worker->worker_name}/settings", $settings);
    }

    private function addWorkerRoute(CfWorker $worker): void
    {
        $this->cfApi($worker, "POST", "/zones/{$worker->cf_zone_id}/workers/routes", [
            "pattern" => "{$worker->domain}/*",
            "script" => $worker->worker_name
        ]);
    }

    private function deleteWorkerRoute(CfWorker $worker): void
    {
        $routes = $this->cfApi($worker, "GET", "/zones/{$worker->cf_zone_id}/workers/routes");
        if (isset($routes["result"])) {
            foreach ($routes["result"] as $route) {
                if (isset($route["script"]) && $route["script"] === $worker->worker_name) {
                    $this->cfApi($worker, "DELETE", "/zones/{$worker->cf_zone_id}/workers/routes/{$route["id"]}");
                }
            }
        }
    }

    private function createXboardNode(CfWorker $worker): int
    {
        $node = new Server();
        $node->name = "CF-" . $worker->name;
        $node->type = "vless";
        $node->host = $worker->domain;
        $node->port = 443;
        $node->server_port = 443;
        $node->tls = 1;
        $node->network = "ws";
        $node->protocol_settings = json_encode([
            "flow" => "",
            "network" => "ws",
            "network_settings" => [
                "path" => "/",
                "headers" => ["Host" => $worker->domain]
            ],
            "tls_settings" => [
                "server_name" => $worker->domain,
                "allow_insecure" => false
            ]
        ]);
        $node->group_ids = json_encode([]);
        $node->route_ids = json_encode([]);
        $node->tags = json_encode(["CF-Worker"]);
        $node->rate = 1;
        $node->show = 1;
        $node->sort = 0;
        $node->save();
        return $node->id;
    }

    private function syncUserList(CfWorker $worker): void
    {
        $existingWorker = CfWorker::where("status", CfWorker::STATUS_DEPLOYED)
            ->where("id", "!=", $worker->id)
            ->whereNotNull("kv_namespace_id")
            ->first();

        if ($existingWorker) {
            try {
                $resp = Http::withHeaders([
                    "Authorization" => "Bearer {$existingWorker->cf_api_token}",
                ])->get("{$this->baseUrl}/accounts/{$existingWorker->cf_account_id}/storage/kv/namespaces/{$existingWorker->kv_namespace_id}/values/user-list");

                if ($resp->successful() && $resp->body()) {
                    Http::withHeaders([
                        "Authorization" => "Bearer {$worker->cf_api_token}",
                        "Content-Type" => "application/octet-stream"
                    ])->put(
                        "{$this->baseUrl}/accounts/{$worker->cf_account_id}/storage/kv/namespaces/{$worker->kv_namespace_id}/values/user-list",
                        $resp->body()
                    );
                }
            } catch (\Exception $e) {
                // Non-critical, user-list will sync via cron
            }
        }
    }

    private function getWorkerTemplate(): string
    {
        $path = storage_path("app/worker-template.js");
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        throw new \Exception("Worker template not found. Please upload via Worker管理页面.");
    }

    private function cfApi(CfWorker $worker, string $method, string $path, array $data = null)
    {
        $request = Http::withHeaders([
            "Authorization" => "Bearer {$worker->cf_api_token}",
            "Content-Type" => "application/json"
        ]);

        $url = $this->baseUrl . $path;

        $response = match(strtoupper($method)) {
            "GET" => $request->get($url),
            "POST" => $request->post($url, $data ?? []),
            "PUT" => $request->put($url, $data ?? []),
            "PATCH" => $request->patch($url, $data ?? []),
            "DELETE" => $request->delete($url),
        };

        if (!$response->successful()) {
            $errors = $response->json("errors") ?? [];
            $msg = !empty($errors) ? ($errors[0]["message"] ?? "Unknown CF API error") : $response->body();
            throw new \Exception("CF API {$method} {$path}: {$msg}");
        }

        return $response->json() ?? $response->body();
    }
}
