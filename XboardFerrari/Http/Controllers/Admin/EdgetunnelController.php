<?php

namespace XboardFerrari\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use XboardFerrari\Services\EdgetunnelService;
use XboardFerrari\Models\EdgetunnelSubscription;
use XboardFerrari\Models\EdgetunnelNode;

class EdgetunnelController
{
    private EdgetunnelService $service;

    public function __construct(EdgetunnelService $service)
    {
        $this->service = $service;
    }

    public function getSubscriptions(): JsonResponse
    {
        $subs = EdgetunnelSubscription::orderBy('sort')->orderBy('id')
            ->withCount('nodes')
            ->get();

        $subs->transform(function ($sub) {
            return [
                'id' => $sub->id,
                'name' => $sub->name,
                'url' => $sub->url,
                'group_ids' => $sub->group_ids,
                'enabled' => $sub->enabled,
                'show' => $sub->show,
                'sort' => $sub->sort,
                'last_sync_at' => $sub->last_sync_at,
                'node_count' => $sub->nodes_count,
                'dns_cf_worker_id' => $sub->dns_cf_worker_id,
                'dns_zone_name' => $sub->dnsWorker ? $sub->dnsWorker->domain : null,
            ];
        });

        return response()->json(['data' => $subs]);
    }

    public function saveSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:512',
            'group_ids' => 'required|array',
            'group_ids.*' => 'nullable|integer|string|in:all',
            'dns_cf_worker_id' => 'nullable|integer',
        ]);

        $updateData = [
            'name' => $request->input('name'),
            'url' => $request->input('url'),
            'group_ids' => $request->input('group_ids'),
            'enabled' => $request->input('enabled', true),
            'show' => $request->input('show', true),
            'sort' => $request->input('sort', 0),
        ];

        // 只在前端明确传递时才更新 dns_cf_worker_id，避免编辑时被清空
        if ($request->has('dns_cf_worker_id')) {
            $updateData['dns_cf_worker_id'] = $request->input('dns_cf_worker_id');
        }

        $sub = EdgetunnelSubscription::updateOrCreate(
            ['id' => $request->input('id')],
            $updateData
        );

        // 自动关联 CfWorker：按订阅 URL 域名匹配已部署的 Worker
        if (empty($sub->dns_cf_worker_id)) {
            $urlHost = parse_url($sub->url, PHP_URL_HOST);
            if ($urlHost) {
                $worker = \App\Models\CfWorker::where('domain', $urlHost)
                    ->where('status', 1)
                    ->first();
                if ($worker) {
                    $sub->update(['dns_cf_worker_id' => $worker->id]);
                }
            }
        }

        return response()->json(['data' => $sub->id]);
    }

    public function dropSubscription(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $sub = EdgetunnelSubscription::findOrFail($request->input('id'));

        // 删除该订阅所有节点对应的 CF DNS 记录
        $this->service->deleteSubscriptionDnsRecords($sub->id);

        EdgetunnelNode::where('subscription_id', $sub->id)->delete();
        $sub->delete();

        return response()->json(['data' => true]);
    }

    public function syncSubscription(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        try {
            $sub = EdgetunnelSubscription::findOrFail($request->input('id'));
            $result = $this->service->syncSubscription($sub);
            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getNodes(Request $request): JsonResponse
    {
        $subscriptionId = $request->input('subscription_id');

        $query = EdgetunnelNode::query();
        if ($subscriptionId) {
            $query->where('subscription_id', $subscriptionId);
        }

        $nodes = $query->with('subscription')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($nodes as $node) {
            $subId = $node->subscription_id;
            if (!isset($grouped[$subId])) {
                $grouped[$subId] = [
                    'id' => $node->subscription->id,
                    'name' => $node->subscription->name,
                    'nodes' => [],
                ];
            }
            $grouped[$subId]['nodes'][] = [
                'id' => $node->id,
                'name' => $node->name,
                'host' => $node->host,
                'port' => $node->port,
                'type' => $node->type,
                'enabled' => $node->enabled,
                'show' => $node->show,
                'sort' => $node->sort,
                'latency' => $node->latency,
                'online' => $node->online,
                'last_check_at' => $node->last_check_at,
            ];
        }

        return response()->json(['data' => array_values($grouped)]);
    }

    public function updateNode(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'enabled' => 'nullable|boolean',
            'show' => 'nullable|boolean',
            'sort' => 'nullable|integer',
        ]);

        $node = EdgetunnelNode::findOrFail($request->input('id'));

        if ($request->has('enabled')) $node->enabled = $request->input('enabled');
        if ($request->has('show')) $node->show = $request->input('show');
        if ($request->has('sort')) $node->sort = $request->input('sort');

        $node->save();

        return response()->json(['data' => true]);
    }

    public function testLatency(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $node = EdgetunnelNode::findOrFail($request->input('id'));
        $latency = $this->service->testLatency($node);

        return response()->json(['data' => $latency]);
    }

    public function batchTestLatency(Request $request): JsonResponse
    {
        $request->validate([
            'node_ids' => 'required|array',
            'node_ids.*' => 'integer',
        ]);

        $results = $this->service->batchTestLatency($request->input('node_ids'));

        return response()->json(['data' => $results]);
    }

    // Server Management
    public function getServers(): JsonResponse
    {
        $servers = DB::table('v2_server')
            ->where('type', 'vless')
            ->orderBy('id')
            ->get();

        $servers->transform(function ($s) {
            $groupIds = json_decode($s->group_ids ?? '[]', true);
            return [
                'id' => $s->id,
                'name' => $s->name,
                'host' => $s->host,
                'port' => $s->port,
                'group_ids' => $groupIds,
                'created_at' => $s->created_at,
            ];
        });

        return response()->json(['data' => $servers]);
    }

    public function saveServer(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer',
            'group_ids' => 'required|array',
            'group_ids.*' => 'integer',
        ]);

        $name = $request->input('name');
        $host = $request->input('host');
        $port = $request->input('port');
        $groupIds = json_encode($request->input('group_ids'));

        // Create or get machine
        $machineId = DB::table('v2_server_machine')
            ->where('name', $name)
            ->value('id');

        if (!$machineId) {
            $machineId = DB::table('v2_server_machine')->insertGetId([
                'name' => $name,
                'token' => 'edgetunnel-' . strtolower(str_replace(' ', '-', $name)),
                'is_active' => 1,
            ]);
        }

        // Create server
        $serverId = DB::table('v2_server')->insertGetId([
            'type' => 'vless',
            'code' => '',
            'parent_id' => 0,
            'group_ids' => $groupIds,
            'route_ids' => '[]',
            'name' => $name,
            'rate' => 1,
            'tags' => json_encode(['edgetunnel']),
            'host' => $host,
            'port' => (string) $port,
            'server_port' => (int) $port,
            'protocol_settings' => json_encode([
                'tls' => 1,
                'tls_settings' => [
                    'server_name' => $host,
                    'allow_insecure' => false,
                ],
                'network' => 'ws',
                'network_settings' => [
                    'path' => '/',
                    'headers' => ['Host' => $host],
                ],
                'flow' => '',
            ]),
            'show' => 0,
            'sort' => 0,
            'enabled' => 1,
            'transfer_enable' => 1,
            'u' => 0,
            'd' => 0,
            'machine_id' => $machineId,
        ]);

        return response()->json(['data' => ['id' => $serverId]]);
    }

    public function updateServer(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'group_ids' => 'required|array',
            'group_ids.*' => 'integer',
        ]);

        $id = $request->input('id');
        $groupIds = json_encode($request->input('group_ids'));

        $affected = DB::table('v2_server')->where('id', $id)->update([
            'group_ids' => $groupIds,
        ]);

        if ($affected === 0) {
            return response()->json(['data' => null, 'error' => 'Server 不存在'], 404);
        }

        return response()->json(['data' => true]);
    }

    public function dropServer(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $id = $request->input('id');
        $server = DB::table('v2_server')->find($id);

        if ($server) {
            $machineId = $server->machine_id;
            DB::table('v2_server')->where('id', $id)->delete();
            // Check if machine has other servers
            $hasOtherServers = DB::table('v2_server')->where('machine_id', $machineId)->exists();
            if (!$hasOtherServers) {
                DB::table('v2_server_machine')->where('id', $machineId)->delete();
            }
        }

        return response()->json(['data' => true]);
    }

    // User Management
    public function getUsers(Request $request): JsonResponse
    {
        $users = DB::table('v2_user')
            ->where('plan_id', '!=', 0)  // 只显示有订阅的用户
            ->select(['id', 'email', 'group_id', 'plan_id', 'expired_at', 'u', 'd', 'transfer_enable'])
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function batchUpdateUserGroup(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer',
            'group_id' => 'required|integer',
        ]);

        $userIds = $request->input('user_ids');
        $newGroupId = $request->input('group_id');

        $affected = DB::table('v2_user')
            ->whereIn('id', $userIds)
            ->update(['group_id' => $newGroupId]);

        return response()->json(['data' => ['affected' => $affected]]);
    }

    public function previewInjection(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|integer',
        ]);

        $sub = EdgetunnelSubscription::findOrFail($request->input('subscription_id'));
        $nodes = EdgetunnelNode::where('subscription_id', $sub->id)
            ->where('enabled', true)
            ->orderBy('sort')
            ->get();

        if ($nodes->isEmpty()) {
            return response()->json(['data' => ['nodes' => [], 'worker_domain' => null, 'total' => 0]]);
        }

        // 解析 Worker 域名（与 EdgetunnelServiceProvider 逻辑一致）
        $workerDomain = 'ferrari.ccwu.cc';
        if ($sub->dns_cf_worker_id) {
            $worker = \App\Models\CfWorker::find($sub->dns_cf_worker_id);
            if ($worker) {
                $workerDomain = $worker->domain;
            }
        }

        $regionHelper = app(\App\Support\RegionHelper::class);

        $previewNodes = [];
        foreach ($nodes as $node) {
            $parsed = $regionHelper->extractRegionAndNumber($node->name);
            $edgetunnelHost = $parsed
                ? $regionHelper->buildDnsPrefix($parsed['region'], $parsed['number']) . '.' . $workerDomain
                : $node->host;

            $previewNodes[] = [
                'name' => $node->name,
                'original_host' => $node->host . ':' . $node->port,
                'injected_host' => $edgetunnelHost . ':' . (!empty($sub->direct_ip_mode) ? (int) $node->port : 443),
                'sni' => $workerDomain,
                'path' => $node->path ?: '/',
                'type' => $node->type,
                'enabled' => $node->enabled,
                'show' => $node->show,
            ];
        }

        return response()->json(['data' => [
            'subscription' => ['id' => $sub->id, 'name' => $sub->name],
            'worker_domain' => $workerDomain,
            'worker_id' => $sub->dns_cf_worker_id,
            'total' => count($previewNodes),
            'nodes' => $previewNodes,
        ]]);
    }
}
