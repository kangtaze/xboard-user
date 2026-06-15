<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Read the raw DB value
$row = \Illuminate\Support\Facades\DB::table('v2_settings')->where('name', 'theme_xboard')->first();
$val = json_decode($row->value, true);
$html = $val['custom_html'];

// Check for Authorization
echo "custom_html length: " . strlen($html) . "\n";
echo "Has 'Authorization': " . (strpos($html, 'Authorization') !== false ? 'YES - BUG!' : 'NO - FIXED') . "\n";
echo "Has 'Content-Type':'application/json','Authorization': " . (strpos($html, "'Content-Type':'application/json','Authorization': token") !== false ? 'YES - BUG!' : 'NO - FIXED') . "\n";

// Also check what the rendered page shows
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:7001/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$page = curl_exec($ch);
curl_close($ch);

echo "\nRendered page 'Authorization': " . (strpos($page, 'Authorization') !== false ? 'YES - BUG!' : 'NO - FIXED') . "\n";

// Find the headers line in the rendered page
preg_match('/var headers = \{[^}]+\};/', $page, $m);
if ($m) echo "Rendered headers: " . $m[0] . "\n";