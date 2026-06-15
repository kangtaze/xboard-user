<?php
// Bootstrap Laravel
require __DIR__ . '/../www/vendor/autoload.php';
$app = require_once __DIR__ . '/../www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read widget HTML
$html = file_get_contents('/tmp/gift-card-widget.html');
if (!$html) {
    echo "ERROR: cannot read /tmp/gift-card-widget.html\n";
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
