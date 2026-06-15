<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('is_admin', 0)->first();
if (!$user) {
    echo "No non-admin user found\n";
    exit;
}

$service = app(\XboardFerrari\Services\EdgetunnelService::class);
$nodes = $service->getAvailableNodes($user);
echo "Available nodes: " . $nodes->count() . "\n";

// Check CF DNS mapping
$cachePath = storage_path('cf-dns-ip-mapping.json');
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    $mapping = $cached['mapping'] ?? [];
    echo "DNS mapping entries: " . count($mapping) . "\n";
} else {
    echo "DNS mapping cache: NOT FOUND\n";
    $mapping = [];
}

// Show first 3 nodes with their mapped domain
foreach ($nodes->take(3) as $n) {
    $domain = $mapping[$n->host] ?? $n->host;
    echo "  {$n->name}: host={$n->host} -> mapped={$domain}\n";
}

// Check admin setting for info display
echo "\nshow_info_to_server_enable: " . (admin_setting('show_info_to_server_enable', 0) ? 'ON' : 'OFF') . "\n";
echo "show_protocol_to_server_enable: " . (admin_setting('show_protocol_to_server_enable', 0) ? 'ON' : 'OFF') . "\n";
