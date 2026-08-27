<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2_user 加 2 列（v20 tracking 基建 — 退订管理）
 *
 * - email_unsubscribed_at: 退订时间戳（null = 未退订，>0 = 已退订）
 * - email_unsubscribed_reason: 'manual' | 'unsubscribe_link'
 *
 * SendIntentCampaign 发邮件前过滤：
 *   WHERE email_unsubscribed_at IS NULL
 *
 * 不删除 user — 保留订阅和历史，退订只是不再发营销邮件
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->integer('email_unsubscribed_at')->nullable()->after('commission_auto_check');
            $table->string('email_unsubscribed_reason', 32)->nullable()->after('email_unsubscribed_at');

            $table->index('email_unsubscribed_at', 'idx_user_unsubscribed');
        });
    }

    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropIndex('idx_user_unsubscribed');
            $table->dropColumn(['email_unsubscribed_at', 'email_unsubscribed_reason']);
        });
    }
};