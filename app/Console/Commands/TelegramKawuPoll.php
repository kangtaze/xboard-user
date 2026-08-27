<?php

namespace App\Console\Commands;

use App\Models\InviteCode;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\Output\QRGdImagePNG;
use App\Models\GiftCardCode;
use App\Models\GiftCardTemplate;
use App\Services\TelegramChatAction;

/**
 * 卡务AI Bot — 独立 Long Polling 守护进程
 *
 * 礼品卡管理、员工购买、兑换码查询。
 * Bot Token: TELEGRAM_KAWU_BOT_TOKEN (@VP92kawu_bot)
 * AI Key: DEEPSEEK_KAWU_API_KEY
 */
class TelegramKawuPoll extends Command
{
    protected $signature = 'telegram:kawu-poll';
    protected $description = '卡务AI Bot — 礼品卡管理 + 员工购买';

    private string $botToken;
    private string $apiUrl;
    private int $offset = 0;

    // 管理员 telegram_id 白名单
    private array $adminTelegramIds = [];

    // 员工 telegram_id => user_id 映射
    private array $staffTelegramMap = [];

    // 当前消息发送者信息
    private string $currentRole = ''; // 'admin' | 'staff'
    private int $currentStaffUserId = 0;
    private int $currentChatId = 0;

    public function handle(): int
    {
        $this->botToken = env('TELEGRAM_KAWU_BOT_TOKEN', '');

        if (empty($this->botToken)) {
            $this->error('TELEGRAM_KAWU_BOT_TOKEN 未配置');
            return 1;
        }

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";

        $this->loadAdminIds();
        $this->loadStaffIds();
        $this->deleteWebhook();

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, fn() => $this->stop());
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_async_signals(true);
        }

        $this->info("卡务AI Bot 已启动 (@" . $this->getBotUsername() . ")");

        while (true) {
            try {
                $this->pollUpdates();
                $this->checkPendingPayments();
            } catch (\Throwable $e) {
                Log::error('卡务AI Bot poll 失败', ['error' => $e->getMessage()]);
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
            'allowed_updates' => json_encode(['message', 'callback_query']),
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
        // 处理内联按钮回调
        $callbackQuery = $update['callback_query'] ?? null;
        if ($callbackQuery) {
            $this->handleCallbackQuery($callbackQuery);
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

        if (!$isPrivate) return;

        $isAdmin = in_array($userId, $this->adminTelegramIds);
        $isStaff = isset($this->staffTelegramMap[$userId]);

        if ($isAdmin) {
            $this->currentRole = 'admin';
            $this->currentChatId = $chatId;
        } elseif ($isStaff) {
            $this->currentRole = 'staff';
            $this->currentStaffUserId = $this->staffTelegramMap[$userId];
            $this->currentChatId = $chatId;
        } else {
            $this->handleStaffBinding($chatId, $userId, $text);
            return;
        }

        $roleLabel = $this->currentRole === 'admin' ? '管理员' : '员工';
        $this->info("[{$userId}] {$roleLabel}消息: {$text}");

        // 员工说"购买"相关关键词时直接弹出礼品卡菜单
        if ($this->currentRole === 'staff') {
            $qtyState = Cache::get("gc_qty_waiting_{$chatId}");
            if ($qtyState !== null) {
                $this->handleQuantityInput($chatId, $text, $qtyState);
                return;
            }

            $buyKeywords = ['购买', '买礼品卡', '买卡', '购卡', '礼品卡菜单', '/buy'];
            $isBuyIntent = false;
            foreach ($buyKeywords as $kw) {
                if (str_contains($text, $kw)) {
                    $isBuyIntent = true;
                    break;
                }
            }
            if ($isBuyIntent) {
                $this->showGiftCardMenu($chatId, $this->currentStaffUserId);
                return;
            }
        }

        if ($text === '/start') {
            if ($this->currentRole === 'admin') {
                $this->sendTelegram($chatId, "💳 卡务AI助手已就绪。\n\n您可以：\n- 生成礼品卡兑换码\n- 查询兑换码使用情况\n\n直接输入需求即可。");
            } else {
                $this->sendTelegram($chatId, "👤 员工AI助手已就绪。\n\n您可以：\n- 🛒 购买礼品卡（员工折扣价）\n- 🔍 查询兑换码使用情况\n- 👥 查看您邀请的用户信息\n\n发送「购买」可直接打开礼品卡菜单，或直接输入需求由AI处理。");
                $this->showGiftCardMenu($chatId, $this->currentStaffUserId);
            }
            return;
        }

        try {
            $reply = $this->kawuAiChat($chatId, $text);
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
        if (!str_contains($text, '@') || !filter_var(trim($text), FILTER_VALIDATE_EMAIL)) {
            $this->sendTelegram($chatId, "👋 您好，此机器人仅供管理员和员工使用。\n\n如果您是员工，请发送您的注册邮箱进行身份验证。");
            return;
        }

        $email = strtolower(trim($text));
        $staff = User::where('email', $email)->where('is_staff', 1)->first();

        if (!$staff) {
            $this->warn("[{$telegramId}] 绑定失败: {$email} 不是员工账号");
            $this->sendTelegram($chatId, "❌ 邮箱 {$email} 不是员工账号，无法绑定。");
            return;
        }

        $staff->update(['telegram_id' => $telegramId]);
        $this->info("[{$telegramId}] 员工绑定成功: {$email} (user_id={$staff->id})");
        $this->reloadStaffIds();

        $this->sendTelegram($chatId, "✅ 员工身份验证成功！\n\n邮箱: {$email}\n\n您现在可以使用员工AI助手查询兑换码和购买礼品卡了。发送 /start 查看可用功能。");
    }

    // ========================================================
    // 礼品卡购买 - 内联按钮选择流程
    // ========================================================

    private function showGiftCardMenu(int $chatId, int $staffUserId): void
    {
        $templates = GiftCardTemplate::where('status', 1)->get();
        $buttons = [];
        $lines = ["🛒 请选择要购买的礼品卡：\n"];

        $excludeNames = ['7天体验卡'];
        $grouped = [];

        foreach ($templates as $t) {
            if (in_array($t->name, $excludeNames)) continue;
            $r = is_string($t->rewards) ? json_decode($t->rewards, true) : $t->rewards;
            if (isset($r['balance'])) continue;

            $planId = $r['plan_id'] ?? 0;
            $plan = Plan::find($planId);
            $vipLabel = $plan ? $plan->name : 'Unknown';
            if (!isset($grouped[$vipLabel])) $grouped[$vipLabel] = [];
            $grouped[$vipLabel][] = ['template' => $t, 'price' => $this->getTemplatePrice($t)];
        }

        foreach ($grouped as $vipLabel => $items) {
            $lines[] = "━━ {$vipLabel} ━━";
            foreach ($items as $item) {
                // 每个按钮独占一行，确保价格不被截断
                $buttons[] = [
                    ['text' => "{$item['template']->name}  ¥{$item['price']}", 'callback_data' => "gc_buy:{$item['template']->id}"]
                ];
            }
        }

        $lines[] = "\n点击上方按钮选择，将跳过AI直接进入支付流程。";
        $lines[] = "也可以直接输入需求，由AI助手帮您处理。";

        // 底部加"取消"按钮
        $buttons[] = [['text' => '❌ 取消', 'callback_data' => 'gc_cancel']];

        $markup = json_encode(['inline_keyboard' => $buttons]);
        $params = [
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'reply_markup' => $markup,
        ];
        Http::timeout(10)->post($this->apiUrl . 'sendMessage', $params);

        // 记录是谁在选
        Cache::put("gc_menu_{$chatId}", ['staff_user_id' => $staffUserId], 300);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $from = $callbackQuery['from'] ?? [];
        $userId = (int) ($from['id'] ?? 0);
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);

        // 回答回调（去掉按钮上的loading状态）
        Http::timeout(5)->post($this->apiUrl . 'answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id'],
        ]);

        if (str_starts_with($data, 'gc_buy:')) {
            $templateId = (int) substr($data, 7);
            $this->handleGiftCardSelect($chatId, $userId, $templateId);
        } elseif ($data === 'gc_cancel') {
            Cache::forget("gc_menu_{$chatId}");
            Cache::forget("gc_qty_waiting_{$chatId}");
            $this->editTelegramMessage($chatId, $messageId, "❌ 已取消购买。");
        }
    }

    private function handleGiftCardSelect(int $chatId, int $userId, int $templateId): void
    {
        $menuState = Cache::get("gc_menu_{$chatId}");
        if (!$menuState) {
            $this->sendTelegram($chatId, "❌ 菜单已过期，请重新输入「购买礼品卡」。");
            return;
        }

        $staffUserId = $menuState['staff_user_id'];
        $isStaff = isset($this->staffTelegramMap[$userId]);

        // 只有员工本人或管理员能操作
        if (!$isStaff && !in_array($userId, $this->adminTelegramIds)) {
            $this->sendTelegram($chatId, "❌ 无权限操作。");
            return;
        }

        $template = GiftCardTemplate::find($templateId);
        if (!$template || $template->status != 1) {
            $this->sendTelegram($chatId, "❌ 模板不存在或已下架，请重新选择。");
            return;
        }

        $price = $this->getTemplatePrice($template);
        $staffUser = User::find($staffUserId);
        $discount = $staffUser ? ($staffUser->discount ?? 0) : 0;
        $discountedPrice = round($price * (100 - $discount) / 100, 2);

        $discountInfo = $discount > 0 ? "（员工 {$discount}% 折扣）" : '';
        $this->sendTelegram($chatId,
            "✅ 已选择：**{$template->name}**\n" .
            "原价：¥{$price} / 张\n" .
            "实付：¥{$discountedPrice} / 张 {$discountInfo}\n\n" .
            "请回复购买数量（1-100）：",
            'Markdown'
        );

        // 进入等待数量状态
        Cache::put("gc_qty_waiting_{$chatId}", [
            'template_id' => $templateId,
            'template_name' => $template->name,
            'staff_user_id' => $staffUserId,
            'discounted_price' => $discountedPrice,
        ], 300);
    }

    private function handleQuantityInput(int $chatId, string $text, array $state): void
    {
        // 支持取消
        if (in_array(trim($text), ['取消', '退出', 'cancel'])) {
            Cache::forget("gc_qty_waiting_{$chatId}");
            Cache::forget("gc_menu_{$chatId}");
            $this->sendTelegram($chatId, "❌ 已取消购买。");
            return;
        }

        $count = (int) $text;
        if ($count < 1 || $count > 100) {
            $this->sendTelegram($chatId, "❌ 数量无效，请输入 1-100 的数字。输入「取消」退出。");
            return;
        }

        Cache::forget("gc_qty_waiting_{$chatId}");
        Cache::forget("gc_menu_{$chatId}");

        $this->info("[礼品卡] 员工通过按钮选择 {$state['template_name']} x {$count}");

        // 直接调用购买流程（跳过AI）
        $result = $this->toolPurchaseGiftCards($state['template_name'], $count);
        $this->sendTelegram($chatId, $result);
    }

    private function editTelegramMessage(int $chatId, int $messageId, string $text): void
    {
        try {
            Http::timeout(10)->post($this->apiUrl . 'editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::error('编辑消息失败', ['error' => $e->getMessage()]);
        }
    }

    // ========================================================
    // 消息发送
    // ========================================================

    private function sendTelegram(int $chatId, string $text, string $parseMode = ''): void
    {
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
            Log::error('卡务AI Bot 发送消息失败', ['error' => $e->getMessage()]);
        }
    }

    // ========================================================
    // DeepSeek Function Calling AI
    // ========================================================

    private function kawuAiChat(int|string $chatId, string $message): string
    {
        // v20.5: 先发 "typing" 状态，让用户感知 Bot 在工作
        TelegramChatAction::send($this->botToken, $chatId);

        $apiKey = env('DEEPSEEK_KAWU_API_KEY', '');
        $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

        if (empty($apiKey)) {
            return '卡务AI API Key 未配置。';
        }

        $tools = $this->currentRole === 'staff' ? $this->getStaffTools() : $this->getAdminTools();

        if ($this->currentRole === 'staff') {
            $systemPrompt = <<<'PROMPT'
你是 VP92 极速加速器的员工 AI 助手。你可以通过工具函数查询信息和购买礼品卡。
你的权限：
1. 兑换码使用情况（是否已使用、被哪个客户使用了）
2. 您邀请的用户的详细信息（余额、流量、套餐、到期时间等）
3. 购买礼品卡（员工专享折扣价，当面付）
- 邮箱地址全部转小写处理
- 流量单位用 GB/MB/KB 自动转换
- 金额单位用元，余额字段是分（除以100）
- 时间戳转换为可读日期格式
- 如果查询的用户不是您邀请的，请告知无权限
回答简洁专业，给出具体数字。回答用中文。
PROMPT;
        } else {
            $systemPrompt = <<<'PROMPT'
你是 VP92 极速加速器的卡务 AI 助手。你可以通过工具函数管理礼品卡和查询兑换码。
- 邮箱地址全部转小写处理
- 流量单位用 GB/MB/KB 自动转换
- 金额单位用元，余额字段是分（除以100）
- 时间戳转换为可读日期格式
回答简洁专业，给出具体数字。回答用中文。
PROMPT;
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        try {
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
                    Log::error('卡务AI API 失败', ['status' => $response->status(), 'body' => $response->body()]);
                    return "AI API 调用失败 (HTTP {$response->status()})，请稍后再试。";
                }

                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];

                $messages[] = $choice;

                if (empty($choice['tool_calls'])) {
                    $content = $choice['content'] ?? '';
                    if (empty($content) && !empty($choice['reasoning_content'])) {
                        $content = $choice['reasoning_content'];
                    }
                    return $content ?: '无法获取回复。';
                }

                foreach ($choice['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'];

                    $this->info("  🔧 调用工具: {$functionName}(" . json_encode($arguments, JSON_UNESCAPED_UNICODE) . ")");

                    $result = $this->executeKawuTool($functionName, $arguments);

                    $this->info("  ✅ 工具结果: " . mb_substr($result, 0, 100));

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => $result,
                    ];
                }
            }

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
            Log::error('卡务AI chat 异常', ['error' => $e->getMessage()]);
            return "AI 服务暂时不可用，请稍后再试。";
        }
    }

    // ========================================================
    // Tool 定义
    // ========================================================

    private function getAdminTools(): array
    {
        return [
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
                    'name' => 'generate_gift_cards',
                    'description' => '生成礼品卡兑换码（管理员免费生成，可指定代理邮箱或自定义前缀字母）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'template_name' => ['type' => 'string', 'description' => '礼品卡模板名称'],
                            'count' => ['type' => 'integer', 'description' => '生成数量 1-100'],
                            'agent_email' => ['type' => 'string', 'description' => '可选，代理员工邮箱，用该代理备注字母作为前缀'],
                            'custom_prefix' => ['type' => 'string', 'description' => '可选，自定义前缀字母（如A），会自动拼接VIP等级+周期代码'],
                        ],
                        'required' => ['template_name', 'count'],
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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'purchase_gift_cards',
                    'description' => '购买礼品卡兑换码（员工专享折扣价，当面付）',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'template_name' => ['type' => 'string', 'description' => '礼品卡模板名称'],
                            'count' => ['type' => 'integer', 'description' => '购买数量'],
                        ],
                        'required' => ['template_name', 'count'],
                    ],
                ],
            ],
        ];
    }

    private function executeKawuTool(string $name, array $args): string
    {
        try {
            if ($this->currentRole === 'staff') {
                return match ($name) {
                    'lookup_code' => $this->toolLookupCode($args['code'] ?? ''),
                    'staff_lookup_user' => $this->toolStaffLookupUser($args['email'] ?? ''),
                    'staff_user_traffic' => $this->toolStaffUserTraffic($args['email'] ?? ''),
                    'staff_my_invites' => $this->toolStaffMyInvites(),
                    'purchase_gift_cards' => $this->toolPurchaseGiftCards($args['template_name'] ?? '', (int)($args['count'] ?? 1)),
                    default => "员工无权使用工具: {$name}",
                };
            }

            return match ($name) {
                'lookup_code' => $this->toolLookupCode($args['code'] ?? ''),
                'generate_gift_cards' => $this->toolGenerateGiftCards($args['template_name'] ?? '', (int)($args['count'] ?? 1), $args['agent_email'] ?? null, $args['custom_prefix'] ?? null),
                default => "未知工具函数: {$name}",
            };
        } catch (\Throwable $e) {
            Log::error("卡务AI tool {$name} 执行失败", ['error' => $e->getMessage()]);
            return "工具执行出错，请稍后再试。";
        }
    }

    // ========================================================
    // 公共辅助方法
    // ========================================================

    private function formatTraffic(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    private function toolLookupCode(string $code): string
    {
        $code = strtoupper(trim($code));

        $giftCode = GiftCardCode::where('code', $code)->first();
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

    // ========================================================
    // 员工工具实现
    // ========================================================

    private function checkStaffInvite(string $email): ?User
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user) return null;
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

    // ========================================================
    // 礼品卡辅助方法
    // ========================================================

    private function generateQrCodeImage(string $text, string $filePath): bool
    {
        try {
            $qr = new QRCode([
                'version' => 5,
                'scale' => 4,
                'outputInterface' => QRGdImagePNG::class,
            ]);
            $qr->render($text, $filePath);
            return file_exists($filePath);
        } catch (\Throwable $e) {
            Log::error('QR码生成失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendTelegramPhoto(int $chatId, string $imagePath, string $caption = ''): bool
    {
        if (!file_exists($imagePath)) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'sendPhoto');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chatId,
            'photo' => new \CURLFile($imagePath),
            'caption' => $caption,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            Log::error('Telegram 发图失败', ['http_code' => $httpCode, 'response' => $result]);
            return false;
        }
        $data = json_decode($result, true);
        return ($data['ok'] ?? false);
    }

    private function sendTelegramDocument(int $chatId, string $filePath, string $caption = ''): bool
    {
        if (!file_exists($filePath)) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'sendDocument');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chatId,
            'document' => new \CURLFile($filePath),
            'caption' => $caption,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            Log::error('Telegram 发文件失败', ['http_code' => $httpCode, 'response' => $result]);
            return false;
        }
        return true;
    }

    private function sendGiftCardCodesAsFile(int $chatId, array $codeList, string $templateName, int $count): string
    {
        $fileName = '/tmp/gift_codes_' . $chatId . '_' . time() . '.txt';
        $content = "{$templateName} x {$count}\n";
        $content .= "生成时间: " . date('Y-m-d H:i:s') . "\n";
        $content .= str_repeat('-', 30) . "\n";
        foreach ($codeList as $i => $code) {
            $content .= ($i + 1) . ". {$code}\n";
        }
        file_put_contents($fileName, "\xEF\xBB\xBF" . $content); // UTF-8 BOM

        $this->sendTelegramDocument($chatId, $fileName, "📋 {$templateName} x {$count} 张");
        @unlink($fileName);

        return "✅ 购买成功！\n\n模板：{$templateName}\n数量：{$count} 张\n\n兑换码已以文件形式发送，请转发给客户。";
    }

    private function findGiftCardTemplate(string $name): ?GiftCardTemplate
    {
        $templates = GiftCardTemplate::where('status', 1)
            ->where('name', 'like', '%' . $name . '%')
            ->get();

        foreach ($templates as $t) {
            $r = is_string($t->rewards) ? json_decode($t->rewards, true) : $t->rewards;
            if (!isset($r['balance'])) {
                return $t;
            }
        }
        return null;
    }

    private function getTemplatePrice(GiftCardTemplate $template): float
    {
        $rewards = is_string($template->rewards) ? json_decode($template->rewards, true) : $template->rewards;
        $planId = $rewards['plan_id'] ?? null;
        $validityDays = $rewards['plan_validity_days'] ?? 0;

        if (!$planId) return 0;

        $plan = Plan::find($planId);
        if (!$plan) return 0;

        $prices = is_string($plan->prices) ? json_decode($plan->prices, true) : $plan->prices;
        if (empty($prices)) return 0;

        $priceKey = match (true) {
            $validityDays <= 7 => 'onetime',
            $validityDays <= 31 => 'monthly',
            $validityDays <= 100 => 'quarterly',
            $validityDays <= 200 => 'half_yearly',
            $validityDays <= 400 => 'yearly',
            default => 'onetime',
        };

        return (float) ($prices[$priceKey] ?? 0);
    }

    private function buildGiftCardPrefix(?User $staffUser, GiftCardTemplate $template): string
    {
        $remarks = $staffUser ? ($staffUser->remarks ?? '') : '';
        $initial = mb_strlen($remarks) >= 1 ? mb_substr($remarks, 0, 1) : 'X';

        $rewards = is_string($template->rewards) ? json_decode($template->rewards, true) : $template->rewards;
        $planId = $rewards['plan_id'] ?? 1;
        $validityDays = $rewards['plan_validity_days'] ?? 30;

        $periodCode = match ($validityDays) {
            7 => '0',
            30 => '1',
            365 => '2',
            90 => '3',
            180 => '6',
            default => '1',
        };

        return $initial . 'VIP' . $planId . $periodCode;
    }

    private function toolGenerateGiftCards(string $templateName, int $count, ?string $agentEmail = null, ?string $customPrefix = null): string
    {
        $templateName = trim($templateName);
        $count = max(1, min($count, 100));

        $template = $this->findGiftCardTemplate($templateName);
        if (!$template) {
            $allTemplates = GiftCardTemplate::where('status', 1)->get();
            $templateList = [];
            foreach ($allTemplates as $t) {
                $r = is_string($t->rewards) ? json_decode($t->rewards, true) : $t->rewards;
                if (!isset($r['balance'])) {
                    $templateList[] = $t->name;
                }
            }
            return "未找到模板「{$templateName}」。\n可用模板：" . implode('、', $templateList);
        }

        $prefix = 'GC';
        if ($agentEmail) {
            $agentUser = User::where('email', $agentEmail)->first();
            if ($agentUser) {
                $prefix = $this->buildGiftCardPrefix($agentUser, $template);
            } else {
                return "未找到代理用户「{$agentEmail}」。";
            }
        } elseif ($customPrefix) {
            $prefix = $this->buildGiftCardPrefix(null, $template);
            $prefix = $customPrefix . substr($prefix, 1);
        }

        $result = ['template' => $template->name, 'count' => $count, 'codes' => []];
        try {
            $options = ['prefix' => $prefix, 'max_usage' => 1];
            $batchId = GiftCardCode::batchGenerate($template->id, $count, $options);
            $codes = GiftCardCode::where('batch_id', $batchId)->get();
            foreach ($codes as $code) {
                $result['codes'][] = $code->code;
            }
        } catch (\Throwable $e) {
            Log::error("[礼品卡] 生成失败", ['error' => $e->getMessage()]);
            return "生成失败，请稍后再试。";
        }

        $this->info("[礼品卡] 管理员生成 {$count} 张 {$template->name}");
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    // ========================================================
    // 支付宝当面付
    // ========================================================

    private function getAlipayConfig(): array
    {
        $payment = DB::table('v2_payment')->where('payment', 'AlipayF2F')->where('enable', 1)->first();
        if (!$payment) {
            throw new \Exception('支付宝当面付未启用');
        }
        $config = json_decode($payment->config, true);
        return [
            'app_id' => $config['app_id'] ?? '',
            'private_key' => $config['private_key'] ?? '',
            'public_key' => $config['public_key'] ?? '',
            'product_name' => $config['product_name'] ?? 'VP92极速加速器',
        ];
    }

    private function createAlipayOrder(float $amount, string $tradeNo): string
    {
        $cfg = $this->getAlipayConfig();

        require_once base_path('plugins-core/AlipayF2f/library/AlipayF2F.php');
        $gateway = new \Plugin\AlipayF2f\library\AlipayF2F();
        $gateway->setMethod('alipay.trade.precreate');
        $gateway->setAppId($cfg['app_id']);
        $gateway->setPrivateKey($cfg['private_key']);
        $gateway->setAlipayPublicKey($cfg['public_key']);
        $gateway->setBizContent([
            'subject' => $cfg['product_name'] . ' - 礼品卡',
            'out_trade_no' => $tradeNo,
            'total_amount' => number_format($amount, 2, '.', ''),
        ]);
        $gateway->send();
        return $gateway->getQrCodeUrl();
    }

    private function pollAlipayPayment(string $tradeNo): string
    {
        $cfg = $this->getAlipayConfig();

        require_once base_path('plugins-core/AlipayF2f/library/AlipayF2F.php');
        $gateway = new \Plugin\AlipayF2f\library\AlipayF2F();
        $gateway->setMethod('alipay.trade.query');
        $gateway->setAppId($cfg['app_id']);
        $gateway->setPrivateKey($cfg['private_key']);
        $gateway->setAlipayPublicKey($cfg['public_key']);
        $gateway->setBizContent([
            'out_trade_no' => $tradeNo,
        ]);
        $gateway->send();
        $response = $gateway->getResponse();
        return $response['trade_status'] ?? '';
    }

    private function checkPendingPayments(): void
    {
        $key = 'gc_pending_payments';
        $pending = Cache::get($key);
        if (!$pending) return;

        $now = time();
        $changed = false;

        foreach ($pending as $chatId => &$order) {
            if ($now - $order['created_at'] > 300) {
                $this->sendTelegram((int) $chatId, "⏰ 支付超时，订单已取消。");
                unset($pending[$chatId]);
                Cache::forget("gc_purchase_{$chatId}");
                $changed = true;
                $this->info("[支付宝] 订单 {$order['trade_no']} 超时取消");
                continue;
            }

            if ($now - ($order['last_poll'] ?? 0) < 3) continue;

            try {
                $status = $this->pollAlipayPayment($order['trade_no']);
                $order['last_poll'] = $now;

                if ($status === 'TRADE_SUCCESS') {
                    $purchaseData = Cache::get("gc_purchase_{$chatId}");
                    if ($purchaseData) {
                        Cache::forget("gc_purchase_{$chatId}");
                        $purchaseData['trade_no'] = $order['trade_no'];
                        $purchaseData['paid_amount'] = $order['amount'];
                        $purchaseData['chat_id'] = $chatId;
                        $result = $this->executeGiftCardPurchase($purchaseData);
                        $this->sendTelegram((int) $chatId, "💰 支付成功！\n\n" . $result);
                        // 通知管理员
                        $staffUser = User::find($purchaseData['staff_user_id']);
                        $staffName = $staffUser ? $staffUser->email : '未知员工';
                        $templateName = $purchaseData['template_name'] ?? '';
                        $purchaseCount = $purchaseData['count'] ?? 0;
                        $adminMsg = "📦 礼品卡采购通知\n\n员工：{$staffName}\n模板：{$templateName}\n数量：{$purchaseCount} 张\n金额：{$order['amount']} 元\n订单号：{$order['trade_no']}";
                        foreach ($this->adminTelegramIds as $adminId) {
                            $this->sendTelegram($adminId, $adminMsg);
                        }
                        $this->info("[支付宝] 订单 {$order['trade_no']} 支付成功，已发码并通知管理员");
                    }
                    unset($pending[$chatId]);
                    $changed = true;
                }
            } catch (\Throwable $e) {
                Log::error("[支付宝] 轮询异常", ['trade_no' => $order['trade_no'], 'error' => $e->getMessage()]);
            }
        }

        if ($changed) {
            if (empty($pending)) {
                Cache::forget($key);
            } else {
                Cache::put($key, $pending, 600);
            }
        } else {
            Cache::put($key, $pending, 600);
        }
    }

    private function toolPurchaseGiftCards(string $templateName, int $count): string
    {
        $templateName = trim($templateName);
        $count = max(1, min($count, 100));

        $template = $this->findGiftCardTemplate($templateName);
        if (!$template) {
            $allTemplates = GiftCardTemplate::where('status', 1)->get();
            $templateList = [];
            foreach ($allTemplates as $t) {
                $r = is_string($t->rewards) ? json_decode($t->rewards, true) : $t->rewards;
                if (!isset($r['balance'])) {
                    $templateList[] = $t->name;
                }
            }
            return "未找到模板「{$templateName}」。\n可用模板：" . implode('、', $templateList);
        }

        $staffUser = User::find($this->currentStaffUserId);
        $discount = $staffUser ? ($staffUser->discount ?? 0) : 0;
        $unitPrice = $this->getTemplatePrice($template);
        $discountedPrice = round($unitPrice * (100 - $discount) / 100, 2);
        $totalAmount = round($discountedPrice * $count, 2);

        if ($totalAmount <= 0) {
            return "❌ 价格计算异常（单价 {$unitPrice}，折扣 {$discount}%），请联系管理员检查套餐价格配置。";
        }

        $chatId = $this->currentChatId;
        $tradeNo = 'GC_' . $chatId . '_' . time();

        try {
            $qrUrl = $this->createAlipayOrder($totalAmount, $tradeNo);

            $qrFile = '/tmp/gc_qr_' . $chatId . '_' . time() . '.png';
            $this->generateQrCodeImage($qrUrl, $qrFile);

            $caption = sprintf(
                "🛒 礼品卡购买 — 请扫码支付\n\n模板：%s\n原价：%.2f 元/张\n折扣：%d%%\n实付：%.2f 元/张 × %d 张 = %.2f 元\n\n⏰ 请在 5 分钟内完成支付",
                $template->name, $unitPrice, $discount, $discountedPrice, $count, $totalAmount
            );

            $this->sendTelegramPhoto($chatId, $qrFile, $caption);
            @unlink($qrFile);

            Cache::put("gc_purchase_{$chatId}", [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'count' => $count,
                'staff_user_id' => $this->currentStaffUserId,
            ], 600);

            $pendingPayments = Cache::get('gc_pending_payments', []);
            $pendingPayments[$chatId] = [
                'trade_no' => $tradeNo,
                'amount' => $totalAmount,
                'created_at' => time(),
                'last_poll' => 0,
            ];
            Cache::put('gc_pending_payments', $pendingPayments, 600);

            $this->info("[礼品卡] 员工发起购买 {$count} 张 {$template->name}，金额 {$totalAmount} 元，trade_no={$tradeNo}");

            return "已发送支付宝付款码，等待员工扫码支付。";
        } catch (\Throwable $e) {
            $this->sendTelegram($chatId, "❌ 支付系统暂时不可用，请稍后再试。");
            Log::error('[礼品卡] 创建支付订单失败', ['trade_no' => $tradeNo, 'error' => $e->getMessage()]);
            return "支付系统暂时不可用，请稍后再试。";
        }
    }

    private function executeGiftCardPurchase(array $pending): string
    {
        $template = GiftCardTemplate::find($pending['template_id']);
        if (!$template) {
            return "❌ 模板已不存在，购买取消。";
        }

        $count = $pending['count'];
        $staffUser = User::find($pending['staff_user_id']);
        $staffName = $staffUser ? $staffUser->email : '未知员工';

        try {
            $prefix = $this->buildGiftCardPrefix($staffUser, $template);
            $options = ['prefix' => $prefix, 'max_usage' => 1];
            $batchId = GiftCardCode::batchGenerate($template->id, $count, $options);
            $codes = GiftCardCode::where('batch_id', $batchId)->get();

            $buyerMeta = json_encode([
                'buyer_user_id' => $pending['staff_user_id'],
                'buyer_name' => $staffName,
                'bought_at' => time(),
                'template_name' => $template->name,
                'quantity' => $count,
            ], JSON_UNESCAPED_UNICODE);
            GiftCardCode::where('batch_id', $batchId)->update(['metadata' => $buyerMeta]);

            $codeList = [];
            foreach ($codes as $code) {
                $codeList[] = $code->code;
            }

            $paidAmount = round(($pending['paid_amount'] ?? 0) * 100);
            $rewards = is_string($template->rewards) ? json_decode($template->rewards, true) : $template->rewards;
            $planId = $rewards['plan_id'] ?? null;

            $validityDays = $template->plan_validity_days ?? 30;
            $periodMap = [7 => 'onetime', 30 => 'monthly', 90 => 'quarterly', 180 => 'half_yearly', 365 => 'yearly'];
            $period = $periodMap[$validityDays] ?? 'monthly';

            Order::create([
                'user_id' => $pending['staff_user_id'],
                'plan_id' => $planId,
                'payment_id' => 3,
                'type' => 5,
                'period' => $period,
                'trade_no' => $pending['trade_no'] ?? ('GC_' . time()),
                'callback_no' => "礼品卡:{$count}张 {$template->name}",
                'total_amount' => $paidAmount,
                'paid_amount' => $paidAmount,
                'status' => 3,
                'commission_status' => 3,
                'paid_at' => time(),
                'created_at' => time(),
                'updated_at' => time(),
            ]);

            $this->info("[礼品卡购买] 员工 {$staffName} 购买 {$count} 张 {$template->name}，已创建订单");
            return $this->sendGiftCardCodesAsFile((int)$pending['chat_id'], $codeList, $template->name, $count);
        } catch (\Throwable $e) {
            return "❌ 生成失败：" . $e->getMessage();
        }
    }

    private function stop(): void
    {
        $this->info('收到退出信号，停止...');
        exit(0);
    }
}
