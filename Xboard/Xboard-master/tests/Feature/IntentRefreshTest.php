<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserIntent;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IntentRefresh 命令的回归测试
 *
 * 核心覆盖：
 *  - 每个 tag 用户被正确写入
 *  - UNIQUE(user_id, tag) 冲突被 delete-then-insert 模式正确处理
 *  - 连续跑两次 refresh 不抛 SQLSTATE 23000
 *  - last_mailed_at 字段被保留（7 天冷却机制依赖）
 *  - --dry 模式不写库
 *  - 新分类用户 last_mailed_at=null
 */
class IntentRefreshTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 直接创建 user（避免 factory 缺少 plan_id/expired_at/banned/is_admin 字段）
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

    /**
     * 1. 基础：每个 tag 用户被正确写入 v2_user_intent
     */
    public function test_refresh_inserts_users_for_each_tag(): void
    {
        $expiring = $this->makeUser(['plan_id' => 1, 'expired_at' => time() + 86400]);     // expiring_soon
        $churned = $this->makeUser(['plan_id' => 1, 'expired_at' => time() - 3 * 86400]);   // churned_warm
        $outdated = $this->makeUser(['plan_id' => 1, 'expired_at' => time() - 15 * 86400]); // intent_outdated
        $high = $this->makeUser(['plan_id' => null, 'created_at' => time() - 2 * 86400]);   // intent_high
        $cold = $this->makeUser(['plan_id' => null, 'created_at' => time() - 30 * 86400]);  // intent_cold

        Artisan::call('intent:refresh');

        $this->assertDatabaseHas('v2_user_intent', [
            'user_id' => $expiring->id, 'tag' => UserIntent::TAG_EXPIRING_SOON,
        ]);
        $this->assertDatabaseHas('v2_user_intent', [
            'user_id' => $churned->id, 'tag' => UserIntent::TAG_CHURNED_WARM,
        ]);
        $this->assertDatabaseHas('v2_user_intent', [
            'user_id' => $outdated->id, 'tag' => UserIntent::TAG_INTENT_OUTDATED,
        ]);
        $this->assertDatabaseHas('v2_user_intent', [
            'user_id' => $high->id, 'tag' => UserIntent::TAG_INTENT_HIGH,
        ]);
        $this->assertDatabaseHas('v2_user_intent', [
            'user_id' => $cold->id, 'tag' => UserIntent::TAG_INTENT_COLD,
        ]);
    }

    /**
     * 2. 关键：refresh 后保留已有 last_mailed_at（UNIQUE fix 核心保护）
     *
     * 场景：用户上次发邮件 3 天前；今天 refresh 后分类不变（仍是 expiring_soon）
     * 期望：last_mailed_at 必须保留（7 天冷却机制依赖）
     */
    public function test_refresh_preserves_last_mailed_at_when_user_keeps_same_tag(): void
    {
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 86400,  // 1 天后过期 → expiring_soon
        ]);

        // 模拟历史邮件：3 天前发过
        $threeDaysAgo = time() - 3 * 86400;
        DB::table('v2_user_intent')->insert([
            'user_id' => $user->id,
            'tag' => UserIntent::TAG_EXPIRING_SOON,
            'last_mailed_at' => $threeDaysAgo,
            'computed_at' => $threeDaysAgo,
            'created_at' => $threeDaysAgo,
            'updated_at' => $threeDaysAgo,
        ]);

        Artisan::call('intent:refresh');

        $row = DB::table('v2_user_intent')
            ->where('user_id', $user->id)
            ->where('tag', UserIntent::TAG_EXPIRING_SOON)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals($threeDaysAgo, (int) $row->last_mailed_at,
            'last_mailed_at 必须保留（7 天冷却机制依赖）');
    }

    /**
     * 3. 关键：连续跑两次 refresh 不抛 UNIQUE 冲突（防 SQLSTATE 23000）
     *
     * 历史教训：v19 早期实现用 INSERT IGNORE / upsert 时出过 UNIQUE 冲突
     * 修复：delete-then-insert 模式（按 tag 分组）
     */
    public function test_refresh_can_run_twice_without_unique_constraint_violation(): void
    {
        $this->makeUser(['plan_id' => 1, 'expired_at' => time() + 86400]);      // expiring_soon
        $this->makeUser(['plan_id' => 1, 'expired_at' => time() - 3 * 86400]);  // churned_warm
        $this->makeUser(['plan_id' => null, 'created_at' => time() - 30 * 86400]); // intent_cold

        // 第 1 次
        Artisan::call('intent:refresh');
        // 第 2 次（关键：不能因 UNIQUE 冲突而炸）
        Artisan::call('intent:refresh');

        // 每用户每 tag 只有 1 行
        $rows = DB::table('v2_user_intent')->get();
        $this->assertGreaterThan(0, $rows->count());

        // 没有重复 (user_id, tag) 组合
        $duplicates = DB::table('v2_user_intent')
            ->select('user_id', 'tag', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id', 'tag')
            ->having('cnt', '>', 1)
            ->get();
        $this->assertCount(0, $duplicates, 'refresh 后不能存在重复的 (user_id, tag) 组合');
    }

    /**
     * 4. --dry 模式不写库
     */
    public function test_refresh_dry_mode_does_not_write_to_db(): void
    {
        $this->makeUser(['plan_id' => 1, 'expired_at' => time() + 86400]);

        Artisan::call('intent:refresh', ['--dry' => true]);

        $this->assertDatabaseCount('v2_user_intent', 0);
    }

    /**
     * 5. 新分类用户 last_mailed_at 为 null（首次进入表）
     */
    public function test_refresh_new_user_has_null_last_mailed_at(): void
    {
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 86400,  // expiring_soon
        ]);

        // 先确保表是空的
        $this->assertDatabaseCount('v2_user_intent', 0);

        Artisan::call('intent:refresh');

        $row = DB::table('v2_user_intent')
            ->where('user_id', $user->id)
            ->where('tag', UserIntent::TAG_EXPIRING_SOON)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->last_mailed_at,
            '新分类用户首次入库，last_mailed_at 必须为 null（未发过邮件）');
    }

    /**
     * 6. 健康用户不入库（不应被任何 tag 收录）
     */
    public function test_refresh_skips_healthy_active_users(): void
    {
        $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 30 * 86400,  // 30 天后才过期 = 健康
        ]);

        Artisan::call('intent:refresh');

        $this->assertDatabaseCount('v2_user_intent', 0);
    }

    /**
     * 7. 排除用户（banned / is_admin）不入库
     */
    public function test_refresh_skips_banned_and_admin_users(): void
    {
        $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 86400,
            'banned' => 1,
        ]);
        $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 86400,
            'is_admin' => 1,
        ]);

        Artisan::call('intent:refresh');

        $this->assertDatabaseCount('v2_user_intent', 0);
    }
}
