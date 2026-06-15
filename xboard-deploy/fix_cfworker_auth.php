<?php
$file = file_get_contents("/www/resources/views/admin/cf-worker.blade.php");

// 添加 getAuthToken 函数，替换 basePath 定义
$old = "const basePath = '/api/v2/{{ \$secure_path ?? \"admin\" }}';";
$new = "function getAuthToken() {
    try {
        const stored = localStorage.getItem('XBOARD_ACCESS_TOKEN');
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed && parsed.value) return parsed.value;
        }
    } catch(e) {}
    return '';
}
const SECURE_PATH = '{{ $secure_path ?? \"admin\" }}';
const basePath = '/api/v2/' + SECURE_PATH;";

$file = str_replace($old, $new, $file);

// 给所有 fetch 添加 Authorization header
$file = str_replace(
    "headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '' }",
    "headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '', 'Authorization': getAuthToken() }",
    $file
);

file_put_contents("/www/resources/views/admin/cf-worker.blade.php", $file);
echo "Done - getAuthToken added and Authorization headers set\n";
