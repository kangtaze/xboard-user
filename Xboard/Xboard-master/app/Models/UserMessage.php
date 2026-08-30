<?php

// 用途：UserMessage 模型，映射 v2_user_message 表
// 调用方：MessageService / MessageController / PushMessageJob
// 被调用方：v2_user_message
// 关联记录：Task #17 PR-B1

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMessage extends Model
{
    protected $table = 'v2_user_message';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];

    // 消息类型常量
    const TYPE_NOTICE = 'notice';   // 管理员公告/维护公告
    const TYPE_TICKET = 'ticket';   // 工单回复
    const TYPE_ORDER = 'order';     // 充值到账/订单完成
    const TYPE_REFUND = 'refund';   // 订单退款

    // tags 常量（用于前端渲染判断）
    const TAG_POPUP = '弹窗';       // 启动时弹底部 sheet
    const TAG_MAINTENANCE = '维护'; // 红色高优横幅
    const TAG_URGENT = '紧急';      // 系统通知高 priority

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'read_at' => 'timestamp',
        'tags' => 'array',
    ];
}