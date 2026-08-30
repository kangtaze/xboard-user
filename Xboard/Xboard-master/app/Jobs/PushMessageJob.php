<?php

// 用途：异步批量推送消息 Job（500 用户/Job，admin save 不阻塞）
// 调用方：MessageService::pushNoticeToAll
// 被调用方：App\Models\UserMessage::insert 批量插入
// 关联记录：Task #17 PR-B1

namespace App\Jobs;

use App\Models\UserMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $params;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(array $params, string $queue = 'push_message')
    {
        $this->onQueue($queue);
        $this->params = $params;
    }

    public function handle(): void
    {
        $now = time();
        $rows = [];
        foreach ($this->params['user_ids'] as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'type' => $this->params['type'],
                'title' => $this->params['title'],
                'content' => $this->params['content'],
                'ref_id' => $this->params['ref_id'] ?? null,
                'tags' => isset($this->params['tags']) ? json_encode($this->params['tags']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        UserMessage::insert($rows);
    }
}