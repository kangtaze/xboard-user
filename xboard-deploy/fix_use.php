<?php
$file = '/www/app/Http/Routes/V2/AdminRoute.php';
$content = file_get_contents($file);
$content = str_replace(
    'use AppHttpControllersV2AdminCfWorkerController;',
    'use App\\Http\\Controllers\\V2\\Admin\\CfWorkerController;',
    $content
);
file_put_contents($file, $content);
echo "FIXED\n";
