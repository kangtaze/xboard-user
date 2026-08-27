<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserIntent;
use App\Services\EmailTrackingService;
use App\Services\SendWeeklyReportLogic;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SendWeeklyReport — v20.4 周报推送核心
 *
 * 核心逻辑:
 *   buildReport()       — 复用 EmailTrackingService::funnel() × 5 tag + bestTagByRoi()
 *   pushToTelegram()    — 查 is_admin=1 + telegram_id NOT NULL 的用户，HTTP 调 sendMessage
 *   --dry-run           — 只 echo 不发 Telegram
 *
 * 关联记录: v20.4 plan §15 (2026-08-28)
 */
class SendWeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    private SendWeeklyReportStub $cmd;

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

    private function makeMailLog(User $user, int $createdAt, ?string $error = null): void
    {
        \DB::table('v2_mail_log')->insert([
            'email' => $user->email,
            'subject' => 'test',
            'template_name' => 'notify',
            'error' => $error,
            'user_id' => $user->id,
            'message_id' => 'msg_' . bin2hex(random_bytes(4)),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeIntent(User $user, string $tag): void
    {
        \DB::table('v2_user_intent')->insert([
            'user_id' => $user->id,
            'tag' => $tag,
            'last_mailed_at' => null,
            'computed_at' => time(),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new SendWeeklyReportStub(new EmailTrackingService());
    }

    public function test_build_report_contains_all_5_tags(): void
    {
        // 当前 v20.4 阶段 UserIntent::ALL_TAGS 有 4 个 tag
        // （v20.2 会加 intent_outdated 第 5 个，预期扩到 5 个）
        $tags = UserIntent::ALL_TAGS;
        $this->assertGreaterThanOrEqual(4, count($tags), '至少有 4 个 tag');

        $now = time();
        foreach ($tags as $tag) {
            $u = $this->makeUser();
            $this->makeIntent($u, $tag);
            $this->makeMailLog($u, $now - 2 * 86400);
        }

        $report = $this->cmd->publicBuildReport(7);

        foreach ($tags as $tag) {
            $this->assertStringContainsString($tag, $report, "周报应包含 tag: {$tag}");
        }

        $this->assertStringContainsString('VP92', $report);
        $this->assertStringContainsString('mailed', $report);
        $this->assertStringContainsString('最佳', $report);
    }

    public function test_build_report_with_no_data_shows_zeros(): void
    {
        // 无任何数据时不应抛异常
        $report = $this->cmd->publicBuildReport(7);

        $this->assertNotEmpty($report);
        $this->assertStringContainsString('mailed', $report);
        foreach (UserIntent::ALL_TAGS as $tag) {
            $this->assertStringContainsString($tag, $report);
        }
    }

    public function test_push_targets_only_admins_with_telegram_id(): void
    {
        // 3 个 admin：2 有 telegram_id，1 无
        $admin1 = $this->makeUser(['is_admin' => 1, 'telegram_id' => 111111]);
        $admin2 = $this->makeUser(['is_admin' => 1, 'telegram_id' => 222222]);
        $adminNoTg = $this->makeUser(['is_admin' => 1, 'telegram_id' => null]);
        // 1 个普通用户（有 telegram_id 但非 admin）
        $user = $this->makeUser(['is_admin' => 0, 'telegram_id' => 999999]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $count = $this->cmd->publicPushToTelegram('test message', 'fake-token');

        // 应该只推 2 个 admin（不算普通用户，不算没 telegram_id 的）
        $this->assertEquals(2, $count);

        Http::assertSentCount(2);

        // 验证推送给的 chat_id 是 admin1/admin2，不是 adminNoTg 或 user
        $sentChatIds = [];
        foreach (Http::recorded() as [$request, $response]) {
            $body = json_decode($request->body(), true);
            $sentChatIds[] = $body['chat_id'];
        }
        $this->assertContains(111111, $sentChatIds);
        $this->assertContains(222222, $sentChatIds);
        $this->assertNotContains(999999, $sentChatIds);
    }

    public function test_dry_run_skips_telegram_calls(): void
    {
        $admin = $this->makeUser(['is_admin' => 1, 'telegram_id' => 111111]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $count = $this->cmd->publicPushToTelegram('test', 'fake-token', dryRun: true);

        // dry-run 不发
        $this->assertEquals(0, $count);
        Http::assertNothingSent();
    }
}

/**
 * 测试替身 — 暴露 buildReport / pushToTelegram 为 public
 * 避免 mock Command 复杂的 handle() 流程
 *
 * 注意：本类与 Tests\Feature\SendWeeklyReportTest 同文件 + 同 namespace，
 * 不需要 use 语句。
 */
class SendWeeklyReportStub
{
    public function __construct(private EmailTrackingService $tracker) {}

    public function publicBuildReport(int $days): string
    {
        return SendWeeklyReportLogic::buildReport($this->tracker, $days);
    }

    public function publicPushToTelegram(string $message, string $token, bool $dryRun = false): int
    {
        return SendWeeklyReportLogic::pushToTelegram($message, $token, $dryRun);
    }
}