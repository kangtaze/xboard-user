<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$row = \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->first();
$val = json_decode($row->value, true);

$html = $val['custom_html'];
echo "custom_html length: " . strlen($html) . "\n";

// Find headers line
preg_match('/var headers = \{[^}]+\};/', $html, $m);
if ($m) {
    echo "headers line: " . $m[0] . "\n";
    if (strpos($m[0], 'Authorization') !== false) {
        echo "ERROR: Authorization still present!\n";
        echo "Trying to fix again...\n";
        $html = str_replace(
            "'Content-Type':'application/json','Authorization': token",
            "'Content-Type':'application/json'",
            $html
        );
        $val['custom_html'] = $html;
        \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->update(['value' => json_encode($val)]);
        echo "DONE - Fixed again\n";
    } else {
        echo "OK: Authorization not present\n";
    }
} else {
    echo "ERROR: headers line not found\n";
}