<?php

namespace App\Console\Commands;

use App\Models\InviteCode;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 管理员 AI Bot — 独立 Long Polling 守护进程
 *
 * 专用管理员 Bot，使用 DeepSeek Function Calling 执行后台操作。
 * 与用户 Bot (TelegramAiPoll) 完全独立。
 *
 * Bot Token 通过环境变量 TELEGRAM_ADMIN_BOT_TOKEN 配置
 */
class TelegramAdminAiPoll extends Command
{
    protected $signature = 'telegram:admin-ai-poll';
    protected $description = '管理员 AI Bot — DeepSeek Function Calling Long Polling';

    private string $botToken;
    private string $apiUrl;
    private int $offset = 0;
    private const LOG_FILE = '/tmp/admin-ai-poll.log';

    // 管理员 telegram_id 白名单（从 DB 读取，启动时缓存）
    private array $adminTelegramIds = [];

    // 员工 telegram_id => user_id 映射（从 DB 读取，启动时缓存）
    private array $staffTelegramMap = [];

    // 当前消息发送者的角色和用户ID（processUpdate 中设置，传递给 tool）
    private string $currentRole = ''; // 'admin' | 'staff'
    private int $currentStaffUserId = 0; // 员工的 v2_user.id，用于邀请人过滤

    public function handle(): int
    {
        $this->botToken = env('TELEGRAM_ADMIN_BOT_TOKEN', '');

        if (empty($this->botToken)) {
            $this->error('TELEGRAM_ADMIN_BOT_TOKEN 未配置');
            return 1;
        }

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";

        // 加载管理员和员工列表
        $this->loadAdminIds();
        $this->loadStaffIds();

        // 删除 webhook，使用 long polling
        $this->deleteWebhook();

        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, fn() => $this->stop());
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_async_signals(true);
        }

        $this->info("管理员 AI Bot 已启动 (@" . $this->getBotUsername() . ")");

        while (true) {
            try {
                $this->pollUpdates();
            } catch (\Throwable $e) {
                Log::error('管理员 Bot poll 失败', ['error' => $e->getMessage()]);
                $this->warn("Poll 错误: {$e->getMessage()}，5秒后重试...");
                sleep(5);
            }
        }
    }

    private function loadAdminIds(): void
    {
        $admins = User::where('is_admin', 1)
            ->where('telegram_id', '!=', null)
            ->pluck('telegram_id')
            ->toArray();

        $this->adminTelegramIds = array_map('intval', $admins);
        $this->info("已加载 " . count($this->adminTelegramIds) . " 个管理员 ID: " . implode(', ', $this->adminTelegramIds));
    }

    private function loadStaffIds(): void
    {
        $staffs = User::where('is_staff', 1)
            ->where('telegram_id', '!=', null)
            ->get();

        $this->staffTelegramMap = [];
        foreach ($staffs as $s) {
            $this->staffTelegramMap[(int) $s->telegram_id] = (int) $s->id;
        }

        $this->info("已加载 " . count($this->staffTelegramMap) . " 个员工 ID: " . implode(', ', array_keys($this->staffTelegramMap)));
    }

    private function reloadStaffIds(): void
    {
        $this->loadStaffIds();
    }

    private function getBotUsername(): string
    {
        try {
            $resp = Http::timeout(5)->get($this->apiUrl . 'getMe');
            $data = $resp->json();
            return $data['result']['username'] ?? 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function deleteWebhook(): void
    {
        try {
            Http::timeout(10)->get($this->apiUrl . 'deleteWebhook');
            $this->info('Webhook 已删除');
        } catch (\Throwable $e) {
            $this->warn("删除 Webhook 失败: {$e->getMessage()}");
        }
    }

    private function pollUpdates(): void
    {
        $resp = Http::timeout(60)->get($this->apiUrl . 'getUpdates', [
            'offset' => $this->offset,
            'timeout' => 30,
            'allowed_updates' => json_encode(['message', 'chat_member']),
        ]);

        if (!$resp->successful()) {
            $this->warn("getUpdates 失败: HTTP {$resp->status()}");
            return;
        }

        $data = $resp->json();
        $updates = $data['result'] ?? [];

        foreach ($updates as $update) {
            $this->offset = $update['update_id'] + 1;
            $this->processUpdate($update);
        }
    }

    private function processUpdate(array $update): void
    {
        // 频道新成员事件
        if (isset($update['chat_member'])) {
            $this->handleChannelJoin($update);
            return;
        }

        $message = $update['message'] ?? null;
        if (!$message) return;

        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chat || !$from) return;

        $chatId = (int) $chat['id'];
        $userId = (int) $from['id'];
        $isPrivate = ($chat['type'] ?? '') === 'private';

        // 只处理私聊
        if (!$isPrivate) return;

        // 判断角色：管理员 > 员工 > 未知
        $isAdmin = in_array($userId, $this->adminTelegramIds);
        $isStaff = isset($this->staffTelegramMap[$userId]);

        if ($isAdmin) {
            $this->currentRole = 'admin';
        } elseif ($isStaff) {
            $this->currentRole = 'staff';
            $this->currentStaffUserId = $this->staffTelegramMap[$userId];
        } else {
            // 未知用户 — 尝试员工绑定流程
            $this->handleStaffBinding($chatId, $userId, $text);
            return;
        }

        $roleLabel = $this->currentRole === 'admin' ? '管理员' : '员工';
        $this->info("[{$userId}] {$roleLabel}消息: {$text}");

        // /start 命令
        if ($text === '/start') {
            if ($this->currentRole === 'admin') {
                $this->sendTelegram($chatId, "👨‍💼 管理员 AI 助手已就绪。\n\n您可以直接输入命令，如：\n- 查看 kingze@gmail.com 的信息\n- 今天有多少新用户\n- 节点状态\n- 封禁 xxx@gmail.com\n\n我会自动调用后台工具执行操作。");
            } else {
                $this->sendTelegram($chatId, "👤 员工 AI 助手已就绪。\n\n您可以查询：\n- 兑换码使用情况（是否被使用、谁使用的）\n- 您邀请的用户信息（余额、流量、套餐等）\n\n直接输入问题即可，例如：\n- 查兑换码 XXXXXX\n- 查看我邀请的用户");
            }
            return;
        }

        try {
            $reply = $this->adminAiChat($text);
            $this->sendTelegram($chatId, $reply);
            $this->info("[{$userId}] 回复已发送");
        } catch (\Throwable $e) {
            Log::error("{$roleLabel} AI 处理失败", ['error' => $e->getMessage()]);
            try {
                $this->sendTelegram($chatId, '抱歉，处理时出错，请稍后再试。');
            } catch (\Throwable) {}
        }
    }

    // ========================================================
    // 员工绑定流程
    // ========================================================

    private function handleStaffBinding(int $chatId, int $telegramId, string $text): void
    {
        // 检查是否是邮箱格式
        if (!str_contains($text, '@') || !filter_var(trim($text), FILTER_VALIDATE_EMAIL)) {
            $this->sendTelegram($chatId, "👋 您好，此机器人仅供管理员和员工使用。\n\n如果您是员工，请发送您的注册邮箱进行身份验证。");
            return;
        }

        $email = strtolower(trim($text));

        // 查找该邮箱是否为员工账号
        $staff = User::where('email', $email)->where('is_staff', 1)->first();

        if (!$staff) {
            $this->warn("[{$telegramId}] 绑定失败: {$email} 不是员工账号");
            $this->sendTelegram($chatId, "❌ 邮箱 {$email} 不是员工账号，无法绑定。");
            return;
        }

        // 绑定 telegram_id
        $staff->update(['telegram_id' => $telegramId]);
        $this->info("[{$telegramId}] 员工绑定成功: {$email} (user_id={$staff->id})");

        // 刷新员工缓存
        $this->reloadStaffIds();

        $this->sendTelegram($chatId, "✅ 员工身份验证成功！\n\n邮箱: {$email}\n\n您现在可以使用员工 AI 助手查询兑换码和您邀请的用户信息了。发送 /start 查看可用功能。");
    }

    private function sendTelegram(int $chatId, string $text, string $parseMode = ''): void
    {
        // 清理 AI 回复中的 Markdown 语法，避免 Telegram 显示 **
        if (empty($parseMode)) {
            $text = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $text);
            $text = preg_replace('/`([^`]+)`/', '$1', $text);
            $text = str_replace('```', '', $text);
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        try {
            Http::timeout(10)->post($this->apiUrl . 'sendMessage', $params);
        } catch (\Throwable $e) {
            Log::error('管理员 Bot 发送消息失败', ['error' => $e->getMessage()]);
        }
    }

    // ========================================================
    // DeepSeek Function Calling AI
    // ========================================================

    private function adminAiChat(string $message): string
    {
        $apiKey = config('deepseek.api_key');
        $apiUrl = config('deepseek.api_url', 'https://api.deepseek.com/v1/chat/completions');

        if (empty($apiKey)) {
            return 'DeepSeek API Key 未配置。';
        }

        $tools = $this->currentRole === 'staff' ? $this->getStaffTools() : $this->getAdminTools();

        if ($this->currentRole === 'staff') {
            $systemPrompt = <<<'PROMPT'
你是 VP92 极速加速器的员工 AI 助手。你可以通过工具函数查询信息。
你的权限有限，只能查询以下内容：
1. 兑换码使用情况（是否已使用、被哪个客户使用了）
2. 您邀请的用户的详细信息（余额、流量、套餐、到期时间等）
- 邮箱地址全部转小写处理
- 流量单位用 GB/MB/KB 自动转换
- 金额单位用元，余额字段是分（除以100）
- 时间戳转换为可读日期格式
- 如果查询的用户不是您邀请的，请告知无权限
回答简洁专业，给出具体数字。回答用中文。
重要：不要使用任何 Markdown 格式，不要用 ** 加粗，不要用 ` 代码，用纯文本和 emoji 表达。
PROMPT;
        } else {
            $systemPrompt = <<<'PROMPT'
你是 VP92 极速加速器的管理员 AI 助手。你可以通过工具函数查询数据和执行管理操作。
当管理员需要查看数据或执行操作时，自动调用对应的工具函数。
- 邮箱地址全部转小写处理
- 流量单位用 GB/MB/KB 自动转换
- 金额单位用元，余额字段是分（除以100）
- 时间戳转换为可读日期格式
回答简洁专业，给出具体数字。不要说"建议登录后台查看"。回答用中文。
重要：不要使用任何 Markdown 格式，不要用 ** 加粗，不要用 ` 代码，用纯文本和 emoji 表达。
PROMPT;
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        try {
            // 最多 3 轮 tool calling
            for ($round = 0; $round < 3; $round++) {
                $response = Http::timeout(60)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post($apiUrl, [
                    'model' => 'deepseek-v4-flash',
                    'messages' => $messages,
                    'tools' => $tools,
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                ]);

                if (!$response->successful()) {
                    Log::error('Admin AI API 失败', ['status' => $response->status(), 'body' => $response->body()]);
                    return "AI API 调用失败 (HTTP {$response->status()})，请稍后再试。";
                }

                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];
                $finishReason = $data['choices'][0]['finish_reason'] ?? '';

                // 添加 assistant 消息到历史
                $messages[] = $choice;

                // 如果没有 tool_calls，直接返回文本回复
                if (empty($choice['tool_calls'])) {
                    $content = $choice['content'] ?? '';
                    if (empty($content) && !empty($choice['reasoning_content'])) {
                        $content = $choice['reasoning_content'];
                    }
                    return $content ?: '无法获取回复。';
                }

                // 执行所有 tool_calls
                foreach ($choice['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'];

                    $this->info("  🔧 调用工具: {$functionName}(" . json_encode($arguments, JSON_UNESCAPED_UNICODE) . ")");

                    $result = $this->executeAdminTool($functionName, $arguments);

                    $this->info("  ✅ 工具结果: " . mb_substr($result, 0, 100));

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => $result,
                    ];
                }
            }

            // 3 轮后获取最终回复
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'model' => 'deepseek-v4-flash',
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 4000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];
                $content = $choice['content'] ?? '';
                if (empty($content) && !empty($choice['reasoning_content'])) {
                    $content = $choice['reasoning_content'];
                }
                return $content ?: '无法获取回复。';
            }

            return 'AI 服务暂时不可用，请稍后再试。';
        } catch (\Throwable $e) {
            Log::error('Admin AI chat 异常', ['error' => $e->getMessage()]);
            return "AI 出错: {$e->getMessage()}";
        }
    }

    // ========================================================
    // Tool 定义 + 执行
    // ========================================================

    private function getAdminTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_user',
                    'description' => '查询用户详细信息，包括套餐、流量、到期时间、状态等',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ban_user',
                    'description' => '封禁用户，使其无法连接服务',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'unban_user',
                    'description' => '解封用户，恢复其服务',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_user',
                    'description' => '更新用户字段，支持套餐、流量、到期时间、余额、限速、设备限制、密码',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                            'field' => [
                                'type' => 'string',
                                'enum' => ['plan_id', 'transfer_enable', 'expired_at', 'balance', 'speed_limit', 'device_limit', 'password'],
                                'description' => '要更新的字段名',
                            ],
                            'value' => ['type' => 'string', 'description' => '新值（plan_id传套餐ID，transfer_enable传如100GB，expired_at传日期如2026-08-01，balance传元如10，speed_limit传Mbps如100，device_limit传整数如3，password传明文密码）'],
                        ],
                        'required' => ['email', 'field', 'value'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'reset_traffic',
                    'description' => '重置用户已用流量（上传和下载归零）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_order',
                    'description' => '查询订单详情，可按订单号或用户邮箱查询',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'identifier' => ['type' => 'string', 'description' => '订单号trade_no或用户邮箱'],
                        ],
                        'required' => ['identifier'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_orders',
                    'description' => '列出订单，可按用户或时间范围过滤',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱（可选）'],
                            'days' => ['type' => 'integer', 'description' => '最近N天的订单（可选）'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancel_order',
                    'description' => '取消待支付订单',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'trade_no' => ['type' => 'string', 'description' => '订单号'],
                        ],
                        'required' => ['trade_no'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'mark_paid',
                    'description' => '手动标记订单为已支付（完成开通）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'trade_no' => ['type' => 'string', 'description' => '订单号'],
                        ],
                        'required' => ['trade_no'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_code',
                    'description' => '查询兑换码/邀请码详情',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'description' => '兑换码'],
                        ],
                        'required' => ['code'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'user_traffic',
                    'description' => '查看用户详细流量使用情况，包括上传、下载、剩余、百分比',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'node_status',
                    'description' => '查看所有节点在线状态',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'system_status',
                    'description' => '查看服务器资源状态（CPU、内存、磁盘、负载）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'user_stats',
                    'description' => '查看用户统计数据（总数、活跃、过期、封禁等）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'today_orders',
                    'description' => '查看今日订单统计。返回所有订单数量、待支付数量、已取消数量、已完成数量和已完成金额。completed_amount才是实际收入，回答时只报completed_amount。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'today_new_users',
                    'description' => '查看今日新注册用户数量',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_announcement',
                    'description' => '发送商业级公告到 Telegram 频道。支持图片+按钮+精美排版。管理员说"发公告/发通知/推广"时调用。自动生成商业化排版。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => '公告标题，如"系统维护通知"、"新品上线"'],
                            'content' => ['type' => 'string', 'description' => '公告正文内容'],
                            'image_url' => ['type' => 'string', 'description' => '配图URL（可选，如官网截图、宣传图）'],
                            'button_text' => ['type' => 'string', 'description' => '按钮文字，如"立即访问"、"立即购买"（可选）'],
                            'button_url' => ['type' => 'string', 'description' => '按钮跳转链接（可选，需要与button_text同时提供）'],
                            'pin' => ['type' => 'boolean', 'description' => '是否置顶，默认false'],
                        ],
                        'required' => ['title', 'content'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_renewal_email',
                    'description' => '给单个用户发送续费促销邮件（VIP限时特惠，官方商业模板）。管理员说"给xxx发续费邮件/发优惠邮件"时调用。每次只发一封。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
        ];
    }

    private function getStaffTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_code',
                    'description' => '查询兑换码详情，包括是否已使用、被哪个客户使用了',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'description' => '兑换码'],
                        ],
                        'required' => ['code'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'staff_lookup_user',
                    'description' => '查询您邀请的用户详细信息（余额、流量、套餐、到期时间等），只能查询您自己邀请的用户',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'staff_user_traffic',
                    'description' => '查看您邀请的用户流量使用情况，只能查询您自己邀请的用户',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string', 'description' => '用户邮箱'],
                        ],
                        'required' => ['email'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'staff_my_invites',
                    'description' => '查看您邀请的所有用户列表',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
        ];
    }

    private function executeAdminTool(string $name, array $args): string
    {
        try {
            // 员工工具
            if ($this->currentRole === 'staff') {
                return match ($name) {
                    'lookup_code' => $this->toolLookupCode($args['code'] ?? ''),
                    'staff_lookup_user' => $this->toolStaffLookupUser($args['email'] ?? ''),
                    'staff_user_traffic' => $this->toolStaffUserTraffic($args['email'] ?? ''),
                    'staff_my_invites' => $this->toolStaffMyInvites(),
                    default => "员工无权使用工具: {$name}",
                };
            }

            // 管理员工具（全部）
            return match ($name) {
                'lookup_user' => $this->toolLookupUser($args['email'] ?? ''),
                'ban_user' => $this->toolBanUser($args['email'] ?? ''),
                'unban_user' => $this->toolUnbanUser($args['email'] ?? ''),
                'update_user' => $this->toolUpdateUser($args['email'] ?? '', $args['field'] ?? '', $args['value'] ?? ''),
                'reset_traffic' => $this->toolResetTraffic($args['email'] ?? ''),
                'lookup_order' => $this->toolLookupOrder($args['identifier'] ?? ''),
                'list_orders' => $this->toolListOrders($args['email'] ?? null, $args['days'] ?? null),
                'cancel_order' => $this->toolCancelOrder($args['trade_no'] ?? ''),
                'mark_paid' => $this->toolMarkPaid($args['trade_no'] ?? ''),
                'lookup_code' => $this->toolLookupCode($args['code'] ?? ''),
                'user_traffic' => $this->toolUserTraffic($args['email'] ?? ''),
                'node_status' => $this->toolNodeStatus(),
                'system_status' => $this->toolSystemStatus(),
                'user_stats' => $this->toolUserStats(),
                'today_orders' => $this->toolTodayOrders(),
                'today_new_users' => $this->toolTodayNewUsers(),
                'send_announcement' => $this->toolSendAnnouncement($args),
                'send_renewal_email' => $this->toolSendRenewalEmail($args['email'] ?? ''),
                default => "未知工具函数: {$name}",
            };
        } catch (\Throwable $e) {
            Log::error("Admin tool {$name} 执行失败", ['error' => $e->getMessage()]);
            return "工具执行出错: {$e->getMessage()}";
        }
    }

    // ========================================================
    // Tool 函数实现
    // ========================================================

    private function formatTraffic(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    private function parseTrafficToBytes(string $value): ?int
    {
        $value = strtoupper(trim($value));
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(TB|GB|MB|KB|B)?$/', $value, $m)) return null;
        $num = (float) $m[1];
        return match ($m[2] ?? 'B') {
            'TB' => (int) ($num * 1099511627776),
            'GB' => (int) ($num * 1073741824),
            'MB' => (int) ($num * 1048576),
            'KB' => (int) ($num * 1024),
            default => (int) $num,
        };
    }

    private function toolLookupUser(string $email): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";

        $plan = $user->plan ? $user->plan->name : '无套餐';
        $status = $user->banned ? '已封禁' : ($user->isActive() ? '活跃' : '已过期');
        $expired = $user->expired_at ? date('Y-m-d H:i', $user->expired_at) : '永不过期';
        $usedTraffic = $this->formatTraffic($user->getTotalUsedTraffic());
        $totalTraffic = $this->formatTraffic($user->transfer_enable ?? 0);
        $tgId = $user->telegram_id ?: '未绑定';
        $lastLogin = $user->last_login_at ? date('Y-m-d H:i', $user->last_login_at) : '从未登录';
        $balance = ($user->balance ?? 0) / 100;

        return json_encode([
            'email' => $user->email, 'status' => $status, 'plan' => $plan,
            'expired_at' => $expired, 'used_traffic' => $usedTraffic,
            'total_traffic' => $totalTraffic, 'balance' => "{$balance}元",
            'telegram_id' => $tgId, 'last_login' => $lastLogin,
            'created_at' => date('Y-m-d H:i', $user->created_at),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolBanUser(string $email): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";
        if ($user->banned) return "用户 {$email} 已处于封禁状态。";
        $user->update(['banned' => true]);
        Cache::forget("user:{$user->id}:token");
        return "用户 {$email} 已封禁。";
    }

    private function toolUnbanUser(string $email): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";
        if (!$user->banned) return "用户 {$email} 未被封禁。";
        $user->update(['banned' => false]);
        return "用户 {$email} 已解封。";
    }

    private function toolUpdateUser(string $email, string $field, string $value): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";

        $updateData = [];
        $displayValue = $value;

        switch ($field) {
            case 'plan_id':
                $plan = Plan::find((int) $value);
                if (!$plan) return "未找到套餐 ID: {$value}";
                $updateData['plan_id'] = $plan->id;
                $displayValue = $plan->name;
                break;
            case 'transfer_enable':
                $bytes = $this->parseTrafficToBytes($value);
                if ($bytes === null) return "无法解析流量值: {$value}，请使用如 100GB、500MB 格式";
                $updateData['transfer_enable'] = $bytes;
                $displayValue = $this->formatTraffic($bytes);
                break;
            case 'expired_at':
                $timestamp = strtotime($value);
                if ($timestamp === false) return "无法解析日期: {$value}";
                $updateData['expired_at'] = $timestamp;
                $displayValue = date('Y-m-d H:i', $timestamp);
                break;
            case 'balance':
                $yuan = (float) $value;
                $updateData['balance'] = (int) round($yuan * 100);
                $displayValue = "{$yuan}元";
                break;
            case 'speed_limit':
                $updateData['speed_limit'] = (int) $value;
                $displayValue = "{$value} Mbps";
                break;
            case 'device_limit':
                $updateData['device_limit'] = (int) $value;
                $displayValue = "{$value}台";
                break;
            case 'password':
                $updateData['password'] = password_hash($value, PASSWORD_DEFAULT);
                $displayValue = '已更新（明文不显示）';
                break;
            default:
                return "不支持的字段: {$field}";
        }

        $user->update($updateData);
        return "用户 {$email} 的 {$field} 已更新为 {$displayValue}。";
    }

    private function toolResetTraffic(string $email): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";
        $beforeUpload = $this->formatTraffic($user->u ?? 0);
        $beforeDownload = $this->formatTraffic($user->d ?? 0);
        $user->update(['u' => 0, 'd' => 0]);
        return "用户 {$email} 流量已重置（上传 {$beforeUpload}，下载 {$beforeDownload} -> 0）。";
    }

    private function toolLookupOrder(string $identifier): string
    {
        $identifier = trim($identifier);
        if (preg_match('/^[A-Za-z0-9]{10,}$/', $identifier)) {
            $order = Order::where('trade_no', $identifier)->first();
            return $order ? $this->formatOrder($order) : "未找到订单号 {$identifier}。";
        }
        $email = strtolower($identifier);
        $order = Order::with('user', 'plan')
            ->whereHas('user', fn($q) => $q->where('email', $email))
            ->orderBy('id', 'desc')->first();
        return $order ? $this->formatOrder($order) : "未找到用户 {$email} 的订单。";
    }

    private function toolListOrders(?string $email, ?int $days): string
    {
        $query = Order::with('user', 'plan')->orderBy('id', 'desc');
        if ($email) {
            $email = strtolower($email);
            $query->whereHas('user', fn($q) => $q->where('email', $email));
        }
        if ($days !== null && $days > 0) {
            $query->where('created_at', '>=', time() - ($days * 86400));
        }
        $orders = $query->limit(20)->get();
        if ($orders->isEmpty()) return '未找到符合条件的订单。';
        $lines = [];
        foreach ($orders as $order) $lines[] = $this->formatOrderOneLine($order);
        return implode("\n", $lines);
    }

    private function toolCancelOrder(string $tradeNo): string
    {
        $tradeNo = trim($tradeNo);
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) return "未找到订单号 {$tradeNo}。";
        $statusMap = [0 => '待支付', 1 => '开通中', 2 => '已取消', 3 => '已完成'];
        if ($order->status != 0) {
            $statusText = $statusMap[$order->status] ?? $order->status;
            return "订单 {$tradeNo} 当前状态为 {$statusText}，无法取消。";
        }
        $order->update(['status' => 2]);
        return "订单 {$tradeNo} 已取消。";
    }

    private function toolMarkPaid(string $tradeNo): string
    {
        $tradeNo = trim($tradeNo);
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) return "未找到订单号 {$tradeNo}。";
        $statusMap = [0 => '待支付', 1 => '开通中', 2 => '已取消', 3 => '已完成'];
        if ($order->status != 0) {
            $statusText = $statusMap[$order->status] ?? $order->status;
            return "订单 {$tradeNo} 当前状态为 {$statusText}，无法标记为已支付。";
        }
        $order->update(['status' => 3]);
        $amount = $order->total_amount / 100;
        return "订单 {$tradeNo}（{$amount}元）已标记为已支付。";
    }

    private function toolLookupCode(string $code): string
    {
        $code = strtoupper(trim($code));

        // 先查礼品卡码表
        $giftCode = \App\Models\GiftCardCode::where('code', $code)->first();
        if ($giftCode) {
            $statusMap = [0 => '未使用', 1 => '已使用', 2 => '已禁用'];
            $usedBy = null;
            $usedAt = null;
            if ($giftCode->status === 1 && $giftCode->user_id) {
                $usedUser = User::find($giftCode->user_id);
                $usedBy = $usedUser ? $usedUser->email : "用户ID:{$giftCode->user_id}";
                $usedAt = $giftCode->used_at ? date('Y-m-d H:i', $giftCode->used_at) : '未知';
            }
            $expiresAt = $giftCode->expires_at ? date('Y-m-d H:i', $giftCode->expires_at) : '永不过期';
            $result = [
                'code' => $code,
                'type' => '礼品卡兑换码',
                'status' => $statusMap[$giftCode->status] ?? "未知({$giftCode->status})",
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i', $giftCode->created_at),
            ];
            if ($usedBy) {
                $result['used_by'] = $usedBy;
                $result['used_at'] = $usedAt;
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE);
        }

        // 再查邀请码表
        $invite = InviteCode::where('code', $code)->first();
        if (!$invite) return "未找到兑换码 {$code}。";
        $creator = User::find($invite->user_id);
        $statusMap = [0 => '未使用', 2 => '已使用'];
        $result = [
            'code' => $code,
            'type' => '邀请码',
            'status' => $statusMap[$invite->status] ?? '未知',
            'pv' => $invite->pv, 'creator' => $creator?->email ?? '未知',
            'created_at' => date('Y-m-d H:i', $invite->created_at),
        ];
        if ($invite->status === 2) {
            $usedBy = User::where('invite_user_id', $invite->user_id)
                ->where('created_at', '>=', $invite->created_at)->orderBy('id', 'desc')->first();
            if ($usedBy) {
                $result['used_by'] = $usedBy->email;
                $result['used_at'] = date('Y-m-d H:i', $usedBy->created_at);
            }
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function toolUserTraffic(string $email): string
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return "未找到邮箱 {$email} 对应的用户。";
        $upload = $user->u ?? 0;
        $download = $user->d ?? 0;
        $totalUsed = $upload + $download;
        $totalEnabled = $user->transfer_enable ?? 0;
        $remaining = max(0, $totalEnabled - $totalUsed);
        $percentage = $totalEnabled > 0 ? round(($totalUsed / $totalEnabled) * 100, 1) : 0;
        return json_encode([
            'email' => $user->email, 'upload' => $this->formatTraffic($upload),
            'download' => $this->formatTraffic($download),
            'total_used' => $this->formatTraffic($totalUsed),
            'total_enabled' => $this->formatTraffic($totalEnabled),
            'remaining' => $this->formatTraffic($remaining),
            'used_percentage' => "{$percentage}%",
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolNodeStatus(): string
    {
        $servers = Server::where('type', '!=', 'edgetunnel')->get();
        if ($servers->isEmpty()) return '未找到节点数据。';
        $lines = [];
        foreach ($servers as $server) {
            $lastCheck = $server->last_check_at ? date('Y-m-d H:i', $server->last_check_at) : '从未检查';
            $status = ($server->is_online ?? false) ? '在线' : '离线';
            $lines[] = "{$server->name} [{$server->type}] - {$status}, 连接数: " . ($server->online_count ?? 0) . ", 最后检查: {$lastCheck}";
        }
        return implode("\n", $lines);
    }

    private function toolSystemStatus(): string
    {
        try {
            $uptime = trim(shell_exec('uptime -p 2>/dev/null') ?: 'N/A');
            $memInfo = trim(shell_exec('free -h 2>/dev/null | grep Mem') ?: 'N/A');
            $diskInfo = trim(shell_exec('df -h / 2>/dev/null | tail -1') ?: 'N/A');
            $loadAvg = trim(shell_exec('cat /proc/loadavg 2>/dev/null | cut -d" " -f1-3') ?: 'N/A');
        } catch (\Throwable) {
            $uptime = $memInfo = $diskInfo = $loadAvg = '无法获取';
        }
        $userCount = User::count();
        $activeCount = User::where('plan_id', '!=', null)->where('banned', 0)
            ->where(fn($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', time()))->count();
        return json_encode([
            'uptime' => $uptime, 'load' => $loadAvg, 'memory' => $memInfo,
            'disk' => $diskInfo, 'total_users' => $userCount, 'active_users' => $activeCount,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolUserStats(): string
    {
        $total = User::count();
        $active = User::where('plan_id', '!=', null)->where('banned', 0)
            ->where(fn($q) => $q->whereNull('expired_at')->orWhere('expired_at', '>', time()))->count();
        $expired = User::where('plan_id', '!=', null)->where('banned', 0)
            ->where('expired_at', '!=', null)->where('expired_at', '<=', time())->count();
        $banned = User::where('banned', 1)->count();
        $noPlan = User::where('plan_id', null)->count();
        $tgBound = User::where('telegram_id', '!=', null)->count();
        return json_encode([
            'total' => $total, 'active' => $active, 'expired' => $expired,
            'banned' => $banned, 'no_plan' => $noPlan, 'telegram_bound' => $tgBound,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolTodayOrders(): string
    {
        $todayStart = strtotime('today');
        $totalCount = Order::where('created_at', '>=', $todayStart)->count();
        $pending = Order::where('created_at', '>=', $todayStart)->where('status', 0)->count();
        $cancelled = Order::where('created_at', '>=', $todayStart)->where('status', 2)->count();
        $completed = Order::where('created_at', '>=', $todayStart)->where('status', 3)->count();
        $completedAmount = Order::where('created_at', '>=', $todayStart)->where('status', 3)->sum('total_amount') / 100;
        return json_encode([
            'total_count' => $totalCount,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'completed' => $completed,
            'completed_amount' => "{$completedAmount}元",
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolTodayNewUsers(): string
    {
        $count = User::where('created_at', '>=', strtotime('today'))->count();
        return json_encode(['today_new_users' => $count], JSON_UNESCAPED_UNICODE);
    }

    private function toolSendAnnouncement(array $args): string
    {
        $title = trim($args['title'] ?? '');
        $content = trim($args['content'] ?? '');
        if (empty($title) || empty($content)) return '标题和内容不能为空';

        $channelId = env('TELEGRAM_CHANNEL_ID', '');
        if (empty($channelId)) return '未配置 TELEGRAM_CHANNEL_ID，无法发送';

        $imageUrl = trim($args['image_url'] ?? '');
        $buttonText = trim($args['button_text'] ?? '');
        $buttonUrl = trim($args['button_url'] ?? '');
        $pin = !empty($args['pin']);

        // 构建精美排版
        $text = "━━━━━━━━━━━━━━━━━\n";
        $text .= "📢 " . $title . "\n";
        $text .= "━━━━━━━━━━━━━━━━━\n\n";
        $text .= $content . "\n\n";
        $text .= "━━━━━━━━━━━━━━━━━\n";
        $text .= "🚀 VP92 极速加速器\n";
        $text .= "🔗 https://vp92vip.com";

        // 构建按钮
        $replyMarkup = null;
        if (!empty($buttonText) && !empty($buttonUrl)) {
            $replyMarkup = json_encode([
                'inline_keyboard' => [[
                    ['text' => $buttonText, 'url' => $buttonUrl],
                ]],
            ]);
        }

        $params = [
            'chat_id' => $channelId,
            'disable_web_page_preview' => false,
        ];

        if (!empty($imageUrl)) {
            // 带图片的公告：用 sendPhoto，图片通过 URL 发送，不存本地
            $params['photo'] = $imageUrl;
            $params['caption'] = $text;
            if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
            $method = 'sendPhoto';
        } else {
            // 纯文字公告
            $params['text'] = $text;
            if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
            $method = 'sendMessage';
        }

        $response = Http::timeout(15)->post($this->apiUrl . $method, $params);

        if (!$response->successful()) {
            $err = $response->json();
            $desc = $err['description'] ?? $response->body();
            return "发送失败: {$desc}";
        }

        // 置顶
        if ($pin) {
            $result = $response->json();
            $msgId = $result['result']['message_id'] ?? null;
            if ($msgId) {
                Http::timeout(10)->post($this->apiUrl . 'pinChatMessage', [
                    'chat_id' => $channelId,
                    'message_id' => $msgId,
                ]);
            }
        }

        return '公告已发送到频道' . ($pin ? '并已置顶' : '') . ($imageUrl ? '（含图片）' : '');
    }

    /**
     * 给单个用户发送续费促销邮件（仅管理员）
     */
    private function toolSendRenewalEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "邮箱格式无效: {$email}";
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return "未找到用户: {$email}";
        }

        $appName = admin_setting('app_name', 'VP92');
        $appUrl = rtrim(admin_setting('app_url', 'https://vp92vip.com'), '/');

        $log = MailService::sendEmail([
            'email' => $email,
            'subject' => $appName . ' - VIP限时特惠，续费专享优惠',
            'template_name' => 'renewalCampaign',
            'template_value' => [
                'name' => $appName,
                'url' => $appUrl,
                'purchase_url' => $appUrl . '/#/plan',
                'price' => '19.99',
            ],
        ]);

        if (!empty($log['error'])) {
            Log::error('Bot 发送续费邮件失败', ['email' => $email, 'error' => $log['error']]);
            return "❌ 发送失败: {$log['error']}";
        }

        Log::info('Bot 发送续费邮件成功', ['email' => $email, 'admin_bot' => true]);
        return "✅ 续费邮件已发送至 {$email}";
    }

    // ========================================================
    // 频道成员管理
    // ========================================================

    private function handleChannelJoin(array $update): void
    {
        $chatMember = $update['chat_member'] ?? [];
        $channelId = $chatMember['chat']['id'] ?? 0;
        $targetChannelId = (int) env('TELEGRAM_CHANNEL_ID', 0);

        if (!$channelId || $channelId != $targetChannelId) return;

        $newStatus = $chatMember['new_chat_member']['status'] ?? '';
        if ($newStatus !== 'member') return;

        $user = $chatMember['new_chat_member']['user'] ?? [];
        $tgUserId = $user['id'] ?? 0;
        $username = $user['username'] ?? '';
        $firstName = $user['first_name'] ?? '未知';

        if (!$tgUserId) return;

        // 检查是否绑定邮箱
        $dbUser = User::where('telegram_id', $tgUserId)->first();
        if ($dbUser) return;

        // 未绑定，在频道发提醒（带绑定按钮）
        $mention = $username ? "@{$username}" : $firstName;
        $notice = "⚠️ 用户 {$mention} 尚未绑定账号\n\n" .
            "请点击下方按钮联系客服 Bot，发送你的注册邮箱完成绑定，绑定后即可重新加入频道。";

        Http::timeout(10)->post($this->apiUrl . 'sendMessage', [
            'chat_id' => $channelId,
            'text' => $notice,
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '绑定账号', 'url' => 'https://t.me/VP92jisu_bot'],
                ]],
            ]),
        ]);

        // 踢出用户
        $this->info("踢出未绑定用户: {$mention} ({$tgUserId})");
        Http::timeout(10)->post($this->apiUrl . 'banChatMember', [
            'chat_id' => $channelId,
            'user_id' => $tgUserId,
            'until_date' => time() + 30,
        ]);
    }

    private function formatOrder(Order $order): string
    {
        $statusMap = [0 => '待支付', 1 => '开通中', 2 => '已取消', 3 => '已完成'];
        return json_encode([
            'trade_no' => $order->trade_no,
            'email' => $order->user?->email ?? '未知',
            'plan' => $order->plan?->name ?? '未知',
            'amount' => ($order->total_amount / 100) . '元',
            'status' => $statusMap[$order->status] ?? "未知({$order->status})",
            'created_at' => date('Y-m-d H:i', $order->created_at),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function formatOrderOneLine(Order $order): string
    {
        $statusMap = [0 => '待支付', 1 => '开通中', 2 => '已取消', 3 => '已完成'];
        $status = $statusMap[$order->status] ?? "未知({$order->status})";
        return "[" . date('m-d H:i', $order->created_at) . "] " . ($order->user?->email ?? '未知') . " - " . ($order->plan?->name ?? '未知') . " " . ($order->total_amount / 100) . "元 ({$status})";
    }

    // ========================================================
    // 员工工具实现（仅限查询自己邀请的用户）
    // ========================================================

    private function checkStaffInvite(string $email): ?\App\Models\User
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return null;
        // 只能查询自己邀请的用户
        if ($user->invite_user_id != $this->currentStaffUserId) return null;
        return $user;
    }

    private function toolStaffLookupUser(string $email): string
    {
        $user = $this->checkStaffInvite($email);
        if (!$user) {
            $email = strtolower(trim($email));
            $exists = User::where('email', $email)->first();
            if (!$exists) return "未找到邮箱 {$email} 对应的用户。";
            return "用户 {$email} 不是您邀请的，无权查看。";
        }

        $plan = $user->plan ? $user->plan->name : '无套餐';
        $status = $user->banned ? '已封禁' : ($user->isActive() ? '活跃' : '已过期');
        $expired = $user->expired_at ? date('Y-m-d H:i', $user->expired_at) : '永不过期';
        $usedTraffic = $this->formatTraffic($user->getTotalUsedTraffic());
        $totalTraffic = $this->formatTraffic($user->transfer_enable ?? 0);
        $balance = ($user->balance ?? 0) / 100;
        $lastLogin = $user->last_login_at ? date('Y-m-d H:i', $user->last_login_at) : '从未登录';

        return json_encode([
            'email' => $user->email, 'status' => $status, 'plan' => $plan,
            'expired_at' => $expired, 'used_traffic' => $usedTraffic,
            'total_traffic' => $totalTraffic, 'balance' => "{$balance}元",
            'last_login' => $lastLogin,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolStaffUserTraffic(string $email): string
    {
        $user = $this->checkStaffInvite($email);
        if (!$user) {
            $email = strtolower(trim($email));
            $exists = User::where('email', $email)->first();
            if (!$exists) return "未找到邮箱 {$email} 对应的用户。";
            return "用户 {$email} 不是您邀请的，无权查看。";
        }

        $upload = $user->u ?? 0;
        $download = $user->d ?? 0;
        $totalUsed = $upload + $download;
        $totalEnabled = $user->transfer_enable ?? 0;
        $remaining = max(0, $totalEnabled - $totalUsed);
        $percentage = $totalEnabled > 0 ? round(($totalUsed / $totalEnabled) * 100, 1) : 0;

        return json_encode([
            'email' => $user->email, 'upload' => $this->formatTraffic($upload),
            'download' => $this->formatTraffic($download),
            'total_used' => $this->formatTraffic($totalUsed),
            'total_enabled' => $this->formatTraffic($totalEnabled),
            'remaining' => $this->formatTraffic($remaining),
            'used_percentage' => "{$percentage}%",
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolStaffMyInvites(): string
    {
        $users = User::where('invite_user_id', $this->currentStaffUserId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        if ($users->isEmpty()) return '您还没有邀请任何用户。';

        $lines = [];
        foreach ($users as $user) {
            $plan = $user->plan ? $user->plan->name : '无套餐';
            $status = $user->banned ? '封禁' : ($user->isActive() ? '活跃' : '过期');
            $balance = ($user->balance ?? 0) / 100;
            $lines[] = "{$user->email} | {$plan} | {$status} | 余额{$balance}元 | 注册: " . date('Y-m-d', $user->created_at);
        }

        return "您共邀请 " . count($users) . " 个用户：\n\n" . implode("\n", $lines);
    }

    private function stop(): void
    {
        $this->info('收到退出信号，停止...');
        exit(0);
    }
}
