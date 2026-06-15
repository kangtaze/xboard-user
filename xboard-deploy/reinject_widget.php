<?php
require '/www/vendor/autoload.php';
$app = require_once '/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Read updated widget HTML
$html = file_get_contents('/tmp/gift-card-widget.html');
if (!$html) {
    // Try reading from stdin if not on server yet
    $html = file_get_contents('php://stdin');
}
if (!$html || strlen($html) < 100) {
    echo "ERROR: widget HTML is too short or empty\n";
    exit(1);
}

// Use raw DB to avoid model cast issues
$row = \Illuminate\Support\Facades\DB::table('v2_settings')
    ->where('name', 'theme_xboard')
    ->first();

if (!$row) {
    echo "ERROR: theme_xboard setting not found\n";
    exit(1);
}

$val = json_decode($row->value, true);
if (!is_array($val)) {
    $val = [];
}
$val['custom_html'] = $html;

\Illuminate\Support\Facades\DB::table('v2_settings')
    ->where('name', 'theme_xboard')
    ->update(['value' => json_encode($val)]);

echo "DONE - injected " . strlen($html) . " bytes\n";
