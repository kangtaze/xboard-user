<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$row = \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->first();
$val = json_decode($row->value, true);

$old = "'Content-Type':'application/json','Authorization': token";
$new = "'Content-Type':'application/json'";

if (strpos($val['custom_html'], $old) !== false) {
    $val['custom_html'] = str_replace($old, $new, $val['custom_html']);
    \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->update(['value' => json_encode($val)]);
    echo "DONE - Fixed in DB\n";
} elseif (strpos($val['custom_html'], $new) !== false) {
    echo "ALREADY FIXED\n";
} else {
    echo "PATTERN NOT FOUND in custom_html\n";
    echo "custom_html length: " . strlen($val['custom_html']) . "\n";
}
