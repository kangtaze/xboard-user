<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/v2/admin/stat/getOverride', 'GET');
$response = $kernel->handle($request);
echo $response->getContent();
