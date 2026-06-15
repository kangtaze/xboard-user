<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cfApiToken = config('edgetunnel.cf_api_token');
$zoneName = 'ferrari.cc.cd';
$mapping = [];

// Get Zone ID
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Bearer {$cfApiToken}",
])->timeout(10)->get("https://api.cloudflare.com/client/v4/zones", ['name' => $zoneName]);

if ($response->successful()) {
    $zones = $response->json('result');
    if (!empty($zones)) {
        $zoneId = $zones[0]['id'];
        echo "Zone: {$zoneId}\n";

        // Get A records
        $dnsResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$cfApiToken}",
        ])->timeout(10)->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
            'type' => 'A',
            'per_page' => 100,
        ]);

        if ($dnsResponse->successful()) {
            $records = $dnsResponse->json('result');
            foreach ($records as $record) {
                $ip = $record['content'];
                $name = $record['name'];
                if (strpos($name, $zoneName) !== false && $name !== $zoneName) {
                    $mapping[$ip] = $name;
                }
            }
        }
    }
}

// Write cache
$cachePath = storage_path('cf-dns-ip-mapping.json');
file_put_contents($cachePath, json_encode(['ts' => time(), 'mapping' => $mapping]));

echo "Mapping: " . count($mapping) . " entries\n";
$i = 0;
foreach ($mapping as $ip => $domain) {
    echo "  $ip => $domain\n";
    if (++$i >= 5) break;
}
echo "Cache saved to: {$cachePath}\n";
