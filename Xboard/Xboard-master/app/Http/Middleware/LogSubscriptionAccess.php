<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionLog;
use App\Services\GeoIpService;
use App\Services\ServerService;
use App\Utils\ClientFingerprint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LogSubscriptionAccess — 记录用户拉取订阅 URL 的行为
 *
 * 用途:
 * - 写 v2_subscription_log 表, 用于 admin 后台「用户详情 → 订阅拉取记录」展示
 * - 区分 Xboard 现有的「登录行为」(last_login_at/last_login_ip) — 本中间件记录「拉订阅行为」
 *
 * 触发:
 * - 注册到 app/Http/Kernel.php 的 $middleware 全局中间件
 * - 仅对 GET /s/{token} 路由生效 (订阅入口, routes/web.php:130)
 * - 排除: 未认证请求 / OPTIONS 探测 / 非订阅路径
 *
 * 关联文档:
 * - database/migrations/2026_08_27_000001_create_v2_subscription_log.php
 * - App\Services\GeoIpService
 * - App\Utils\ClientFingerprint
 * - docs/session-progress-latest.md (v15.11 章节)
 */
class LogSubscriptionAccess
{
    /**
     * 路由前缀 (订阅入口, Xboard admin_setting 'subscribe_path' 默认 's')
     * 完整路径: /s/{token}
     */
    private const SUBSCRIBE_PATH_PREFIX = '/s/';

    public function handle(Request $request, Closure $next)
    {
        // 仅记录订阅路由 + 可识别用户
        if ($this->shouldLog($request)) {
            try {
                $this->recordSubscriptionAccess($request);
            } catch (\Throwable $e) {
                // 记录失败不影响订阅响应
                Log::warning('LogSubscriptionAccess failed', [
                    'err' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * 判断是否需要记录: 仅订阅路径.
     */
    private function shouldLog(Request $request): bool
    {
        $path = $request->path(); // 不含 leading /
        if (!str_starts_with($path, 's/')) {
            return false;
        }
        // 仅 GET 拉订阅 (POST 等其他方法不记)
        if (!$request->isMethod('GET')) {
            return false;
        }
        return true;
    }

    /**
     * 写入订阅访问记录.
     */
    private function recordSubscriptionAccess(Request $request): void
    {
        // 优先用 auth middleware 已注入的 user; 若无, 从 path 末尾 token 反查 v2_user
        // (Swoole 模式下 $request->route('token') 不可靠 — 返回空字符串,
        //  直接从 path split 更稳定)
        $user = $request->user();
        if (!$user) {
            $parts = explode('/', $request->path(), 2);
            $token = $parts[1] ?? '';
            if ($token === '') {
                return;
            }
            $user = \App\Models\User::where('token', $token)->first();
            if (!$user) {
                return;
            }
        }

        $ip = $request->ip();
        $ua = $request->userAgent() ?? '';

        // 客户端指纹
        $fp = ClientFingerprint::parse($ua);

        // 地理位置
        $geo = app(GeoIpService::class)->lookup($ip);

        // 节点数 (订阅响应前的可用节点)
        $nodeCount = 0;
        try {
            $servers = app(ServerService::class)->getAvailableServers($user);
            $nodeCount = is_countable($servers) ? count($servers) : 0;
        } catch (\Throwable $e) {
            // 节点数查不到不影响日志写入
            Log::debug('LogSubscriptionAccess: getAvailableServers failed', [
                'user_id' => $user->id,
                'err' => $e->getMessage(),
            ]);
        }

        $now = time();
        SubscriptionLog::create([
            'user_id' => $user->id,
            'ip' => $ip,
            'ip_int' => is_numeric($ip) ? (int)$ip : (ip2long($ip) ?: null),
            'user_agent' => mb_substr($ua, 0, 512),
            'client_type' => $fp['type'],
            'client_version' => $fp['version'],
            'os' => $fp['os'],
            'country' => $geo['country'],
            'province' => $geo['province'],
            'city' => $geo['city'],
            'isp' => $geo['isp'],
            'node_count' => $nodeCount,
            'subscription_token' => $user->token ? substr($user->token, 0, 8) . '***' : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
