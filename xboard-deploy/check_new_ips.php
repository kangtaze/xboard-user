<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 检查 Edgetunnel 订阅节点 ===\n\n";

// 获取订阅
$sub = \XboardFerrari\Models\EdgetunnelSubscription::first();
if (!$sub) {
    echo "没有找到订阅\n";
    exit;
}

echo "订阅URL: {$sub->url}\n";
echo "最后同步: {$sub->last_sync_at}\n\n";

// 获取当前数据库中的节点
$dbNodes = \XboardFerrari\Models\EdgetunnelNode::where('subscription_id', $sub->id)->get();
echo "数据库中的节点数量: " . $dbNodes->count() . "\n";

// 获取订阅URL的实际内容
$service = app(\XboardFerrari\Services\EdgetunnelService::class);
$content = file_get_contents($sub->url);
$decoded = base64_decode(trim($content));

$nodes = $service->parseSubscriptionContent($decoded);
echo "订阅URL中的节点数量: " . count($nodes) . "\n\n";

// 显示前10个节点
echo "订阅URL中的节点（前10个）:\n";
foreach (array_slice($nodes, 0, 10) as $node) {
    $region = preg_match('/^([A-Z]{2})[_\s]/', $node['name'], $matches) ? strtolower($matches[1]) : 'unknown';
    echo "  {$node['name']} | host={$node['host']} | region={$region}\n";
}

// 检查哪些IP是新IP
$dbIps = $dbNodes->pluck('host')->unique()->values()->all();
$newIps = array_diff(array_column($nodes, 'host'), $dbIps);

echo "\n新IP数量: " . count($newIps) . "\n";
if (!empty($newIps)) {
    echo "新IP列表:\n";
    foreach ($newIps as $ip) {
        echo "  $ip\n";
    }
} else {
    echo "没有新IP，所以没有创建DNS记录\n";
}

// 检查 CF DNS 记录
$zoneId = '482ac3c8466b3855eb78b62afa5fdb20';
$cfApiToken = config('edgetunnel.cf_api_token');
$dnsResponse = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Bearer {$cfApiToken}",
])->timeout(10)->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
    'type' => 'A',
    'per_page' => 100,
]);

if ($dnsResponse->successful()) {
    $records = $dnsResponse->json('result');
    echo "\nCF DNS A记录数量: " . count($records) . "\n";

    // 统计各地区记录
    $regionCount = [];
    foreach ($records as $record) {
        if (preg_match('/^([a-z]{2})(\d+)\.eogop1\.ferrari\.cc\.cd$/', $record['name'], $matches)) {
            $region = $matches[1];
            $regionCount[$region] = ($regionCount[$region] ?? 0) + 1;
        }
    }

    echo "各地区记录数:\n";
    foreach ($regionCount as $region => $count) {
        echo "  $region: $count\n";
    }
}