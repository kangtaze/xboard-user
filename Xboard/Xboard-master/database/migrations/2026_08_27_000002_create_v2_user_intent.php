<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用途: 创建 v2_user_intent 表，存储按"潜在付费意向"打标签的用户清单
     * 调用方: IntentRefresh 命令每日 cron 重算
     * 被调用方: IntentClassifier service 决定每用户的 tag
     * 关联记录: v19 plan Task 1 (2026-08-27)
     */
    public function up(): void
    {
        Schema::create('v2_user_intent', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('tag', 32);
            $table->integer('last_mailed_at')->nullable();
            $table->integer('computed_at');
            $table->integer('created_at');
            $table->integer('updated_at');

            $table->unique(['user_id', 'tag'], 'uk_user_tag');
            $table->index(['tag', 'last_mailed_at'], 'idx_tag_mailed');
            $table->index('computed_at', 'idx_computed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_user_intent');
    }
};
