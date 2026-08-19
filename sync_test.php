<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();
$sub = XboardFerrari\Models\EdgetunnelSubscription::find(4);
$svc = app(XboardFerrari\Services\EdgetunnelService::class);
$r = $svc->syncSubscription($sub);
echo json_encode($r);
