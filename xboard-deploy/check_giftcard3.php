<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== GIFT CARD CODES ===\n";
$codes = DB::table('v2_gift_card_code')->get();
echo "Total codes: " . $codes->count() . "\n\n";
foreach ($codes as $c) {
    echo "id={$c->id} code=" . substr($c->code, 0, 12) . " status={$c->status} usage_count=" . var_export($c->usage_count, true) . " max_usage=" . var_export($c->max_usage, true) . " user_id=" . var_export($c->user_id, true) . "\n";
}

echo "\n=== GIFT CARD TEMPLATES ===\n";
$templates = DB::table('v2_gift_card_template')->get();
foreach ($templates as $t) {
    echo "id={$t->id} name={$t->name} type={$t->type} status={$t->status} max_usage_per_code=" . var_export($t->max_usage_per_code ?? $t->max_usage ?? 'N/A', true) . "\n";
}
