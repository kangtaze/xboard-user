<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2_mail_log 加 2 列（v20 tracking 基建）
 *
 * - user_id: 用于关联 mailed → user（funnel 计算 paid 转化率）
 * - message_id: 用于 tracking URL 反查 mail_log_id（TrackController 必用）
 *
 * 不加 event 计数列 — funnel 由 v2_email_event_log JOIN 实时算
 * 不加 ip 列 — 用户发送邮件不存 IP（GDPR）
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_mail_log', function (Blueprint $table) {
            $table->integer('user_id')->nullable()->after('id');
            $table->string('message_id', 128)->nullable()->after('user_id');

            $table->index('message_id', 'idx_mail_log_message');
            $table->index(['user_id', 'created_at'], 'idx_mail_log_user_time');
        });
    }

    public function down(): void
    {
        Schema::table('v2_mail_log', function (Blueprint $table) {
            $table->dropIndex('idx_mail_log_message');
            $table->dropIndex('idx_mail_log_user_time');
            $table->dropColumn(['user_id', 'message_id']);
        });
    }
};