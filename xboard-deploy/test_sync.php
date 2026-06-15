<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sub = \XboardFerrari\Models\EdgetunnelSubscription::find(33);
if (!$sub) {
    echo "订阅 #32 不存在\n";
    exit;
}

echo "测试同步订阅 #32: {$sub->name}\n";
echo "URL: {$sub->url}\n\n";

try {
    $service = app(\XboardFerrari\Services\EdgetunnelService::class);
    $result = $service->syncSubscription($sub);
    echo "同步成功!\n";
    echo "节点数量: " . $result['node_count'] . "\n";
} catch (\Exception $e) {
    echo "同步失败!\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}
