<?php
// Bootstrap Laravel
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$html = file_get_contents('/tmp/gift-card-widget.html');
$setting = \App\Models\Setting::where('name', 'theme_xboard')->first();
if (!$setting) {
    echo "ERROR: setting not found\n";
    exit(1);
}
$val = json_decode($setting->value, true);
$val['custom_html'] = $html;
$setting->value = json_encode($val);
$setting->save();
echo "DONE - custom_html length: " . strlen($html) . "\n";
