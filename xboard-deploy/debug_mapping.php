<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 1. Check cache file
$cachePath = storage_path('cf-dns-ip-mapping.json');
echo "=== Cache File ===\n";
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    echo "Exists: YES\n";
    echo "Age: " . (time() - $cached['ts']) . " seconds\n";
    echo "Mapping count: " . count($cached['mapping'] ?? []) . "\n";
    // Show first 5 entries
    $i = 0;
    foreach ($cached['mapping'] ?? [] as $ip => $domain) {
        echo "  $ip => $domain\n";
        if (++$i >= 5) break;
    }
} else {
    echo "Exists: NO\n";
}

// 2. Check EdgetunnelNode host values
echo "\n=== EdgetunnelNode hosts (first 10) ===\n";
$nodes = \XboardFerrari\Models\EdgetunnelNode::select('id','name','host')->limit(10)->get();
foreach ($nodes as $n) {
    echo $n->id." | ".$n->name." | host=".$n->host.PHP_EOL;
}

// 3. Test the mapping logic
echo "\n=== Test mapping ===\n";
$cfAccountId = config('edgetunnel.cf_account_id');
$cfApiToken = config('edgetunnel.cf_api_token');
echo "CF_ACCOUNT_ID: " . ($cfAccountId ? substr($cfAccountId, 0, 8) . '...' : 'EMPTY') . "\n";
echo "CF_API_TOKEN: " . ($cfApiToken ? substr($cfApiToken, 0, 12) . '...' : 'EMPTY') . "\n";

// Try to call CF API
try {
    $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://api.cloudflare.com/client/v4/zones", [
        'name' => 'ferrari.cc.cd',
    ], [
        'Authorization' => "Bearer {$cfApiToken}",
        'Content-Type' => 'application/json',
    ]);
    echo "CF API status: " . $response->status() . "\n";
    echo "CF API success: " . ($response->successful() ? 'YES' : 'NO') . "\n";
    if ($response->successful()) {
        $zones = $response->json('result');
        echo "Zones found: " . count($zones) . "\n";
        if (!empty($zones)) {
            echo "Zone ID: " . $zones[0]['id'] . "\n";
        }
    } else {
        echo "CF API error: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "CF API exception: " . $e->getMessage() . "\n";
}
