<?php

namespace XboardFerrari\Providers;

use App\Services\Cloudflare\CloudflareDnsService;
use App\Support\RegionHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use XboardFerrari\Services\EdgetunnelService;
use App\Services\Plugin\HookManager;
use Laravel\Octane\Events\WorkerStarting;

/**
 * Edgetunnel 订阅输出 Hook 提供者
 *
 * 在 Xboard 的 `client.subscribe.servers` 过滤器中注入 Edgetunnel 节点。
 *
 * 数据流：
 *   用户订阅 → Xboard ClientController → HookManager::filter()
 *   → 本 Provider 的回调 → 查询可用节点 → 分配域名 → 追加到 $servers 数组
 *
 * 域名分配逻辑：
 *   1. 从节点名提取地区+编号（如 SG_新加坡_02 → sg02）
 *   2. 拼接 Worker 域名（如 sg02.jpuuc.cc.cd）
 *   3. 无法提取时回退到 DNS IP→域名映射
 */
class EdgetunnelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EdgetunnelService::class);
    }

    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->registerHooks();

        if (!$this->app->runningInConsole() && $this->app->bound('octane')) {
            $this->app['events']->listen(WorkerStarting::class, function () {
                $this->registerHooks();
            });
        }
    }

    /**
     * 注册订阅输出 Hook
     */
    private function registerHooks(): void
    {
        HookManager::registerFilter('client.subscribe.servers', function ($servers, $user, $request) {
            $service = app(EdgetunnelService::class);
            $nodes = $service->getAvailableNodes($user);

            if ($nodes->isEmpty()) {
                return $servers;
            }

            $workerDomains = $this->resolveWorkerDomains($nodes);

            // === NEW: 预加载 subscription 对象（含 direct_ip_mode 等字段）===
            $subsById = \XboardFerrari\Models\EdgetunnelSubscription::whereIn('id',
                $nodes->pluck('subscription_id')->unique()->values()
            )->get()->keyBy('id');

            $regionHelper = app(RegionHelper::class);
            $dnsService = app(CloudflareDnsService::class);

            $subscriptionConfigs = $this->detectSubscriptionConfigs($nodes);

            foreach ($nodes as $node) {
                // === NEW: 获取当前节点对应的 subscription ===
                $sub = $subsById[$node->subscription_id] ?? null;

                $baseDomain = $workerDomains[$node->subscription_id] ?? 'vp92.cc.cd';

                $config = $subscriptionConfigs[$node->subscription_id] ?? [
                    'network' => 'ws',
                    'mode' => null,
                    'panel_uuid' => $user->uuid,
                ];
                $network = $config['network'];
                $uuid = $user->uuid;

                // 根据传输协议选择连接方式
                if ($network === 'xhttp') {
                    $server = $node->host;
                    $port = (int) $node->port;
                    $path = '/';
                } else {
                    // WS: 使用子域名路由 + Worker 端口 + 区域路径
                    // === CHANGED: direct_ip_mode=1 时用节点解析端口，否则保持 443 ===
                    $port = (!empty($sub->direct_ip_mode)) ? (int) $node->port : 443;

                    $server = $this->assignDomainFromNodeName(
                        $node->name,
                        $node->host,
                        $baseDomain,
                        $regionHelper,
                        $dnsService,
                    );
                    $path = $this->buildRegionPath($node->path, $node->name, $regionHelper);
                }

                $networkSettings = $this->buildNetworkSettings($network, $path, $baseDomain, $config['mode']);

                $servers[] = [
                    'id' => $node->id,
                    'name' => $this->addFlagToNodeName($node->name, $regionHelper),
                    'type' => $node->type,
                    'host' => $server,
                    'port' => $port,
                    'server_port' => $port,
                    'password' => $uuid,
                    'uuid' => $uuid,
                    'path' => $path,
                    'sni' => $baseDomain,
                    'host_header' => $baseDomain,
                    'fingerprint' => $node->fingerprint,
                    'security' => $node->security,
                    'protocol_settings' => [
                        'tls' => $node->security === 'tls' ? 1 : 0,
                        'tls_settings' => [
                            'server_name' => $baseDomain,
                            'allow_insecure' => false,
                        ],
                        'network' => $network,
                        'network_settings' => $networkSettings,
                        'flow' => null,
                    ],
                    'group_ids' => [],
                    'route_ids' => [],
                    'show' => $node->show,
                    'sort' => $node->sort,
                    'tags' => ['edgetunnel'],
                ];
            }

            return $servers;
        }, 10);
    }

    private function detectSubscriptionConfigs($nodes): array
    {
        $configs = [];
        $uniqueSubIds = $nodes->pluck('subscription_id')->unique()->values();

        foreach ($uniqueSubIds as $subId) {
            $cacheKey = 'edgetunnel_cfg_' . $subId;
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $configs[$subId] = $cached;
                continue;
            }

            $sub = \XboardFerrari\Models\EdgetunnelSubscription::find($subId);
            if (!$sub || !$sub->url) {
                $configs[$subId] = ['network' => 'ws', 'mode' => null, 'panel_uuid' => ''];
                continue;
            }

            $result = $this->parseUpstreamSubscription($sub->url);
            $configs[$subId] = $result;
            Cache::put($cacheKey, $result, 300);
        }

        return $configs;
    }

    private function parseUpstreamSubscription(string $url): array
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                return ['network' => 'ws', 'mode' => null, 'panel_uuid' => ''];
            }

            $decoded = base64_decode($response);
            if ($decoded === false) {
                return ['network' => 'ws', 'mode' => null, 'panel_uuid' => ''];
            }

            $lines = array_filter(explode("\n", trim($decoded)));
            if (empty($lines)) {
                return ['network' => 'ws', 'mode' => null, 'panel_uuid' => ''];
            }

            $firstLine = $lines[0];
            $panelUuid = '';
            if (preg_match('#vless://([^@]+)@#', $firstLine, $m)) {
                $panelUuid = $m[1];
            }

            $query = parse_url($firstLine, PHP_URL_QUERY);
            if (!$query) {
                return ['network' => 'ws', 'mode' => null, 'panel_uuid' => $panelUuid];
            }

            parse_str($query, $params);

            $network = $params['type'] ?? 'ws';
            if (!in_array($network, ['ws', 'xhttp', 'grpc', 'h2', 'tcp'])) {
                $network = 'ws';
            }

            return [
                'network' => $network,
                'mode' => $params['mode'] ?? null,
                'panel_uuid' => $panelUuid,
            ];
        } catch (\Throwable $e) {
            return ['network' => 'ws', 'mode' => null, 'panel_uuid' => ''];
        }
    }

    private function buildNetworkSettings(string $network, string $path, string $baseDomain, ?string $mode): array
    {
        if ($network === 'xhttp') {
            return [
                'path' => $path,
                'mode' => $mode ?? 'stream-one',
                'host' => $baseDomain,
            ];
        }

        return [
            'path' => $path,
            'headers' => [
                'Host' => $baseDomain,
            ],
        ];
    }

    private function assignDomainFromNodeName(
        string $name,
        string $ip,
        string $baseDomain,
        RegionHelper $regionHelper,
        CloudflareDnsService $dnsService,
    ): string {
        $parsed = $regionHelper->extractRegionAndNumber($name);
        if ($parsed) {
            $prefix = $regionHelper->buildDnsPrefix($parsed['region'], $parsed['number']);
            return "{$prefix}.{$baseDomain}";
        }

        $ipToDomain = $dnsService->buildIpDomainMapping();
        return $ipToDomain[$ip] ?? $ip;
    }

    private function buildRegionPath(?string $originalPath, string $nodeName, RegionHelper $regionHelper): string
    {
        $path = $originalPath ?: '/';
        $parsed = $regionHelper->extractRegionAndNumber($nodeName);
        if ($parsed) {
            $prefix = $regionHelper->buildDnsPrefix($parsed['region'], $parsed['number']);
            return '/' . $prefix . ($path !== '/' ? $path : '');
        }
        return $path;
    }

    private function resolveWorkerDomains($nodes): array
    {
        $subscriptionIds = $nodes->pluck('subscription_id')->unique()->values();
        $defaultDomain = 'vp92.cc.cd';
        $domains = [];

        foreach ($subscriptionIds as $subId) {
            $sub = \XboardFerrari\Models\EdgetunnelSubscription::find($subId);
            if (!$sub) {
                $domains[$subId] = $defaultDomain;
                continue;
            }

            if ($sub->dns_cf_worker_id) {
                $worker = \App\Models\CfWorker::find($sub->dns_cf_worker_id);
                $domains[$subId] = $worker ? $worker->domain : $defaultDomain;
                continue;
            }

            $urlHost = parse_url($sub->url, PHP_URL_HOST);
            $domains[$subId] = $urlHost ?: $defaultDomain;
        }

        return $domains;
    }

    private function countryCodeToFlag(string $code): string
    {
        $code = strtoupper($code);
        if (strlen($code) !== 2 || !ctype_alpha($code)) {
            return '';
        }
        return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    private function addFlagToNodeName(string $name, RegionHelper $regionHelper): string
    {
        $name = preg_replace('/^\[.\]/u', '', $name);
        $name = trim($name);
        $name = preg_replace('/^[\x{1F1E6}-\x{1F1FF}]{2}\s*/u', '', $name);

        $parsed = $regionHelper->extractRegionAndNumber($name);
        if ($parsed) {
            $flag = $this->countryCodeToFlag($parsed['region']);
            if ($flag) {
                return $flag . ' ' . $name;
            }
        }
        return $name;
    }
}
