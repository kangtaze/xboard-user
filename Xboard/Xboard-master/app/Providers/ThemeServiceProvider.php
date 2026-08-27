<?php

namespace App\Providers;

use App\Services\ThemeService;
use Illuminate\Support\ServiceProvider;

/**
 * ThemeServiceProvider
 *
 * 注册 theme:: 视图命名空间 hint path，让 view('theme::VP92.xxx') 能正确解析。
 *
 * 背景：ThemeService::__construct() 会调用 registerThemeViewPaths()，
 * 但只有 XboardUpdate 命令和 ConfigController::save() 实例化了它——
 * 正常 HTTP 请求路由（如 /privacy-policy.html）从未实例化，导致
 * "No hint path defined for [theme]" 500 错误。
 *
 * 修复：在每个 web 请求 boot 时实例化一次 ThemeService，确保 namespace 已注册。
 *
 * 使用：在 config/app.php 的 providers 数组里追加本类。
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 实例化 ThemeService 时会自动调用 registerThemeViewPaths()
        $this->app->singleton(ThemeService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 确保 namespace 在每个请求启动时已注册
        $this->app->make(ThemeService::class);
    }
}