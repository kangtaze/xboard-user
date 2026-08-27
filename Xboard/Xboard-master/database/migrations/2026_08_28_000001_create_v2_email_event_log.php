<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2_email_event_log — 邮件事件追踪日志（v20 tracking 基建）
 *
 * 用途:
 * - App\Http\Controllers\TrackController 写 open / click / unsubscribe 三种事件
 * - v20.3 admin-ai Bot funnel_report / open_rate 工具读此表聚合
 * - v20.4 周报自动推送读此表生成上周 funnel
 *
 * 关键决策:
 * - message_id 不暴露 mail_log_id（防遍历）
 * - ip_hash 用 SHA256(ip + secret) 不存明文 IP（GDPR + 减少 PII 存储）
 * - event_type 用 string 而非 enum（SQLite 无原生 enum，跨 DB 兼容）
 *
 * 表前缀: v2_ (项目规范)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_email_event_log', function (Blueprint $table) {
            $table->id();
            $table->integer('mail_log_id');                  // 软引用 v2_mail_log.id
            $table->integer('user_id')->nullable();         // 失败邮件可能没 user
            $table->string('message_id', 128);              // HMAC-safe id

            $table->string('event_type', 16);               // open | click | unsubscribe
            $table->text('target_url')->nullable();         // 仅 click 有
            $table->string('ip_hash', 64)->nullable();      // SHA256(ip + secret)
            $table->string('ua', 255)->nullable();

            $table->integer('occurred_at');
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();

            // 索引 — funnel 查询 hot path
            $table->index('mail_log_id', 'idx_event_mail_log');
            $table->index(['user_id', 'occurred_at'], 'idx_event_user_time');
            $table->index('message_id', 'idx_event_message');
            $table->index(['event_type', 'occurred_at'], 'idx_event_type_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_email_event_log');
    }
};