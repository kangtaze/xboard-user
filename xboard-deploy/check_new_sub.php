<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 检查新订阅状态 ===\n\n";

// 获取所有订阅
$subs = \XboardFerrari\Models\EdgetunnelSubscription::orderBy('id', 'desc')->get();
echo "订阅数量: " . $subs->count() . "\n\n";

foreach ($subs->take(3) as $sub) {
    echo "订阅 #{$sub->id}: {$sub->name}\n";
    echo "  URL: " . substr($sub->url, 0, 60) . "...\n";
    echo "  最后同步: " . ($sub->last_sync_at ?? '从未同步') . "\n";
    echo "  节点数量: " . $sub->nodes()->count() . "\n\n";
}

if ($subs->count() > 0) {
    $latestSub = $subs->first();
    echo "最新订阅 #{$latestSub->id} 的节点（前5个）:\n";
    foreach ($latestSub->nodes->take(5) as $node) {
        echo "  {$node->name} -> {$node->host}\n";
    }
}
