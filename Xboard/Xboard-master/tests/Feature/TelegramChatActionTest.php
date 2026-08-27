<?php

namespace Tests\Feature;

use App\Services\TelegramChatAction;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TelegramChatAction — v20.5 Bot "正在输入" 状态发送服务
 *
 * 测试目标:
 *   - send() 正确调用 sendChatAction API (chat_id + action=typing)
 *   - HTTP 错误静默（不抛异常）
 *   - 网络异常静默（不抛异常）
 *   - action 参数可自定义
 *
 * 关联记录: v20.5 plan §16 (2026-08-28)
 */
class TelegramChatActionTest extends TestCase
{
    public function test_send_posts_to_sendChatAction_with_typing_default(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $result = TelegramChatAction::send('fake-token', 123456);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), '/sendChatAction')
                && ($body['chat_id'] ?? null) == 123456
                && ($body['action'] ?? null) === 'typing';
        });
    }

    public function test_send_supports_custom_action(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $result = TelegramChatAction::send('fake-token', 999, 'upload_photo');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['chat_id'] ?? null) == 999
                && ($body['action'] ?? null) === 'upload_photo';
        });
    }

    public function test_send_returns_false_on_http_error_silently(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'error' => 'Bad Request'], 400),
        ]);

        $result = TelegramChatAction::send('fake-token', 123456);

        $this->assertFalse($result);
    }

    public function test_send_returns_false_on_exception_silently(): void
    {
        // 触发异常的 fake
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });

        $result = TelegramChatAction::send('fake-token', 123456);

        $this->assertFalse($result);
    }
}
