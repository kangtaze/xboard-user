<?php

// 用途：消息中心服务层（push 写入 / getMessages 读取 / markRead 标记已读）
// 调用方：
//   - OrderService::open() — 充值到账推送
//   - TicketService::sendEmailNotify() — 工单回复推送
//   - NoticeController::save() — 公告全员推送（异步 Job）
//   - MessageController — 用户端 fetch/markRead
// 被调用方：App\Models\UserMessage / App\Jobs\PushMessageJob
// 关联记录：Task #17 PR-B1

namespace App\Services;

use App\Jobs\PushMessageJob;
use App\Models\User;
use App\Models\UserMessage;

class MessageService
{
    /**
     * 推送一条消息给单个用户
     */
    public function push(int $userId, string $type, string $title, string $content, ?int $refId = null, ?array $tags = null): UserMessage
    {
        $message = new UserMessage();
        $message->user_id = $userId;
        $message->type = $type;
        $message->title = $title;
        $message->content = $content;
        $message->ref_id = $refId;
        $message->tags = $tags;
        $message->save();
        return $message;
    }

    /**
     * 推送公告给所有用户（异步 Job，admin save 不阻塞）
     */
    public function pushNoticeToAll(string $title, string $content, ?int $refId = null, ?array $tags = null): void
    {
        $userIds = User::pluck('id')->all();
        // 分批 500 用户/Job，避免单次 insert 过大
        foreach (array_chunk($userIds, 500) as $chunk) {
            PushMessageJob::dispatch([
                'user_ids' => $chunk,
                'type' => UserMessage::TYPE_NOTICE,
                'title' => $title,
                'content' => $content,
                'ref_id' => $refId,
                'tags' => $tags,
            ]);
        }
    }

    /**
     * 拉取用户消息
     *
     * @return array{total: int, list: \Illuminate\Database\Eloquent\Collection}
     */
    public function getMessages(int $userId, ?string $type = null, int $page = 1, int $pageSize = 20): array
    {
        $query = UserMessage::where('user_id', $userId)
            ->orderBy('id', 'DESC');
        if ($type) {
            $query->where('type', $type);
        }
        $total = $query->count();
        $list = $query->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();
        return [
            'total' => $total,
            'list' => $list,
        ];
    }

    /**
     * 标记单条已读
     */
    public function markRead(int $userId, int $messageId): bool
    {
        $message = UserMessage::where('id', $messageId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->first();
        if (!$message) {
            return true; // 已读或不存在都算成功
        }
        $message->read_at = time();
        return $message->save();
    }
}