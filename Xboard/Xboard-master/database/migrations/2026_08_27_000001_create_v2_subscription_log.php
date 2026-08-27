<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2_subscription_log — 记录每次用户拉取订阅 URL 的行为
 *
 * 用途:
 * - admin 后台用户详情显示「该用户最近拉过订阅的客户端/IP/地点/运营商」
 * - 区分 Xboard 已有的「登录」行为 — 这是「拉订阅」行为(更接近用户真实使用客户端)
 * - 数据来源: App\Http\Middleware\LogSubscriptionAccess
 *
 * 表前缀: v2_ (项目规范, 同其他业务表)
 * 引擎: SQLite (vp92 现网用 SQLite)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('v2_subscription_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index();

            // IP — 同时存字符串 + int (兼容旧代码 long2ip)
            $table->string('ip', 45)->nullable();
            $table->bigInteger('ip_int')->nullable();

            // UA + 客户端指纹解析结果
            $table->string('user_agent', 512)->nullable();
            $table->string('client_type', 32)->nullable();     // shadowrocket / clash / vp92-android / browser / unknown
            $table->string('client_version', 32)->nullable();
            $table->string('os', 64)->nullable();

            // GeoIP (ip2region 反查)
            $table->string('country', 8)->nullable();    // CN
            $table->string('province', 32)->nullable();  // 浙江
            $table->string('city', 32)->nullable();      // 杭州
            $table->string('isp', 32)->nullable();       // 电信

            // 业务字段
            $table->integer('node_count')->default(0);                // 拉到的节点数
            $table->string('subscription_token', 64)->nullable();     // token 掩码 (substr 8 + ***)

            $table->integer('created_at');
            $table->integer('updated_at');

            // 性能: 用户详情「最近 10 次」查询
            $table->index(['user_id', 'created_at']);
            $table->index('subscription_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_subscription_log');
    }
};
