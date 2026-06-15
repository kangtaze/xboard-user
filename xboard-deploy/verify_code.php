<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 检查 EdgetunnelController 中 dropSubscription 方法
$reflection = new \ReflectionMethod('XboardFerrari\Http\Controllers\Admin\EdgetunnelController', 'dropSubscription');
$contents = file_get_contents('/www/app/XboardFerrari/Http/Controllers/Admin/EdgetunnelController.php');

// 查找 dropSubscription 相关代码
$start = strpos($contents, 'function dropSubscription');
if ($start !== false) {
    echo "dropSubscription found at position {$start}\n";
    echo substr($contents, $start, 500);
} else {
    echo "dropSubscription NOT FOUND in file\n";
}

echo "\n\n--- Check EdgetunnelService for deleteSubscriptionDnsRecords ---\n";
$serviceContents = file_get_contents('/www/app/XboardFerrari/Services/EdgetunnelService.php');
$start2 = strpos($serviceContents, 'deleteSubscriptionDnsRecords');
if ($start2 !== false) {
    echo "deleteSubscriptionDnsRecords found at position {$start2}\n";
    echo substr($serviceContents, $start2, 300);
} else {
    echo "deleteSubscriptionDnsRecords NOT FOUND\n";
}
