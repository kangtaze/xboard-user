<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailServiceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-tracking-secret-mail';
    private string $messageId = 'msg_mail_test_001';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.tracking_secret' => $this->secret]);
    }

    public function test_injectTracking_replaces_pixel_placeholder_with_img_tag(): void
    {
        $html = '<html><body>Hello {{tracking_pixel}}</body></html>';

        $result = MailService::injectTracking($html, $this->messageId, time() + 86400 * 30, $this->secret);

        $this->assertStringNotContainsString('{{tracking_pixel}}', $result);
        $this->assertStringContainsString('<img src="https://vp92vip.com/track/open/', $result);
        $this->assertStringContainsString('.gif"', $result);
        $this->assertStringContainsString('width="1" height="1"', $result);
        $this->assertStringContainsString($this->messageId, $result);
    }

    public function test_injectTracking_replaces_click_base_placeholder(): void
    {
        $html = '<a href="{{tracking_click_base}}' . urlencode('https://vp92vip.com/dashboard/plans') . '">订阅</a>';

        $result = MailService::injectTracking($html, $this->messageId, time() + 86400 * 30, $this->secret);

        $this->assertStringNotContainsString('{{tracking_click_base}}', $result);
        $this->assertStringContainsString('https://vp92vip.com/track/click/', $result);
        $this->assertStringContainsString('?to=https', $result);
    }

    public function test_injectTracking_replaces_unsubscribe_placeholder(): void
    {
        $html = '<a href="{{unsubscribe_url}}">取消订阅</a>';

        $result = MailService::injectTracking($html, $this->messageId, time() + 86400 * 30, $this->secret);

        $this->assertStringNotContainsString('{{unsubscribe_url}}', $result);
        $this->assertStringContainsString('https://vp92vip.com/track/unsubscribe/', $result);
        $this->assertStringContainsString($this->messageId, $result);
    }

    public function test_injectTracking_leaves_html_unchanged_when_placeholders_absent(): void
    {
        $html = '<html><body>没有任何占位符的普通邮件</body></html>';

        $result = MailService::injectTracking($html, $this->messageId, time() + 86400 * 30, $this->secret);

        $this->assertEquals($html, $result);
    }

    public function test_injectTracking_uses_trackController_compatible_hash(): void
    {
        // 验证 injectTracking 生成的 URL 能在 TrackController 解析（hash 兼容）
        $expiresAt = time() + 86400 * 30;
        $html = '<img src="{{tracking_pixel}}">';

        $result = MailService::injectTracking($html, $this->messageId, $expiresAt, $this->secret);

        // 提取 hash 段
        preg_match('#/track/open/' . preg_quote($this->messageId, '#') . '\.(\d+)\.([A-Za-z0-9_-]+)\.gif#', $result, $m);
        $this->assertNotEmpty($m, 'URL 格式应为 /track/open/{messageId}.{expiresAt}.{hash}.gif');

        $extractedExpiresAt = (int) $m[1];
        $extractedHash = $m[2];
        $this->assertEquals($expiresAt, $extractedExpiresAt);

        // 验证 hash 可被 TrackController 算法复算
        $expected = \App\Http\Controllers\TrackController::makeHash($this->messageId, $extractedExpiresAt, $this->secret);
        $this->assertEquals($expected, $extractedHash);
    }

    public function test_sendEmail_persists_message_id_to_mail_log(): void
    {
        // 拦截 Brevo HTTP 调用（无真发邮件）
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'brevo-123'], 201),
        ]);

        MailService::sendEmail([
            'email' => 'test@example.com',
            'subject' => 'tracking 测试',
            'template_name' => 'notify',  // 系统默认 blade 模板
            'template_value' => [
                'name' => '测试用户',
                'content' => '这是一条带追踪的测试通知',
                'url' => 'https://vp92vip.com/dashboard',
            ],
        ]);

        $log = MailLog::orderBy('id', 'desc')->first();
        $this->assertNotNull($log, '应该写入了 mail_log');
        $this->assertNotEmpty($log->message_id, 'message_id 应该被持久化');
        $this->assertStringStartsWith('msg_', $log->message_id);
        $this->assertNull($log->error, '邮件发送应该成功（Http::fake）');
    }
}