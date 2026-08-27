<?php

namespace Tests\Feature;

use App\Models\EmailEventLog;
use App\Models\MailLog;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-tracking-secret-do-not-use-in-prod';
    private string $messageId = 'msg_test_abc123';

    protected function setUp(): void
    {
        parent::setUp();
        // 测试用 secret — 与生产无关
        config(['mail.tracking_secret' => $this->secret]);
    }

    /**
     * 生成测试用 hash（与生产 MailTrackingService::makeHash 算法一致）
     */
    private function makeHash(string $messageId, int $expiresAt): string
    {
        $payload = $messageId . '|' . $expiresAt;
        $raw = hash_hmac('sha256', $payload, $this->secret, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'user' . uniqid() . '@example.com',
            'password' => password_hash('test1234', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'banned' => 0,
            'is_admin' => 0,
            'plan_id' => null,
            'expired_at' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ], $overrides));
    }

    private function makeMailLog(User $user): MailLog
    {
        return MailLog::create([
            'email' => $user->email,
            'subject' => 'test subject',
            'template_name' => 'notify',
            'created_at' => time(),
            'updated_at' => time(),
            'user_id' => $user->id,
            'message_id' => $this->messageId,
        ]);
    }

    public function test_open_pixel_returns_gif_and_writes_event(): void
    {
        $user = $this->makeUser();
        $mailLog = $this->makeMailLog($user);

        $expiresAt = time() + 86400 * 30;
        $hash = $this->makeHash($this->messageId, $expiresAt);
        $url = "/track/open/{$this->messageId}.{$expiresAt}.{$hash}.gif";

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
        // 1x1 GIF89a magic bytes
        $this->assertStringStartsWith('GIF89a', $response->getContent());

        $event = EmailEventLog::where('event_type', 'open')
            ->where('mail_log_id', $mailLog->id)
            ->first();
        $this->assertNotNull($event, 'expected open event to be written');
        $this->assertEquals($user->id, $event->user_id);
        $this->assertEquals($this->messageId, $event->message_id);
    }

    public function test_open_invalid_hash_returns_gif_silently(): void
    {
        $user = $this->makeUser();
        $this->makeMailLog($user);

        // 用错误 secret 算的 hash
        $wrongHash = rtrim(strtr(base64_encode(
            hash_hmac('sha256', 'fake', 'wrong-secret', true)
        ), '+/', '-_'), '=');
        $expiresAt = time() + 86400 * 30;
        $url = "/track/open/{$this->messageId}.{$expiresAt}.{$wrongHash}.gif";

        $response = $this->get($url);

        // 仍然返回 200 + GIF（不让发件人探测 hash 格式）
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
        // 但不写 event
        $this->assertEquals(0, EmailEventLog::where('event_type', 'open')->count());
    }

    public function test_open_expired_hash_returns_gif_silently(): void
    {
        $user = $this->makeUser();
        $this->makeMailLog($user);

        // 过期 hash（90 天前）
        $expiresAt = time() - 86400 * 90;
        $hash = $this->makeHash($this->messageId, $expiresAt);
        $url = "/track/open/{$this->messageId}.{$expiresAt}.{$hash}.gif";

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertEquals(0, EmailEventLog::where('event_type', 'open')->count());
    }

    public function test_click_redirects_to_target_and_writes_event(): void
    {
        $user = $this->makeUser();
        $mailLog = $this->makeMailLog($user);

        $expiresAt = time() + 86400 * 30;
        $hash = $this->makeHash($this->messageId, $expiresAt);
        $targetUrl = 'https://vp92vip.com/dashboard/plans?plan=2';
        $url = "/track/click/{$this->messageId}.{$expiresAt}.{$hash}?to=" . urlencode($targetUrl);

        $response = $this->get($url);

        $response->assertStatus(302);
        $response->assertRedirect($targetUrl);

        $event = EmailEventLog::where('event_type', 'click')
            ->where('mail_log_id', $mailLog->id)
            ->first();
        $this->assertNotNull($event, 'expected click event to be written');
        $this->assertEquals($user->id, $event->user_id);
        $this->assertEquals($targetUrl, $event->target_url);
    }
}
