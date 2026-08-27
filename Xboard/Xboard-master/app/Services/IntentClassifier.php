<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserIntent;

/**
 * 用途: 给单个用户分类到 1 个 tag（4 tag 天然互斥），返回 null 表示不需要发邮件
 * 调用方: IntentRefresh 命令每日 cron 重算 + SendIntentCampaign 群发
 * 被调用方: v2_user_intent 表 + User 模型
 * 关联记录: v19 plan Task 3 (2026-08-27)
 */
class IntentClassifier
{
    /**
     * 给单个用户分类到 1 个 tag（4 tag 天然互斥）
     * 返回 null 表示不需要发邮件（已订阅、健康或被排除）
     */
    public static function classify(User $user): ?string
    {
        // 排除条件
        if ($user->banned || $user->is_admin) {
            return null;
        }

        $now = time();

        // 1. expiring_soon: 即将过期 (≤3 天)
        if ($user->plan_id !== null
            && $user->expired_at !== null
            && $user->expired_at > $now
            && $user->expired_at < $now + 3 * 86400) {
            return UserIntent::TAG_EXPIRING_SOON;
        }

        // 2. churned_warm: 已过期 1-7 天
        if ($user->plan_id !== null
            && $user->expired_at !== null
            && $user->expired_at < $now - 86400
            && $user->expired_at > $now - 7 * 86400) {
            return UserIntent::TAG_CHURNED_WARM;
        }

        // 3. intent_high: 新注册 7 天内未付费
        if ($user->plan_id === null
            && $user->created_at !== null
            && $user->created_at > $now - 7 * 86400) {
            return UserIntent::TAG_INTENT_HIGH;
        }

        // 4. intent_cold: 注册 >7 天未付费
        if ($user->plan_id === null
            && $user->created_at !== null
            && $user->created_at < $now - 7 * 86400) {
            return UserIntent::TAG_INTENT_COLD;
        }

        return null;
    }

    /**
     * 给定 tag 返回所有用户 ID 数组
     */
    public static function usersByTag(string $tag): array
    {
        $now = time();

        switch ($tag) {
            case UserIntent::TAG_EXPIRING_SOON:
                return User::where('banned', 0)
                    ->where('is_admin', 0)
                    ->whereNotNull('plan_id')
                    ->whereNotNull('expired_at')
                    ->where('expired_at', '>', $now)
                    ->where('expired_at', '<', $now + 3 * 86400)
                    ->pluck('id')
                    ->toArray();

            case UserIntent::TAG_CHURNED_WARM:
                return User::where('banned', 0)
                    ->where('is_admin', 0)
                    ->whereNotNull('plan_id')
                    ->whereNotNull('expired_at')
                    ->where('expired_at', '<', $now - 86400)
                    ->where('expired_at', '>', $now - 7 * 86400)
                    ->pluck('id')
                    ->toArray();

            case UserIntent::TAG_INTENT_HIGH:
                return User::where('banned', 0)
                    ->where('is_admin', 0)
                    ->whereNull('plan_id')
                    ->where('created_at', '>', $now - 7 * 86400)
                    ->pluck('id')
                    ->toArray();

            case UserIntent::TAG_INTENT_COLD:
                return User::where('banned', 0)
                    ->where('is_admin', 0)
                    ->whereNull('plan_id')
                    ->where('created_at', '<', $now - 7 * 86400)
                    ->pluck('id')
                    ->toArray();

            default:
                return [];
        }
    }
}