<?php
$file = '/www/resources/views/admin/cf-worker.blade.php';
$content = file_get_contents($file);

$old = "const basePath = '/{{ \$secure_path ?? \"admin\" }}';";
$new = "const basePath = '/api/v2/{{ \$secure_path ?? \"admin\" }}';";

$content = str_replace($old, $new, $file);
// Actually, need to use the literal string, not PHP variable interpolation
// Read raw file and replace literal text

$content = file_get_contents($file);
$search = "const basePath = '/" . "\{\{ " . "\$secure_path" . " ?? \"admin\" " . "\}\}" . "';";
echo "Looking for: " . $search . "\n";
echo "Found: " . (strpos($content, $search) !== false ? "YES" : "NO") . "\n";

// Simpler approach: just find and replace the literal string
$content = str_replace(
    "const basePath = '/{{ \$secure_path ?? \"admin\" }}';",
    "const basePath = '/api/v2/{{ \$secure_path ?? \"admin\" }}';",
    $content
);

file_put_contents($file, $content);
echo "DONE\n";

// Verify
$check = file_get_contents($file);
if (strpos($check, '/api/v2/{{ $secure_path') !== false) {
    echo "VERIFIED: basePath now includes /api/v2\n";
} else {
    echo "WARNING: replacement may have failed\n";
    echo "First basePath line: " . substr($check, strpos($check, 'basePath'));
}
