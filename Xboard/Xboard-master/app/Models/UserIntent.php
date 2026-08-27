<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用途: v2_user_intent 表的 Eloquent model，存储按"潜在付费意向"打标签的用户
 * 调用方: IntentClassifier / IntentRefresh / SendIntentCampaign
 * 被调用方: v2_user_intent SQLite 表
 * 关联记录: v19 plan Task 2 (2026-08-27)
 */
class UserIntent extends Model
{
    protected $table = 'v2_user_intent';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'last_mailed_at' => 'timestamp',
        'computed_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public const TAG_EXPIRING_SOON = 'expiring_soon';
    public const TAG_CHURNED_WARM = 'churned_warm';
    public const TAG_INTENT_HIGH = 'intent_high';
    public const TAG_INTENT_COLD = 'intent_cold';
    public const TAG_INTENT_OUTDATED = 'intent_outdated';  // v20.2 (2026-08-28): 过期 7-30 天，中段漏斗用户

    public const ALL_TAGS = [
        self::TAG_EXPIRING_SOON,
        self::TAG_CHURNED_WARM,
        self::TAG_INTENT_HIGH,
        self::TAG_INTENT_COLD,
        self::TAG_INTENT_OUTDATED,
    ];
}