<?php
$file = '/www/app/Models/GiftCardCode.php';
$content = file_get_contents($file);

// Extract markAsUsed
preg_match('/public function markAsUsed.*?\n        \}/s', $content, $m);
echo "=== markAsUsed ===\n";
echo $m[0] ?? "NOT FOUND\n\n";

// Extract status constants
preg_match('/const STATUS_.*\n.*\n.*\n.*\n/s', $content, $m);
echo "=== STATUS CONSTANTS ===\n";
echo $m[0] ?? "NOT FOUND\n\n";

// Also check actual gift card codes in DB
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$codes = DB::table('v2_gift_card_code')->select('id','code','status','usage_count','max_usage','used_at','expires_at')->get();
echo "=== GIFT CARD CODES IN DB ===\n";
foreach ($codes as $c) {
    echo "id={$c->id} status={$c->status} usage={$c->usage_count} max={$c->max_usage} used_at={$c->used_at} expires_at={$c->expires_at} code=" . substr($c->code, 0, 8) . "...\n";
}
