<?php

/**
 * DeepSeek API 配置
 *
 * 3 个 Bot + 1 个落地页前端 AI 客服, 各自用独立 key:
 * - 客服 Bot (TelegramAiPoll): DEEPSEEK_API_KEY
 * - 管理 Bot (TelegramAdminAiPoll): DEEPSEEK_ADMIN_API_KEY
 * - 卡务 Bot (TelegramKawuPoll): DEEPSEEK_KAWU_API_KEY
 * - 落地页前端 AI 客服 (AiChatService): DEEPSEEK_FRONTEND_API_KEY (优先) / DEEPSEEK_API_KEY (fallback)
 *
 * 历史背景:
 *   2026-08-28 用户提供 3 个独立 DeepSeek key；之前 admin-ai / kawu-ai Bot
 *   用 config('deepseek.api_key') 读到 null 一直返回 "Key 未配置"。
 *
 *   2026-09-15 v44: 新增 frontend_api_key (前端 AI 客服独立 key 隔离) +
 *   model 默认值 deepseek-v4-flash → deepseek-v4-pro (实测 deepseek-flash 60s timeout)。
 *   关键事实: deepseek /v1/models 枚举只有 2 个 ID: deepseek-flash, deepseek-v4-pro。
 *   deepseek-flash 服务端挂死不返响应 (60s+); deepseek-v4-pro 正常秒回。
 */
return [
    'api_key' => env('DEEPSEEK_API_KEY'),
    'frontend_api_key' => env('DEEPSEEK_FRONTEND_API_KEY'),
    'admin_api_key' => env('DEEPSEEK_ADMIN_API_KEY'),
    'kawu_api_key' => env('DEEPSEEK_KAWU_API_KEY'),

    'api_url' => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
    'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-pro'),
];