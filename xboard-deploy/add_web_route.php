<?php
$file = '/www/routes/web.php';
$content = file_get_contents($file);

$newRoute = <<<'PHP'

Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))) . '/cf-worker', function () {
    return view('admin.cf-worker', [
        'title' => admin_setting('app_name', 'XBoard'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))),
    ]);
});
PHP;

// Insert after the edgetunnel route block
$marker = "});

Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {";
$replacement = "});" . $newRoute . "\n\nRoute::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {";

$newContent = str_replace($marker, $replacement, $content);

if ($newContent === $content) {
    echo "MARKER NOT FOUND - trying alternative\n";
    // Try inserting after the edgetunnel closing
    $alt = "    ]);\n});\n\nRoute::get('/'";
    $pos = strpos($content, $alt, strpos($content, 'edgetunnel'));
    if ($pos !== false) {
        $newContent = substr($content, 0, $pos) . "    ]);\n});" . $newRoute . "\n\nRoute::get('/" . substr($content, $pos + strlen($alt));
        echo "ALT INSERT OK\n";
    } else {
        echo "FAILED\n";
        exit(1);
    }
}

file_put_contents($file, $newContent);
echo "DONE\n";
