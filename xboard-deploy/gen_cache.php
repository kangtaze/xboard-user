<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Manually call the buildIpDomainMapping method
$provider = app(\XboardFerrari\Providers\EdgetunnelServiceProvider::class);
$reflection = new \ReflectionClass($provider);
$method = $reflection->getMethod('buildIpDomainMapping');
$method->setAccessible(true);

$mapping = $method->invoke($provider);
echo "Generated mapping: " . count($mapping) . " entries\n";

// Show sample
$i = 0;
foreach ($mapping as $ip => $domain) {
    echo "  $ip => $domain\n";
    if (++$i >= 5) break;
}

// Check cache file
$cachePath = storage_path('cf-dns-ip-mapping.json');
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    echo "\nCache file created at: " . date('Y-m-d H:i:s', $cached['ts']) . "\n";
} else {
    echo "\nCache file NOT created\n";
}