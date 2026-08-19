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
        // 加载自定义管理 API 路由

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // Octane 环境下在 WorkerStarting 事件注册 Hook（因为 HookManager::reset() 在每个 Worker 启动时调用）
        // 同时直接在 boot() 注册，确保覆盖所有场景
        $this->registerHooks();

        if (!$this->app->runningInConsole() && $this->app->bound('octane')) {
            $this->app['events']->listen(WorkerStarting::class, function () {
                $this->registerHooks();
            });
        }
    }

    /**
     * 注册订阅输出 Hook
     *
     * Hook 优先级 10，在 Xboard 核心 Hook 之后运行。
     * 回调接收 ($servers, $user, $request)，返回追加 Edgetunnel 节点后的 $servers 数组。
     */
    private function registerHooks(): void
    {
        HookManager::registerFilter('client.subscribe.servers', function ($servers, $user, $request) {
            $service = app(EdgetunnelService::class);
            $nodes = $service->getAvailableNodes($user);

            if ($nodes->isEmpty()) {
                return $servers;
            }

            // 预加载每个订阅对应的 Worker 域名
            $workerDomains = $this->resolveWorkerDomains($nodes);

            // 共享服务（用于域名分配和 IP 回退映射）
            $regionHelper = app(RegionHelper::class);
            $dnsService = app(CloudflareDnsService::class);

            // 从上游订阅自动检测传输协议和面板 UUID（带 5 分钟缓存）
            $subscriptionConfigs = $this->detectSubscriptionConfigs($nodes);

            foreach ($nodes as $node) {
                // 获取该订阅对应的 Worker 域名作为 SNI/host
                $baseDomain = $workerDomains[$node->subscription_id] ?? 'vp92.cc.cd';

                // 获取该订阅的传输协议类型和面板 UUID
                $config = $subscriptionConfigs[$node->subscription_id] ?? [
                    'network' => 'ws',
                    'mode' => null,
                    'panel_uuid' => $user->uuid,
                ];
                $network = $config['network'];

                // XHTTP 使用面板 UUID（Worker XHTTP 不走 KV 用户验证）
                // WS 使用用户 UUID（Worker WS 走 KV 验证）
                $uuid = $user->uuid;

                // 根据传输协议选择连接方式
                if ($network === 'xhttp') {
                    // XHTTP: 使用节点原始 IP + 端口 + path=/（匹配上游订阅格式）
                    $server = $node->host;
                    $port = (int) $node->port;
                    $path = '/';
                } else {
                    // WS: 使用子域名路由 + 443 端口 + 区域路径
                    $server = $this->assignDomainFromNodeName(
                        $node->name,
                        $node->host,
                        $baseDomain,
                        $regionHelper,
                        $dnsService,
                    );
                    $port = 443;
                    $path = $this->buildRegionPath($node->path, $node->name, $regionHelper);
                }

                // 根据传输协议构建 network_settings
                $networkSettings = $this->buildNetworkSettings($network, $path, $baseDomain, $config['mode']);

                // 追加 Edgetunnel 节点到订阅输出
                $servers[] = [
                    'id' => $node->id,
                    'name' => $node->name,
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

    /**
     * 从上游订阅自动检测每个订阅的传输协议和面板 UUID
     *
     * 缓存 5 分钟，避免每次订阅请求都 fetch 上游。
     *
     * @return array<int, array{network: string, mode: string|null, panel_uuid: string}>  subscription_id => config
     */
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

    /**
     * Fetch 上游订阅 URL，解析第一个节点的传输协议和 UUID
     *
     * @return array{network: string, mode: string|null, panel_uuid: string}
     */
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

            // 提取 UUID: vless://UUID@host:port?...
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

    /**
     * 根据传输协议类型构建 network_settings
     */
    private function buildNetworkSettings(string $network, string $path, string $baseDomain, ?string $mode): array
    {
        if ($network === 'xhttp') {
            return [
                'path' => $path,
                'mode' => $mode ?? 'stream-one',
                'host' => $baseDomain,
            ];
        }

        // 默认: ws
        return [
            'path' => $path,
            'headers' => [
                'Host' => $baseDomain,
            ],
        ];
    }

    /**
     * 从节点名解析地区代码和编号，分配域名
     */
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

    /**
     * 在 WebSocket path 中编码区域前缀
     */
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

    /**
     * 解析每个订阅对应的 CF Worker 域名
     *
     * @return array<int, string>  subscription_id => domain
     */
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
}
