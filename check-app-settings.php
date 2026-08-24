<?php
// 查询 APP 下载相关设置
$names = [
    'windows_download_url', 'windows_version',
    'android_download_url', 'android_version',
    'macos_download_url', 'macos_version',
    'ios_download_url', 'ios_version',
    'app_url', 'frontend_theme',
];
foreach ($names as $name) {
    $val = admin_setting($name);
    echo str_pad($name, 26) . " => " . (is_null($val) ? '(NULL 未设置)' : $val) . PHP_EOL;
}
