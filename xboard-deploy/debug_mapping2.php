<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cfApiToken = config('edgetunnel.cf_api_token');
$zoneName = 'ferrari.cc.cd';

// Test with correct Http::withHeaders() syntax
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Bearer {$cfApiToken}",
])->timeout(10)->get("https://api.cloudflare.com/client/v4/zones", [
    'name' => $zoneName,
]);

echo "CF API status: " . $response->status() . "\n";
echo "CF API success: " . ($response->successful() ? 'YES' : 'NO') . "\n";

if ($response->successful()) {
    $zones = $response->json('result');
    echo "Zones found: " . count($zones) . "\n";

    if (!empty($zones)) {
        $zoneId = $zones[0]['id'];
        echo "Zone ID: {$zoneId}\n";

        $dnsResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$cfApiToken}",
        ])->timeout(10)->get(
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records",
            ['type' => 'A', 'per_page' => 100]
        );

        echo "DNS API status: " . $dnsResponse->status() . "\n";
        if ($dnsResponse->successful()) {
            $records = $dnsResponse->json('result');
            echo "DNS records: " . count($records) . "\n";

            $mapping = [];
            foreach ($records as $record) {
                $ip = $record['content'];
                $name = $record['name'];
                if (strpos($name, $zoneName) !== false && $name !== $zoneName) {
                    $mapping[$ip] = $name;
                }
            }

            echo "Mapping entries: " . count($mapping) . "\n";

            // Test against actual node IPs
            $nodes = \XboardFerrari\Models\EdgetunnelNode::select('name','host')->limit(5)->get();
            foreach ($nodes as $n) {
                $domain = $mapping[$n->host] ?? 'NOT FOUND';
                echo "  " . $n->name . " (" . $n->host . ") => " . $domain . "\n";
            }
        }
    }
} else {
    echo "Error: " . $response->body() . "\n";
}
