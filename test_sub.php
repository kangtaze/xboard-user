<?php
require '/www/app/Http/Kernel.php';
echo "Loading Xboard...\n";
$user = \App\Models\User::find(2);
echo "User: " . $user->email . " (group_id=" . $user->group_id . ")\n";
$servers = \App\Services\ServerService::getAvailableServers($user);
echo "Total servers: " . count($servers) . "\n";
foreach ($servers as $s) {
    $etag = isset($s['edgetunnel']) ? '[EDGETUNNEL]' : '[XBOARD]';
    echo "  {$etag} {$s['name']} ({$s['type']}) @ {$s['host']}:{$s['port']}\n";
}
echo "Done!\n";
