<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserIntent;
use App\Services\IntentClassifier;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentClassifierTest extends TestCase
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

    public function test_expiring_soon_returns_user_within_3_days(): void
    {
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 2 * 86400, // 2 天后过期
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_EXPIRING_SOON, $result);
    }

    public function test_churned_warm_returns_user_expired_1_to_7_days(): void
    {
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 3 * 86400, // 3 天前过期
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_CHURNED_WARM, $result);
    }

    public function test_intent_high_returns_newly_registered_without_plan(): void
    {
        $user = $this->makeUser([
            'plan_id' => null,
            'created_at' => time() - 2 * 86400, // 2 天前注册
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_INTENT_HIGH, $result);
    }

    public function test_intent_cold_returns_old_registered_without_plan(): void
    {
        $user = $this->makeUser([
            'plan_id' => null,
            'created_at' => time() - 30 * 86400, // 30 天前注册
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_INTENT_COLD, $result);
    }

    public function test_excluded_user_returns_null(): void
    {
        $bannedUser = $this->makeUser(['banned' => 1]);
        $adminUser = $this->makeUser(['is_admin' => 1]);

        $this->assertNull(IntentClassifier::classify($bannedUser));
        $this->assertNull(IntentClassifier::classify($adminUser));
    }

    public function test_no_tag_returns_null(): void
    {
        // 健康用户：plan_id 有效且未过期
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() + 30 * 86400,
        ]);

        $this->assertNull(IntentClassifier::classify($user));
    }

    // ===================== v20.2 intent_outdated (过期 7-30 天) =====================

    public function test_intent_outdated_returns_user_expired_7_to_30_days(): void
    {
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 15 * 86400, // 15 天前过期
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_INTENT_OUTDATED, $result);
    }

    public function test_intent_outdated_priority_below_churned_warm(): void
    {
        // 7 天过期（边界） → 应为 churned_warm（更高优先级）
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 7 * 86400 + 60, // 7 天前过期（少 60 秒，让 7 天边界归 churned_warm）
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertEquals(UserIntent::TAG_CHURNED_WARM, $result);
    }

    public function test_intent_outdated_excludes_users_still_active(): void
    {
        // 30 天前过期（边界外） → 不应命中任何 tag
        $user = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 30 * 86400,
        ]);

        $result = IntentClassifier::classify($user);

        $this->assertNull($result);
    }

    public function test_intent_outdated_usersByTag_returns_only_expired_7_to_30_days(): void
    {
        // 过期 5 天 → churned_warm，不应出现
        $warm = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 5 * 86400,
        ]);
        // 过期 15 天 → intent_outdated，应出现
        $outdated = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 15 * 86400,
        ]);
        // 过期 40 天 → null，不应出现
        $old = $this->makeUser([
            'plan_id' => 1,
            'expired_at' => time() - 40 * 86400,
        ]);

        $result = IntentClassifier::usersByTag(UserIntent::TAG_INTENT_OUTDATED);

        $this->assertContains($outdated->id, $result);
        $this->assertNotContains($warm->id, $result);
        $this->assertNotContains($old->id, $result);
    }
}
