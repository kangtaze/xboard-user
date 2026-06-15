<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$workers = DB::table('v2_cf_worker')->select('id', 'name', 'domain', 'status', 'created_at')->get();

echo "Existing CF Workers:\n";
echo "==================\n";
foreach ($workers as $w) {
    echo "ID: {$w->id} | Name: {$w->name} | Domain: {$w->domain} | Status: " . ($w->status == 1 ? 'Deployed' : ($w->status == 0 ? 'Pending' : 'Error')) . " | Created: {$w->created_at}\n";
}
echo "\nTotal: " . $workers->count() . " workers\n";