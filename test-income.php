<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$start = strtotime('today');

echo "=== Today's completed orders ===\n";
$rows = DB::table('v2_order')
    ->where('created_at', '>=', $start)
    ->where('created_at', '<', time())
    ->whereNotIn('status', [0, 2])
    ->get(['id', 'total_amount', 'balance_amount', 'created_at']);
foreach ($rows as $r) {
    echo "id={$r->id} amount={$r->total_amount} balance=" . var_export($r->balance_amount, true) . " created={$r->created_at}\n";
}
echo "count=" . count($rows) . "\n";

echo "\n=== SUM tests ===\n";
$r1 = DB::table('v2_order')
    ->where('created_at', '>=', $start)
    ->where('created_at', '<', time())
    ->whereNotIn('status', [0, 2])
    ->selectRaw('COALESCE(SUM(total_amount + balance_amount), 0) as total')
    ->value('total');
echo "SUM(total_amount + balance_amount) = $r1\n";

$r2 = DB::table('v2_order')
    ->where('created_at', '>=', $start)
    ->where('created_at', '<', time())
    ->whereNotIn('status', [0, 2])
    ->selectRaw('COALESCE(SUM(total_amount), 0) as total')
    ->value('total');
echo "SUM(total_amount) only = $r2\n";

$r3 = DB::table('v2_order')
    ->where('created_at', '>=', $start)
    ->where('created_at', '<', time())
    ->whereNotIn('status', [0, 2])
    ->selectRaw('COALESCE(SUM(COALESCE(total_amount,0) + COALESCE(balance_amount,0)), 0) as total')
    ->value('total');
echo "SUM(COALESCE(total,0) + COALESCE(balance,0)) = $r3\n";
