<?php

namespace XboardFerrari\Providers;

use App\Services\Cloudflare\CloudflareDnsService;
use App\Support\RegionHelper;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
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
        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // Octane 环境下在 WorkerStarting 事件注册 Hook（因为 HookManager::reset() 在每个 Worker 启动时调用）
        if (!$this->app->runningInConsole() && $this->app->bound('octane')) {
            $this->app['events']->listen(WorkerStarting::class, function () {
                $this->registerHooks();
            });
        } else {
            // CLI 环境（artisan 命令）直接注册
            $this->registerHooks();
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

            foreach ($nodes as $node) {
                // 获取该订阅对应的 Worker 域名作为 SNI/host
                $baseDomain = $workerDomains[$node->subscription_id] ?? 'eogop1.ferrari.cc.cd';

                // 从节点名解析域名（如 SG_新加坡_02 → sg02.baseDomain）
                $edgetunnelHost = $this->assignDomainFromNodeName(
                    $node->name,
                    $node->host,
                    $baseDomain,
                    $regionHelper,
                    $dnsService,
                );

                // 追加 Edgetunnel 节点到订阅输出
                $servers[] = [
                    'id' => $node->id,
                    'name' => $node->name,
                    'type' => $node->type,
                    'host' => $edgetunnelHost,
                    'port' => 443,
                    'server_port' => 443,
                    // 使用用户 UUID 作为认证凭证（Worker KV 验证用）
                    'password' => $user->uuid,
                    'uuid' => $user->uuid,
                    'path' => $node->path,
                    // SNI 和 Host header 使用 Worker 域名（不是子域名）
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
                        'network' => 'ws',
                        'network_settings' => [
                            'path' => $node->path,
                            'headers' => [
                                'Host' => $baseDomain,
                            ],
                        ],
                        'flow' => null,
                    ],
                    'group_ids' => [],
                    'route_ids' => [],
                    'show' => $node->show,
                    'sort' => $node->sort,
                    // 标记为 edgetunnel 节点，便于 Xboard 核心识别
                    'tags' => ['edgetunnel'],
                ];
            }

            return $servers;
        }, 10);
    }

    /**
     * 从节点名解析地区代码和编号，分配域名
     *
     * 示例:
     *   SG_新加坡_02 + baseDomain → sg02.baseDomain
     *   JP_日本_01 + baseDomain → jp01.baseDomain
     *   无法解析 → 从 CF DNS IP→域名映射查找，找不到则返回原始 IP
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

        // 无法从节点名称解析，回退到 DNS IP→域名映射
        $ipToDomain = $dnsService->buildIpDomainMapping();
        return $ipToDomain[$ip] ?? $ip;
    }

    /**
     * 解析每个订阅对应的 CF Worker 域名
     *
     * 通过 EdgetunnelSubscription.dns_cf_worker_id 查找关联的 CfWorker.domain。
     * 如果没有关联 Worker，使用默认域名。
     *
     * @return array<int, string>  subscription_id => domain
     */
    private function resolveWorkerDomains($nodes): array
    {
        $subscriptionIds = $nodes->pluck('subscription_id')->unique()->values();
        $defaultDomain = 'eogop1.ferrari.cc.cd';
        $domains = [];

        foreach ($subscriptionIds as $subId) {
            $sub = \XboardFerrari\Models\EdgetunnelSubscription::find($subId);
            if (!$sub || !$sub->dns_cf_worker_id) {
                $domains[$subId] = $defaultDomain;
                continue;
            }

            $worker = \App\Models\CfWorker::find($sub->dns_cf_worker_id);
            $domains[$subId] = $worker ? $worker->domain : $defaultDomain;
        }

        return $domains;
    }
}
