<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SubscriptionLog — v2_subscription_log 表的 Eloquent 模型
 *
 * 用途:
 * - 记录用户拉取订阅 URL 的客户端/IP/地点
 * - 通过 App\Http\Middleware\LogSubscriptionAccess 写入
 * - 通过 App\Http\Controllers\V2\Admin\UserController::transformUserData 读取展示
 *
 * 关联:
 * - belongsTo User (user_id)
 *
 * 关联文档:
 * - database/migrations/2026_08_27_000001_create_v2_subscription_log.php
 * - docs/session-progress-latest.md (v15.11 章节 — 方案 B1)
 */
class SubscriptionLog extends Model
{
    protected $table = 'v2_subscription_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip',
        'ip_int',
        'user_agent',
        'client_type',
        'client_version',
        'os',
        'country',
        'province',
        'city',
        'isp',
        'node_count',
        'subscription_token',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'integer',
        'updated_at' => 'integer',
        'node_count' => 'integer',
        'ip_int' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
