<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 检查删除订阅后的状态 ===\n\n";

// 检查订阅是否还存在
$sub = \XboardFerrari\Models\EdgetunnelSubscription::find(1);
if ($sub) {
    echo "订阅 #1 仍然存在\n";
} else {
    echo "订阅 #1 已删除\n";
}

// 检查节点是否还存在
$nodes = \XboardFerrari\Models\EdgetunnelNode::where('subscription_id', 1)->get();
echo "订阅 #1 的节点数量: " . $nodes->count() . "\n";

if ($nodes->count() > 0) {
    echo "节点列表（前5个）:\n";
    foreach ($nodes->take(5) as $node) {
        echo "  {$node->name} -> {$node->host}\n";
    }
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

    // 显示所有记录
    echo "\n所有 DNS A 记录:\n";
    foreach ($records as $record) {
        if (preg_match('/eogop1\.ferrari\.cc\.cd$/', $record['name'])) {
            echo "  {$record['name']} -> {$record['content']}\n";
        }
    }
}