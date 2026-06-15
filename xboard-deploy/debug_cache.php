<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Clear ALL caches
\Illuminate\Support\Facades\Artisan::call('config:clear');
\Illuminate\Support\Facades\Artisan::call('cache:clear');
\Illuminate\Support\Facades\Artisan::call('view:clear');

echo "Cleared config, cache, view\n";

// Check raw DB value
$row = \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->first();
echo "DB value length: " . strlen($row->value) . "\n";
echo "Has Authorization in DB: " . (strpos($row->value, 'Authorization') !== false ? 'YES' : 'NO') . "\n";

// Check via admin_setting
$customHtml = admin_setting('custom_html');
echo "admin_setting custom_html length: " . strlen($customHtml) . "\n";
echo "Has Authorization in admin_setting: " . (strpos($customHtml, 'Authorization') !== false ? 'YES' : 'NO') . "\n";
