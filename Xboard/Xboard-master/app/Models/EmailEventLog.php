<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 邮件事件日志（v20 tracking 基建）
 *
 * 记录三种事件：open / click / unsubscribe
 * 由 App\Http\Controllers\TrackController 异步写入（路由无 cookie/无 session）
 *
 * 关联：
 * - mail_log_id → v2_mail_log.id（软引用，MailLog 可能被清理）
 * - user_id → v2_user.id（冗余，失败邮件可能没 user）
 * - message_id → v2_mail_log.message_id（HMAC-safe id，非 mail_log_id 本身）
 *
 * @property int $id
 * @property int $mail_log_id
 * @property int|null $user_id
 * @property string $message_id
 * @property string $event_type 'open'|'click'|'unsubscribe'
 * @property string|null $target_url
 * @property string|null $ip_hash SHA256(ip + secret) — 不存明文 IP（GDPR）
 * @property string|null $ua
 * @property int $occurred_at
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class EmailEventLog extends Model
{
    protected $table = 'v2_email_event_log';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'occurred_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public const EVENT_OPEN = 'open';
    public const EVENT_CLICK = 'click';
    public const EVENT_UNSUBSCRIBE = 'unsubscribe';
}