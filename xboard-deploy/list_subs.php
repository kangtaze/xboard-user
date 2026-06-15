<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 当前订阅列表 ===\n\n";

$subs = \XboardFerrari\Models\EdgetunnelSubscription::orderBy('id', 'desc')->get();
echo "订阅数量: " . $subs->count() . "\n\n";

if ($subs->count() > 0) {
    foreach ($subs as $sub) {
        echo "订阅 #{$sub->id}: {$sub->name}\n";
        echo "  URL: " . substr($sub->url, 0, 50) . "...\n";
        echo "  最后同步: " . ($sub->last_sync_at ?? '从未同步') . "\n";
        echo "  启用: " . ($sub->enabled ? '是' : '否') . "\n";
        echo "  节点数量: " . $sub->nodes()->count() . "\n";
        echo "  套餐组: " . json_encode($sub->group_ids ?? []) . "\n";
        echo "\n";
    }
} else {
    echo "没有订阅\n";
}