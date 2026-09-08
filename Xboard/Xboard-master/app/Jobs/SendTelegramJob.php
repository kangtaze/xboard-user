<?php

/**
 * 用途: Telegram API 异步发送 Job（带 429 限流防御：指数退避 + 随机 jitter sleep）
 * 调用方: TelegramService::sendMessageWithAdmin() (foreach + dispatch)、Telegram bot webhook 内部
 * 被调用方: TelegramService::sendMessage() → Telegram Bot API (api.telegram.org)
 * 关联记录: v36 (2026-09-08) 加 429 限流防御 — $backoff=[3,10,30] + 50-200ms 随机 sleep；原代码无 backoff + 立即 dispatch，3 个 admin 并发触发 HTTP 429 全部失败
 */

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $telegramId;
    protected $text;

    public $tries = 3;
    // v36 修复：429 限流防御 — 第一次重试等 3 秒，第二次 10 秒，第三次 30 秒（指数退避）
    // 仿 SendRenewalMarketing 的"分批 + 间隔"思路，但 Telegram API 限流比 Brevo 邮件宽松
    public $backoff = [3, 10, 30];
    public $timeout = 10;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $telegramId, string $text)
    {
        $this->onQueue('send_telegram');
        $this->telegramId = $telegramId;
        $this->text = $text;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // v36 修复：随机 jitter sleep 50-200ms，避免 sendMessageWithAdmin() 一次性 dispatch 3 个 job
        // 导致 worker 几乎同时并发打 Telegram API（瞬时 3 req 触发限流）。
        // 随机抖动让 3 个 job 错峰执行，同时每次执行都有缓冲。
        usleep(random_int(50000, 200000));

        try {
            $telegramService = new TelegramService();
            $telegramService->sendMessage($this->telegramId, $this->text, 'markdown');
        } catch (Throwable $e) {
            // v36 修复：识别 429 限流场景，记录重试进度（Laravel Job 框架会按 $tries + $backoff 自动重试）
            $errorMsg = $e->getMessage();
            $isRateLimited = str_contains($errorMsg, '429')
                || str_contains($errorMsg, 'Too Many Requests')
                || str_contains($errorMsg, 'retry after');

            if ($isRateLimited) {
                Log::warning('Telegram API 429 限流触发重试', [
                    'telegram_id' => $this->telegramId,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'next_backoff' => $this->backoff[$this->attempts() - 1] ?? 30,
                    'error' => $errorMsg,
                ]);
            } else {
                Log::error('Telegram API 发送失败', [
                    'telegram_id' => $this->telegramId,
                    'attempt' => $this->attempts(),
                    'error' => $errorMsg,
                ]);
            }

            // 重新抛出异常让 Laravel Job 框架按 $tries + $backoff 自动重试
            throw $e;
        }
    }
}