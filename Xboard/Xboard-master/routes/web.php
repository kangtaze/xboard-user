<?php

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function (Request $request) {
    if (admin_setting('app_url') && admin_setting('safe_mode_enable', 0)) {
        $requestHost = $request->getHost();
        $configHost = parse_url(admin_setting('app_url'), PHP_URL_HOST);
        
        if ($requestHost !== $configHost) {
            abort(403);
        }
    }

    $theme = admin_setting('frontend_theme', 'Xboard');
    $themeService = new ThemeService();

    try {
        if (!$themeService->exists($theme)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
            $themeService->switch($theme);
        }

        if (!$themeService->getThemeViewPath($theme)) {
            throw new Exception('主题视图文件不存在');
        }

        $publicThemePath = public_path('theme/' . $theme);
        if (!File::exists($publicThemePath)) {
            $themePath = $themeService->getThemePath($theme);
            if (!$themePath || !File::copyDirectory($themePath, $publicThemePath)) {
                throw new Exception('主题初始化失败');
            }
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'Xboard'),
            'theme' => $theme,
            'version' => app(UpdateService::class)->getCurrentVersion(),
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeService->getConfig($theme),
            // XboardFerrari v10: landing.blade.php 通过 @isset($windows_download_url) 等读取，
            // 必须从 admin_setting 注入，否则下载卡片永远显示"敬请期待"。
            // 注意：DB 实际只存了 windows_*/macos_*/android_*（无 ios_*），macos 给 iOS 兜底
            'windows_download_url' => admin_setting('windows_download_url'),
            'windows_version' => admin_setting('windows_version'),
            'android_download_url' => admin_setting('android_download_url'),
            'android_version' => admin_setting('android_version'),
            // iOS: 优先 ios_*，缺则回落到 macos_*（DB 实际命名）
            'ios_download_url' => admin_setting('ios_download_url') ?: admin_setting('macos_download_url'),
            'ios_version' => admin_setting('ios_version') ?: admin_setting('macos_version', '苹果端自备-小火箭-Shadowrocket'),
            // 8/10 落地的 SEO 背景图
            'background_url' => admin_setting('frontend_background_url'),
        ];
        // XboardFerrari: 根路径渲染落地页 landing.blade.php（恢复 v10 用户报告：vp92vip.com 应为营销落地页）
        // 命名空间 theme::VP92 → /www/theme/VP92/landing.blade.php
        return view('theme::' . $theme . '.landing', $renderParams);
    } catch (Exception $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
            'error' => $e->getMessage()
        ]);
        abort(500, '主题加载失败');
    }
});

// XboardFerrari v10: 恢复 2026-08-10 加的 SEO 法律页/教程路由（commit 3c6cd38 后 submodule 迁移丢失）
// landing.blade.php 引用 /vp92-tutorial.html /vp92-terms.html /privacy-policy.html，
// sitemap.xml 也列出这三页；缺一 → GSC 报语法错误 + 用户看到 404。
// 渲染源在 /www/theme/VP92/（nginx 静态副本在 /www/public/）
Route::get('/about', function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    return view('theme::' . $theme . '.about');
});

Route::get('/vp92-tutorial.html', function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    return view('theme::' . $theme . '.vp92-tutorial');
});
Route::get('/vp92-terms.html', function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    return view('theme::' . $theme . '.vp92-terms');
});
Route::get('/privacy-policy.html', function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    return view('theme::' . $theme . '.privacy-policy');
});
Route::get('/privacy-privacy.html', function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    return view('theme::' . $theme . '.privacy-privacy');
});

//TODO:: 兼容
Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin', [
        'title' => admin_setting('app_name', 'XBoard'),
        'theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
        'theme_header' => admin_setting('frontend_theme_header', 'dark'),
        'theme_color' => admin_setting('frontend_theme_color', 'default'),
        'background_url' => admin_setting('frontend_background_url'),
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'logo' => admin_setting('logo'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . (admin_setting('subscribe_path', 's')) . '/{token}', [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])
    ->middleware('client')
    ->name('client.subscribe');

// XboardFerrari: SPA shell 路由 — 登录/注册/忘记密码/用户面板走 dashboard.blade.php（Vue SPA 接管）
// 不影响 /（landing）/s/{token}（订阅）/{secure_path}（后台）
$dashboardShell = function () {
    $theme = admin_setting('frontend_theme', 'VP92');
    $renderParams = [
        'title' => admin_setting('app_name', 'VP92极速加速器'),
        'theme' => $theme,
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'description' => admin_setting('app_description', 'VP92极速加速器'),
        'logo' => admin_setting('logo'),
        'theme_config' => (new ThemeService())->getConfig($theme),
    ];
    return view('theme::' . $theme . '.dashboard', $renderParams);
};
Route::get('/login', $dashboardShell);
Route::get('/register', $dashboardShell);
Route::get('/forgot', $dashboardShell);
// SPA 路径：dashboard, auth 全部命中（Vue router 内部处理）
Route::get('/dashboard/{path?}', $dashboardShell)->where('path', '.*');
Route::get('/auth/{path?}', $dashboardShell)->where('path', '.*');