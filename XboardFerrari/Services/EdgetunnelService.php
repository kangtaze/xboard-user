<?php

namespace XboardFerrari\Services;

use App\Services\Cloudflare\CloudflareDnsService;
use XboardFerrari\Models\EdgetunnelSubscription;
use XboardFerrari\Models\EdgetunnelNode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Edgetunnel 订阅管理核心服务
 *
 * 职责：
 * - 抓取上游订阅 URL，解析 vless/vmess/trojan 节点
 * - 持久化节点到数据库
 * - 触发 DNS 记录同步（委托给 CloudflareDnsService）
 * - 按用户组筛选可用节点
 * - TCP 延迟测试
 *
 * DNS 管理已提取到 CloudflareDnsService，KV 同步已提取到 EdgetunnelUserSyncService。
 */
class EdgetunnelService
{
    public function __construct(
        private CloudflareDnsService $dnsService,
    ) {}

    /**
     * 同步上游订阅
     *
     * 抓取订阅 URL → 解析节点 → 创建/更新 DB 记录 → 同步 DNS → 清理旧数据
     *
     * @param  EdgetunnelSubscription $subscription
     * @return array{success: bool, node_count: int}
     * @throws \Exception
     */
    public function syncSubscription(EdgetunnelSubscription $subscription): array
    {
        try {
            $response = Http::timeout(10)->get($subscription->url);
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch subscription URL: ' . $response->status());
            }

            $content = $response->body();
            // 尝试 base64 解码，失败则使用原始内容
            $decoded = base64_decode(trim($content));
            if (!$decoded) {
                $decoded = $content;
            }

            $nodes = $this->parseSubscriptionContent($decoded);

            // 当前订阅中的唯一 IP 列表
            $currentIps = collect($nodes)->pluck('host')->unique()->values()->all();

            // DNS 记录同步（委托给 CloudflareDnsService）
            $this->dnsService->syncRecordsForNodes($subscription, $nodes, $currentIps);

            // 持久化节点到数据库
            $nodeCount = 0;
            foreach ($nodes as $node) {
                EdgetunnelNode::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'host' => $node['host'],
                        'port' => $node['port'],
                    ],
                    [
                        'name' => $node['name'],
                        'uuid' => $node['uuid'],
                        'path' => $node['path'] ?? null,
                        'sni' => $node['sni'] ?? null,
                        'host_header' => $node['host_header'] ?? null,
                        'fingerprint' => $node['fingerprint'] ?? null,
                        'security' => $node['security'] ?? 'tls',
                        'type' => $node['type'],
                        'transport' => $node['transport'] ?? 'ws',
                        'enabled' => true,
                        'show' => true,
                        'latency' => -1,
                        'online' => false,
                        'sort' => $nodeCount,
                    ]
                );
                $nodeCount++;
            }

            // 清理数据库中不再存在的节点
            $this->cleanOldNodes($subscription->id, $currentIps);

            // 清除 DNS 缓存，让新记录立即生效
            $this->dnsService->clearCache();

            $subscription->last_sync_at = now();
            $subscription->save();

            return ['success' => true, 'node_count' => $nodeCount];
        } catch (\Exception $e) {
            Log::error('Edgetunnel sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除订阅的所有 DNS 记录
     *
     * @param  int $subscriptionId
     */
    public function deleteSubscriptionDnsRecords(int $subscriptionId): void
    {
        $this->dnsService->deleteRecordsForSubscription($subscriptionId);
    }

    // ============ 订阅内容解析 ============

    /**
     * 解析订阅内容（base64 编码的节点列表）
     *
     * @param  string $content  解码后的文本内容
     * @return array<int, array{type: string, name: string, uuid: string, host: string, port: int, ...}>
     */
    private function parseSubscriptionContent(string $content): array
    {
        $lines = explode("\n", trim($content));
        $nodes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (str_starts_with($line, 'vless://')) {
                $nodes[] = $this->parseVless($line);
            } elseif (str_starts_with($line, 'vmess://')) {
                $nodes[] = $this->parseVmess($line);
            } elseif (str_starts_with($line, 'trojan://')) {
                $nodes[] = $this->parseTrojan($line);
            }
        }

        return $nodes;
    }

    /**
     * 解析 vless:// URI
     */
    private function parseVless(string $uri): array
    {
        $parsed = parse_url($uri);
        $fragment = $parsed['fragment'] ?? 'Unknown';
        parse_str($parsed['query'] ?? '', $params);

        return [
            'type' => 'vless',
            'name' => urldecode($fragment),
            'uuid' => $parsed['user'],
            'host' => $parsed['host'],
            'port' => $parsed['port'] ?? 443,
            'security' => $params['security'] ?? 'tls',
            'sni' => $params['sni'] ?? $params['serverName'] ?? null,
            'host_header' => $params['host'] ?? $params['headerHost'] ?? null,
            'fingerprint' => $params['fp'] ?? null,
            'path' => $params['path'] ?? null,
            'transport' => $params['type'] ?? 'ws',
        ];
    }

    /**
     * 解析 vmess:// URI（base64 编码的 JSON）
     */
    private function parseVmess(string $uri): array
    {
        $base64 = substr($uri, 8);
        $json = base64_decode($base64);
        $data = json_decode($json, true);

        return [
            'type' => 'vmess',
            'name' => $data['ps'] ?? 'Unknown',
            'uuid' => $data['id'],
            'host' => $data['add'],
            'port' => $data['port'],
            'security' => $data['tls'] === 'tls' ? 'tls' : 'none',
            'sni' => $data['sni'] ?? null,
            'host_header' => $data['host'] ?? null,
            'path' => $data['net'] === 'ws' ? ($data['path'] ?? '/') : null,
        ];
    }

    /**
     * 解析 trojan:// URI
     */
    private function parseTrojan(string $uri): array
    {
        $parsed = parse_url($uri);
        $fragment = $parsed['fragment'] ?? 'Unknown';
        parse_str($parsed['query'] ?? '', $params);

        return [
            'type' => 'trojan',
            'name' => urldecode($fragment),
            'uuid' => $parsed['user'],
            'host' => $parsed['host'],
            'port' => $parsed['port'] ?? 443,
            'security' => 'tls',
            'sni' => $params['sni'] ?? null,
            'host_header' => $params['peer'] ?? null,
            'path' => $params['path'] ?? null,
        ];
    }

    // ============ 节点可用性查询 ============

    /**
     * 获取用户可用的 Edgetunnel 节点
     *
     * 根据用户的 group_id 筛选订阅和节点。
     * 支持 "all" 模式（所有订阅对用户可见）。
     *
     * @param  User $user
     * @return Collection<EdgetunnelNode>
     */
    public function getAvailableNodes(User $user): Collection
    {
        if (empty($user->group_id)) {
            return collect();
        }

        $subs = EdgetunnelSubscription::where('enabled', true)
            ->where('show', true)
            ->get();

        // 辅助函数：确保 group_ids 是数组（防御双重编码）
        $ensureArray = function ($ids): array {
            if (is_array($ids)) {
                return $ids;
            }
            if (is_string($ids)) {
                return json_decode($ids, true) ?: [];
            }
            return [];
        };

        // "全部套餐组"模式
        $hasAllGroups = $subs->contains(function ($sub) use ($ensureArray) {
            return in_array('all', $ensureArray($sub->group_ids));
        });

        if ($hasAllGroups) {
            $subGroupIds = $subs->pluck('id');
        } else {
            $subGroupIds = $subs->filter(function ($sub) use ($user, $ensureArray) {
                return in_array((string) $user->group_id, array_map('strval', $ensureArray($sub->group_ids)));
            })->pluck('id');
        }

        if ($subGroupIds->isEmpty()) {
            return collect();
        }

        return EdgetunnelNode::whereHas('subscription')
            ->whereIn('subscription_id', $subGroupIds)
            ->where('enabled', true)
            ->where('show', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    // ============ 延迟测试 ============

    /**
     * 测试单个节点的 TCP 连接延迟
     *
     * @param  EdgetunnelNode $node
     * @return int  延迟毫秒数，-1 表示不可达
     */
    public function testLatency(EdgetunnelNode $node): int
    {
        $start = microtime(true);
        $latency = -1;

        // 解析该节点订阅的 Worker 域名，测试域名:443（CF Workers 实际端口）
        $testHost = $this->resolveLatencyTestHost($node);
        $testPort = 443;

        try {
            $fp = @fsockopen($testHost, $testPort, $errno, $errstr, 5);
            if ($fp) {
                $latency = round((microtime(true) - $start) * 1000);
                fclose($fp);
            }
        } catch (\Exception $e) {
            $latency = -1;
        }

        $node->latency = $latency;
        $node->online = $latency > 0 && $latency < 5000;
        $node->last_check_at = now();
        $node->save();

        return $latency;
    }

    /**
     * 解析延迟测试目标域名
     *
     * Edgetunnel 节点的 host/port 来自上游订阅（CF anycast IP + 代理端口），
     * 不可直接 TCP 连通。实际连接通过 Worker 子域名:443（如 jp01.eogop1.ferrari.cc.cd）。
     *
     * 策略：从节点名生成子域名，拼接订阅的 Worker 域名。
     * 无法解析时回退到订阅的 DNS zone 域名或原始 host。
     */
    private function resolveLatencyTestHost(EdgetunnelNode $node): string
    {
        $sub = EdgetunnelSubscription::find($node->subscription_id);
        if (!$sub) {
            return $node->host;
        }

        // 获取订阅的 Worker 域名（base domain）
        $baseDomain = 'ferrari.ccwu.cc'; // 默认 VIP1
        if ($sub->dns_cf_worker_id) {
            $worker = \App\Models\CfWorker::find($sub->dns_cf_worker_id);
            if ($worker) {
                $baseDomain = $worker->domain;
            }
        } elseif ($sub->dns_zone_domain) {
            $baseDomain = 'eogop1.' . $sub->dns_zone_domain; // 旧格式回退
                // 新格式：baseDomain 直接是 dns_zone_domain
        }

        // 从节点名生成子域名前缀（如 JP_日本_01 → jp01）
        $regionHelper = app(\App\Support\RegionHelper::class);
        $parsed = $regionHelper->extractRegionAndNumber($node->name);
        if ($parsed) {
            $prefix = $regionHelper->buildDnsPrefix($parsed['region'], $parsed['number']);
            return "{$prefix}.{$baseDomain}";
        }

        // 无法解析节点名，回退到 base domain
        return $baseDomain;
    }

    /**
     * 批量测试节点延迟
     *
     * @param  array<int, int> $nodeIds  节点 ID 列表
     * @return array<int, int>  nodeId => latency
     */
    public function batchTestLatency(array $nodeIds): array
    {
        $results = [];
        foreach ($nodeIds as $nodeId) {
            $node = EdgetunnelNode::find($nodeId);
            if ($node) {
                $results[$nodeId] = $this->testLatency($node);
            }
        }
        return $results;
    }

    // ============ 私有方法 ============

    /**
     * 清理数据库中不再属于当前订阅的节点
     */
    private function cleanOldNodes(int $subscriptionId, array $currentIps): void
    {
        $dbNodes = EdgetunnelNode::where('subscription_id', $subscriptionId)->get();

        foreach ($dbNodes as $node) {
            if (!in_array($node->host, $currentIps)) {
                Log::info("删除过期节点: {$node->name} ({$node->host})");
                $node->delete();
            }
        }
    }
}
