<?php

// 用途：建 v2_user_message 表，统一消息中心（公告/工单回复/充值到账/订单状态）
// 调用方：MessageService::push / MessageService::getMessages / MessageController::fetch
// 被调用方：v2_user_message（SQLite / MySQL 双兼容）
// 关联记录：Task #17 PR-B1

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_user_message', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index('idx_user_message_user');
            $table->string('type', 32)->index('idx_user_message_type'); // notice/ticket/order/refund
            $table->string('title', 255);
            $table->text('content');
            $table->integer('ref_id')->nullable(); // 关联订单/工单/公告 id
            $table->string('tags', 255)->nullable(); // JSON 数组：弹窗/维护/紧急
            $table->integer('read_at')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
            $table->index(['user_id', 'read_at'], 'idx_user_message_unread');
            $table->index(['user_id', 'type', 'created_at'], 'idx_user_message_type_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_user_message');
    }
};