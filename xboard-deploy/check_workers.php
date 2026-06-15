<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$workers = DB::table('v2_cf_worker')->get();
echo 'Total: ' . $workers->count() . PHP_EOL;
foreach ($workers as $w) {
    echo '---' . PHP_EOL;
    echo 'ID: ' . $w->id . PHP_EOL;
    echo 'Name: ' . $w->name . PHP_EOL;
    echo 'Domain: ' . $w->domain . PHP_EOL;
    echo 'Worker: ' . $w->worker_name . PHP_EOL;
    echo 'Zone: ' . $w->cf_zone_id . PHP_EOL;
    echo 'NodeID: ' . $w->xboard_node_id . PHP_EOL;
    echo 'Status: ' . $w->status . PHP_EOL;
    echo 'KV: ' . $w->kv_namespace_id . PHP_EOL;
    echo 'Error: ' . $w->error_msg . PHP_EOL;
    echo 'Deployed: ' . $w->deployed_at . PHP_EOL;
}
