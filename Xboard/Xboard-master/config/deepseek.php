<?php

/**
 * DeepSeek API 配置
 *
 * 3 个 Bot 各自用独立 key:
 * - 客服 Bot (TelegramAiPoll): DEEPSEEK_API_KEY
 * - 管理 Bot (TelegramAdminAiPoll): DEEPSEEK_ADMIN_API_KEY
 * - 卡务 Bot (TelegramKawuPoll): DEEPSEEK_KAWU_API_KEY
 *
 * 历史背景: 2026-08-28 用户提供3 个独立 DeepSeek key；之前 admin-ai / kawu-ai Bot
 * 用 config('deepseek.api_key') 读到 null 一直返回 "Key 未配置"
 */
return [
    'api_key' => env('DEEPSEEK_API_KEY'),
    'api_url' => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
    'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),

    'admin_api_key' => env('DEEPSEEK_ADMIN_API_KEY'),
    'kawu_api_key' => env('DEEPSEEK_KAWU_API_KEY'),
];