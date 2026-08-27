<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * 用途: v20.5 — 向 Telegram Bot API 发送 chat action（如 "typing"），让用户感知 Bot 在工作
 * 调用方: 3 个 Bot 文件 (TelegramAdminAiPoll / TelegramAiPoll / TelegramKawuPoll) 在调 DeepSeek 前调用
 * 被调用方: Telegram sendChatAction HTTP API
 * 关联记录: v20.5 plan §16 (2026-08-28)
 *
 * 关键决策:
 * - 静态方法 + try/catch 失败静默：typing 状态是 UX 增强，失败不阻塞 Bot 主流程
 * - HTTP 200 算成功，其他都算失败（返回 false），便于调用方选择是否重试
 * - action 默认 'typing'（最常用），可传 'upload_photo' 等其他 action
 * - 注意 Telegram typing 状态只持续 5 秒，长时间 DeepSeek 调用需调用方多次触发
 */
class TelegramChatAction
{
    /**
     * 发送 chat action 到指定 chat
     *
     * @param string $botToken Bot Token (从 env 配置读取)
     * @param int|string $chatId 目标 chat id
     * @param string $action chat action，默认 'typing'，可选 'upload_photo' / 'record_video' 等
     * @return bool true=HTTP 2xx 成功；false=失败（HTTP 错误或异常，静默不抛）
     */
    public static function send(string $botToken, int|string $chatId, string $action = 'typing'): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendChatAction";
            $resp = Http::post($url, [
                'chat_id' => $chatId,
                'action' => $action,
            ]);

            return $resp->successful();
        } catch (\Throwable $e) {
            // typing 状态是 UX 增强，失败不阻塞主流程
            return false;
        }
    }
}
