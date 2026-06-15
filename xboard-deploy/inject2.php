<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$html = file_get_contents('/tmp/gift-card-widget.html');
$setting = \App\Models\Setting::where('name', 'theme_xboard')->first();
if (!$setting) { echo "NOT FOUND\n"; exit(1); }

$val = $setting->value;
if (is_string($val)) $val = json_decode($val, true);
$val['custom_html'] = $html;
$setting->value = $val;
$setting->save();
echo "DONE - injected " . strlen($html) . " bytes\n";
