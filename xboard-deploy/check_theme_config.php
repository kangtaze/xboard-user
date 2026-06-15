<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$row = \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->first();
$val = json_decode($row->value, true);

echo "theme_xboard value keys: " . implode(', ', array_keys($val)) . "\n";
echo "\n=== custom_html ===\n";
echo isset($val['custom_html']) ? (substr($val['custom_html'], 0, 200) . "...") : "NOT SET\n";
echo "\n=== All keys ===\n";
foreach ($val as $k => $v) {
    echo "$k: " . (is_string($v) ? substr($v, 0, 50) . "..." : gettype($v)) . "\n";
}