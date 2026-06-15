<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$setting = \App\Models\Setting::where('name', 'theme_xboard')->first();
$raw = $setting->getRawOriginal('value');
$decoded = json_decode($raw, true);
echo "custom_html length: " . strlen($decoded['custom_html'] ?? '') . "\n";
echo "first 100 chars: " . substr($decoded['custom_html'] ?? 'NULL', 0, 100) . "\n";
