<?php

namespace App\Http\Controllers;

use App\Models\EmailEventLog;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 邮件追踪控制器（v20 tracking 基建）
 *
 * 三个路由：
 * - GET /track/open/{hash}.gif      → 1x1 GIF + 写 open event（hash 错误/过期静默返回 GIF）
 * - GET /track/click/{hash}?to=...  → 302 redirect + 写 click event
 * - GET /track/unsubscribe/{hash}   → 200 确认页 + 写 unsubscribe event
 *
 * URL 格式：
 *   /track/open/{messageId}.{expiresAt}.{hash}.gif
 *   /track/click/{messageId}.{expiresAt}.{hash}?to=<encoded target_url>
 *   /track/unsubscribe/{messageId}.{expiresAt}.{hash}
 *
 * Hash 算法：HMAC-SHA256(secret, messageId | expiresAt) base64url
 *   - secret 从 config('mail.tracking_secret') 读
 *   - expiresAt Unix timestamp，默认 90 天
 *
 * 为什么不绑定到 mail_log_id：暴露内部 ID 易被遍历
 */
class TrackController extends Controller
{
    /** 1x1 transparent GIF89a (43 bytes) */
    private const TRACKING_PIXEL = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3b";

    public function open(Request $request, string $hashParam): Response
    {
        $parsed = $this->parseHash($hashParam);

        // 不论 hash 是否有效都返回 GIF（不暴露格式）
        $response = new \Illuminate\Http\Response(self::TRACKING_PIXEL, 200);
        $response->headers->set('Content-Type', 'image/gif');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        if ($parsed === null) {
            return $response;
        }
        [$messageId, $expiresAt] = $parsed;

        if ($expiresAt < time()) {
            return $response;
        }

        // 写 open event
        try {
            $mailLog = MailLog::where('message_id', $messageId)->first();
            EmailEventLog::create([
                'mail_log_id' => $mailLog?->id ?? 0,
                'user_id' => $mailLog?->user_id,
                'message_id' => $messageId,
                'event_type' => EmailEventLog::EVENT_OPEN,
                'ip_hash' => $this->hashIp($request->ip()),
                'ua' => substr((string) $request->userAgent(), 0, 255),
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // 静默失败 — 不能让追踪错误影响邮件渲染
            Log::warning('TrackController::open failed', ['err' => $e->getMessage()]);
        }

        return $response;
    }

    public function click(Request $request, string $hashParam): Response
    {
        $targetUrl = (string) $request->query('to', '');
        $parsed = $this->parseHash($hashParam);

        // 即便 hash 无效或 target_url 缺失，也跳回 dashboard（不暴露 hash 格式）
        if ($parsed === null || $targetUrl === '' || !str_starts_with($targetUrl, 'http')) {
            return redirect('https://vp92vip.com/dashboard');
        }

        [$messageId, $expiresAt] = $parsed;

        // hash 过期仍跳转（不破坏用户体验），但不写 event
        if ($expiresAt < time()) {
            return redirect($targetUrl);
        }

        try {
            $mailLog = MailLog::where('message_id', $messageId)->first();
            EmailEventLog::create([
                'mail_log_id' => $mailLog?->id ?? 0,
                'user_id' => $mailLog?->user_id,
                'message_id' => $messageId,
                'event_type' => EmailEventLog::EVENT_CLICK,
                'target_url' => $targetUrl,
                'ip_hash' => $this->hashIp($request->ip()),
                'ua' => substr((string) $request->userAgent(), 0, 255),
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TrackController::click failed', ['err' => $e->getMessage()]);
        }

        return redirect($targetUrl);
    }

    public function unsubscribe(Request $request, string $hashParam): Response
    {
        $parsed = $this->parseHash($hashParam);

        $html = view('mail.tracking.unsubscribe_confirm', [
            'valid' => $parsed !== null && $parsed[1] >= time(),
            'hashParam' => $hashParam,
        ])->render();

        $response = new \Illuminate\Http\Response($html, 200);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');

        if ($parsed === null || $parsed[1] < time()) {
            return $response;
        }

        [$messageId] = $parsed;

        try {
            $mailLog = MailLog::where('message_id', $messageId)->first();
            EmailEventLog::create([
                'mail_log_id' => $mailLog?->id ?? 0,
                'user_id' => $mailLog?->user_id,
                'message_id' => $messageId,
                'event_type' => EmailEventLog::EVENT_UNSUBSCRIBE,
                'ip_hash' => $this->hashIp($request->ip()),
                'ua' => substr((string) $request->userAgent(), 0, 255),
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TrackController::unsubscribe failed', ['err' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * 解析 /track/{action}/{messageId}.{expiresAt}.{hash}[.gif]
     * 验证 HMAC，成功返回 [messageId, expiresAt]，失败返回 null
     */
    private function parseHash(string $hashParam): ?array
    {
        // open 路由 URL 以 .gif 结尾，先去掉
        $stripped = preg_replace('/\.gif$/i', '', $hashParam);
        $parts = explode('.', $stripped);
        if (count($parts) !== 3) {
            return null;
        }
        [$messageId, $expiresAtStr, $hash] = $parts;
        if (!ctype_digit($expiresAtStr)) {
            return null;
        }
        $expiresAt = (int) $expiresAtStr;
        $secret = config('mail.tracking_secret');
        if (empty($secret)) {
            return null;
        }
        $expected = $this->makeHash($messageId, $expiresAt, $secret);
        if (!hash_equals($expected, $hash)) {
            return null;
        }
        return [$messageId, $expiresAt];
    }

    public static function makeHash(string $messageId, int $expiresAt, string $secret): string
    {
        $raw = hash_hmac('sha256', $messageId . '|' . $expiresAt, $secret, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function hashIp(?string $ip): ?string
    {
        if (!$ip) return null;
        $secret = config('mail.tracking_secret') ?: 'fallback';
        return hash('sha256', $ip . $secret);
    }
}