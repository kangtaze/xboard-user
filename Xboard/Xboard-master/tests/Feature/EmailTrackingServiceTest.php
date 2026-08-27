<?php

namespace Tests\Feature;

use App\Models\EmailEventLog;
use App\Models\MailLog;
use App\Models\User;
use App\Models\UserIntent;
use App\Services\EmailTrackingService;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EmailTrackingService — v20 funnel 报表核心
 *
 * 聚合逻辑:
 *   mailed     = v2_mail_log 有效发送 (error IS NULL, created_at in window)
 *   opened     = DISTINCT mail_log_id WHERE event_type='open'
 *   clicked    = DISTINCT mail_log_id WHERE event_type='click'
 *   paid       = DISTINCT user_id in v2_order status IN(1,3), paid_at in window
 *
 * 关联 v2_user_intent.tag 用于按 tag 切片
 */
class EmailTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailTrackingService $service;

    /**
     * 直接创建 user（避免 factory 字段缺失）
     */
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

    private function makeMailLog(User $user, int $createdAt, ?string $error = null, ?string $messageId = null): MailLog
    {
        return MailLog::query()->create([
            'email' => $user->email,
            'subject' => 'test',
            'template_name' => 'notify',
            'error' => $error,
            'user_id' => $user->id,
            'message_id' => $messageId ?? ('msg_' . bin2hex(random_bytes(4))),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeIntent(User $user, string $tag, ?int $lastMailedAt = null): UserIntent
    {
        return UserIntent::query()->create([
            'user_id' => $user->id,
            'tag' => $tag,
            'last_mailed_at' => $lastMailedAt,
            'computed_at' => time(),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    private function makeOpen(MailLog $log, int $occurredAt, int $seq = 0): EmailEventLog
    {
        return EmailEventLog::query()->create([
            'mail_log_id' => $log->id,
            'user_id' => $log->user_id,
            'message_id' => $log->message_id,
            'event_type' => EmailEventLog::EVENT_OPEN,
            'occurred_at' => $occurredAt + $seq,
            'created_at' => $occurredAt + $seq,
            'updated_at' => $occurredAt + $seq,
        ]);
    }

    private function uniqueTradeNo(): string
    {
        return 't_' . bin2hex(random_bytes(6));
    }

    private function makePaidOrder(int $userId, int $status, int $paidAt): void
    {
        \DB::table('v2_order')->insert([
            'user_id' => $userId,
            'plan_id' => 1,
            'trade_no' => $this->uniqueTradeNo(),
            'total_amount' => 0,
            'type' => 1,
            'period' => 'month_price',
            'status' => $status,
            'commission_status' => 0,
            'commission_balance' => 0,
            'paid_at' => $paidAt,
            'created_at' => $paidAt - 86400,
            'updated_at' => $paidAt,
        ]);
    }

    private function makeClick(MailLog $log, int $occurredAt, ?string $targetUrl = null): EmailEventLog
    {
        return EmailEventLog::query()->create([
            'mail_log_id' => $log->id,
            'user_id' => $log->user_id,
            'message_id' => $log->message_id,
            'event_type' => EmailEventLog::EVENT_CLICK,
            'target_url' => $targetUrl ?? 'https://vp92vip.com/dashboard',
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTrackingService();
    }

    public function test_funnel_returns_zero_when_no_data(): void
    {
        $result = $this->service->funnel('expiring_soon', 7);

        $this->assertEquals(0, $result['mailed']);
        $this->assertEquals(0, $result['opened']);
        $this->assertEquals(0, $result['clicked']);
        $this->assertEquals(0, $result['paid']);
        $this->assertEquals(0.0, $result['open_rate']);
        $this->assertEquals(0.0, $result['click_rate']);
        $this->assertEquals(0.0, $result['paid_rate']);
    }

    public function test_funnel_counts_mailed_users_with_tag_and_no_error(): void
    {
        $now = time();

        // intent_high 用户，mailed 5 天前
        $user = $this->makeUser();
        $this->makeIntent($user, UserIntent::TAG_INTENT_HIGH);
        $log = $this->makeMailLog($user, $now - 5 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_INTENT_HIGH, 7);

        $this->assertEquals(1, $result['mailed']);
        $this->assertEquals(0, $result['opened']);
        $this->assertEquals(0, $result['clicked']);
        $this->assertEquals(0, $result['paid']);
    }

    public function test_funnel_excludes_error_mail_logs(): void
    {
        $now = time();

        $user1 = $this->makeUser();
        $this->makeIntent($user1, UserIntent::TAG_INTENT_HIGH);
        $this->makeMailLog($user1, $now - 2 * 86400, error: 'smtp fail');

        $user2 = $this->makeUser();
        $this->makeIntent($user2, UserIntent::TAG_INTENT_HIGH);
        $this->makeMailLog($user2, $now - 2 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_INTENT_HIGH, 7);

        $this->assertEquals(1, $result['mailed']);
    }

    public function test_funnel_excludes_mail_outside_window(): void
    {
        $now = time();

        $user = $this->makeUser();
        $this->makeIntent($user, UserIntent::TAG_INTENT_HIGH);
        // mailed 10 天前，窗口 7 天
        $this->makeMailLog($user, $now - 10 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_INTENT_HIGH, 7);

        $this->assertEquals(0, $result['mailed']);
    }

    public function test_funnel_counts_opened_clicked_paid(): void
    {
        $now = time();

        $user = $this->makeUser();
        $this->makeIntent($user, UserIntent::TAG_CHURNED_WARM);
        $log = $this->makeMailLog($user, $now - 3 * 86400);

        // 打开 + 点击
        $this->makeOpen($log, $now - 2 * 86400);
        $this->makeClick($log, $now - 1 * 86400);

        // 直接在 v2_order 插一条 paid（不依赖 Order 模型，避免复杂 factory）
        $this->makePaidOrder($user->id, 3, $now - 1 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_CHURNED_WARM, 7);

        $this->assertEquals(1, $result['mailed']);
        $this->assertEquals(1, $result['opened']);
        $this->assertEquals(1, $result['clicked']);
        $this->assertEquals(1, $result['paid']);
        $this->assertEquals(100.0, $result['open_rate']);
        $this->assertEquals(100.0, $result['click_rate']);
        $this->assertEquals(100.0, $result['paid_rate']);
    }

    public function test_funnel_dedupes_multiple_opens_per_user(): void
    {
        $now = time();

        $user = $this->makeUser();
        $this->makeIntent($user, UserIntent::TAG_INTENT_HIGH);
        $log = $this->makeMailLog($user, $now - 2 * 86400);

        // 同一封邮件 open 5 次
        for ($i = 0; $i < 5; $i++) {
            $this->makeOpen($log, $now - 1 * 86400 + $i);
        }

        $result = $this->service->funnel(UserIntent::TAG_INTENT_HIGH, 7);

        $this->assertEquals(1, $result['mailed']);
        $this->assertEquals(1, $result['opened'], 'DISTINCT mail_log_id');
    }

    public function test_open_rate_returns_per_tag_breakdown(): void
    {
        $now = time();

        $u1 = $this->makeUser();
        $this->makeIntent($u1, UserIntent::TAG_INTENT_HIGH);
        $l1 = $this->makeMailLog($u1, $now - 2 * 86400);
        $this->makeOpen($l1, $now - 1 * 86400);

        $u2 = $this->makeUser();
        $this->makeIntent($u2, UserIntent::TAG_INTENT_COLD);
        $l2 = $this->makeMailLog($u2, $now - 2 * 86400);
        $this->makeMailLog($u2, $now - 2 * 86400);  // 第二封没打开
        $this->makeClick($l2, $now - 1 * 86400);

        $rows = $this->service->openRate(7);

        $byTag = [];
        foreach ($rows as $r) {
            $byTag[$r['tag']] = $r;
        }

        $this->assertArrayHasKey(UserIntent::TAG_INTENT_HIGH, $byTag);
        $this->assertEquals(1, $byTag[UserIntent::TAG_INTENT_HIGH]['mailed']);
        $this->assertEquals(1, $byTag[UserIntent::TAG_INTENT_HIGH]['opened']);
        $this->assertEquals(100.0, $byTag[UserIntent::TAG_INTENT_HIGH]['open_rate']);

        $this->assertEquals(2, $byTag[UserIntent::TAG_INTENT_COLD]['mailed']);
        $this->assertEquals(0, $byTag[UserIntent::TAG_INTENT_COLD]['opened']);
        $this->assertEquals(0.0, $byTag[UserIntent::TAG_INTENT_COLD]['open_rate']);
    }

    public function test_intent_stats_returns_user_counts_and_last_mailed(): void
    {
        $now = time();

        // 3 个 intent_high，其中 1 个 mailed 过
        for ($i = 0; $i < 3; $i++) {
            $u = $this->makeUser();
            $this->makeIntent($u, UserIntent::TAG_INTENT_HIGH, $i === 0 ? $now - 2 * 86400 : null);
            if ($i === 0) {
                $this->makeMailLog($u, $now - 2 * 86400);
            }
        }

        // 2 个 churned_warm
        for ($i = 0; $i < 2; $i++) {
            $u = $this->makeUser();
            $this->makeIntent($u, UserIntent::TAG_CHURNED_WARM);
        }

        $stats = $this->service->intentStats();

        $this->assertEquals(3, $stats['user_counts'][UserIntent::TAG_INTENT_HIGH]);
        $this->assertEquals(2, $stats['user_counts'][UserIntent::TAG_CHURNED_WARM]);
        $this->assertEquals(0, $stats['user_counts'][UserIntent::TAG_EXPIRING_SOON]);
        $this->assertEquals(0, $stats['user_counts'][UserIntent::TAG_INTENT_COLD]);
        // 至少有一个 tag 有 last_mailed_at 时间戳
        $this->assertNotNull($stats['last_mailed_at'][UserIntent::TAG_INTENT_HIGH]);
        $this->assertNull($stats['last_mailed_at'][UserIntent::TAG_CHURNED_WARM]);
    }

    public function test_best_tag_by_roi_returns_highest_conversion_tag(): void
    {
        $now = time();

        // churned_warm: mailed 2, paid 1 → 50%
        for ($i = 0; $i < 2; $i++) {
            $u = $this->makeUser();
            $this->makeIntent($u, UserIntent::TAG_CHURNED_WARM);
            $this->makeMailLog($u, $now - 2 * 86400);
            if ($i === 0) {
                $this->makePaidOrder($u->id, 3, $now - 1 * 86400);
            }
        }

        // intent_high: mailed 10, paid 1 → 10%
        for ($i = 0; $i < 10; $i++) {
            $u = $this->makeUser();
            $this->makeIntent($u, UserIntent::TAG_INTENT_HIGH);
            $this->makeMailLog($u, $now - 2 * 86400);
            if ($i === 0) {
                $this->makePaidOrder($u->id, 3, $now - 1 * 86400);
            }
        }

        $best = $this->service->bestTagByRoi(7);

        $this->assertEquals(UserIntent::TAG_CHURNED_WARM, $best['tag']);
        $this->assertEquals(2, $best['mailed']);
        $this->assertEquals(1, $best['paid']);
        $this->assertEquals(50.0, $best['paid_rate']);
    }

    public function test_funnel_only_counts_paid_orders_with_paid_at_in_window(): void
    {
        $now = time();

        $user = $this->makeUser();
        $this->makeIntent($user, UserIntent::TAG_EXPIRING_SOON);
        $log = $this->makeMailLog($user, $now - 2 * 86400);

        // order 状态 paid 但 paid_at 在窗口外
        $this->makePaidOrder($user->id, 3, $now - 30 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_EXPIRING_SOON, 7);

        $this->assertEquals(1, $result['mailed']);
        $this->assertEquals(0, $result['paid']);
    }

    public function test_funnel_paid_status_accepts_1_or_3(): void
    {
        $now = time();

        $u1 = $this->makeUser();
        $this->makeIntent($u1, UserIntent::TAG_INTENT_COLD);
        $this->makeMailLog($u1, $now - 2 * 86400);
        $this->makePaidOrder($u1->id, 1, $now - 1 * 86400);

        $u2 = $this->makeUser();
        $this->makeIntent($u2, UserIntent::TAG_INTENT_COLD);
        $this->makeMailLog($u2, $now - 2 * 86400);
        $this->makePaidOrder($u2->id, 2, $now - 1 * 86400);

        $result = $this->service->funnel(UserIntent::TAG_INTENT_COLD, 7);

        $this->assertEquals(2, $result['mailed']);
        $this->assertEquals(1, $result['paid']);
    }
}