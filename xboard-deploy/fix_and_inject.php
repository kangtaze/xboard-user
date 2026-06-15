<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Read widget and fix Authorization line
$html = file_get_contents('/tmp/gift-card-widget.html');
$html = str_replace(
    "'Content-Type':'application/json','Authorization': token",
    "'Content-Type':'application/json'",
    $html
);

// Save fixed version
file_put_contents('/tmp/gift-card-widget-fixed.html', $html);

// Inject into DB
$row = \Illuminate\Support\Facades\DB::table('v2_settings')
    ->where('name', 'theme_xboard')
    ->first();

$val = json_decode($row->value, true);
$val['custom_html'] = $html;

\Illuminate\Support\Facades\DB::table('v2_settings')
    ->where('name', 'theme_xboard')
    ->update(['value' => json_encode($val)]);

echo "DONE - injected fixed widget (" . strlen($html) . " bytes)\n";

// Show the fixed line
echo "Fixed headers line:\n";
preg_match('/var headers = \{[^}]+\};/', $html, $m);
echo $m[0] ?? "NOT FOUND\n";