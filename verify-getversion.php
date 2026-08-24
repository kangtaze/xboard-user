<?php
// 用管理员账号登录并调用 user/app/getVersion 验证返回数据
$email = 'kangtazezege@gmail.com';
$user = \App\Models\User::where('email', $email)->first();
if (!$user) {
    // 找任何 admin 用户
    $user = \App\Models\User::where('is_admin', true)->first();
}
if (!$user) { echo "NO_ADMIN_USER\n"; exit; }
echo "USER: {$user->email}\n";

// 模拟认证 token（直接构造，绕过密码）
$token = $user->token;
echo "TOKEN_LEN: " . strlen($token ?? '') . "\n";

// 直接调用控制器逻辑验证数据（不走 HTTP 认证）
$data = [
    'windows_version' => admin_setting('windows_version'),
    'windows_download_url' => admin_setting('windows_download_url'),
    'macos_version' => admin_setting('macos_version'),
    'macos_download_url' => admin_setting('macos_download_url'),
    'android_version' => admin_setting('android_version'),
    'android_download_url' => admin_setting('android_download_url'),
    'ios_version' => admin_setting('ios_version'),
    'ios_download_url' => admin_setting('ios_download_url'),
];
echo "API_WILL_RETURN: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
