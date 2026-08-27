<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\TelegramChatAction;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram AI 客服 + 频道管理 — Long Polling 守护进程
 *
 * 功能：
 * 1. AI 客服：私聊/群聊 @提及 自动回复（需先验证会员）
 * 2. 首次联系验证：新用户必须通过邮箱验证会员身份
 * 3. 频道会员审核：用户申请加入频道时自动审核
 * 4. 管理员公告：/announce 命令转发消息到 discuss 频道
 */
class TelegramAiPoll extends Command
{
    protected $signature = 'telegram:ai-poll';
    protected $description = 'Telegram AI 客服 + 频道管理 Long Polling 守护进程';

    private string $botToken;
    private string $apiUrl;
    private AiChatService $aiChat;
    private TelegramService $telegram;
    private int $offset = 0;
    private ?int $discussChatId = null;

    // 每用户对话历史缓存 TTL（秒）
    private const HISTORY_TTL = 3600;
    // 邮箱验证等待 TTL（秒）
    private const VERIFY_TTL = 600;

    public function handle(): int
    {
        $this->botToken = admin_setting('telegram_bot_token');

        if (empty($this->botToken)) {
            $this->error('telegram_bot_token 未配置');
            return 1;
        }

        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
        $this->aiChat = new AiChatService();
        $this->telegram = new TelegramService();

        // 读取 discuss 频道 ID
        $discussId = admin_setting('telegram_discuss_id');
        if (!empty($discussId) && is_numeric($discussId)) {
            $this->discussChatId = (int) $discussId;
            $this->info("Discuss 频道 ID: {$this->discussChatId}");
        } else {
            $this->warn('telegram_discuss_id 未配置，频道邀请功能不可用');
        }

        // 确认没有 webhook（否则 getUpdates 不可用）
        $this->deleteWebhook();

        $this->info("Telegram AI 客服 + 频道管理 Long Polling 已启动...");

        // 信号处理：优雅退出
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_signal(SIGINT, fn() => $this->stop());
        }

        while (true) {
            try {
                $this->poll();
            } catch (\Exception $e) {
                Log::error('Telegram AI Poll 异常', ['error' => $e->getMessage()]);
                $this->warn("异常: {$e->getMessage()}，5 秒后重连...");
                sleep(5);
            }
        }

        return 0;
    }

    private function poll(): void
    {
        $response = Http::timeout(35)->get($this->apiUrl . 'getUpdates', [
            'offset' => $this->offset,
            'timeout' => 30,
            'allowed_updates' => json_encode(['message', 'chat_join_request']),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("getUpdates 失败: HTTP {$response->status()}");
        }

        $data = $response->json();

        if (!($data['ok'] ?? false)) {
            throw new \RuntimeException("getUpdates 错误: " . ($data['description'] ?? '未知'));
        }

        $updates = $data['result'] ?? [];

        foreach ($updates as $update) {
            $this->offset = $update['update_id'] + 1;
            $this->processUpdate($update);
        }
    }

    private function processUpdate(array $update): void
    {
        // 处理频道加入请求
        if (isset($update['chat_join_request'])) {
            $this->processJoinRequest($update['chat_join_request']);
            return;
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return;
        }

        $chat = $message['chat'] ?? null;
        $text = $message['text'] ?? null;
        $from = $message['from'] ?? null;

        if (!$chat || !$from) {
            return;
        }

        $chatId = $chat['id'];
        $userId = $from['id'];
        $isPrivate = ($chat['type'] ?? '') === 'private';
        $text = $text ?? '';

        // 群组消息 @提及 Bot 才回复
        if (!$isPrivate) {
            $entities = $message['entities'] ?? [];
            $isMentioned = false;
            foreach ($entities as $entity) {
                if (($entity['type'] ?? '') === 'mention') {
                    $mentionedText = substr($text, $entity['offset'], $entity['length']);
                    if ($mentionedText === '@VP92jisu_bot') {
                        $isMentioned = true;
                    }
                }
            }
            if (!$isMentioned) {
                return;
            }
            $text = trim(preg_replace('/@VP92jisu_bot\s*/', '', $text));
        }

        $this->info("[{$chatId}] 用户 {$userId}: {$text}");

        try {
            // ========== 私聊命令处理 ==========

            if ($isPrivate) {
                // /announce 命令：管理员转发公告到频道
                if (str_starts_with($text, '/announce')) {
                    $this->handleAnnounce($userId, $chatId, $text);
                    return;
                }

                // 管理员跳过验证，直接放行
                $isAdminUser = User::where('telegram_id', $userId)->where('is_admin', 1)->exists();

                // 检查是否在等待邮箱验证
                $verifyState = Cache::get("tg_verify_{$userId}");
                if ($verifyState !== null) {
                    $this->handleEmailVerification($userId, $chatId, $text, $verifyState);
                    return;
                }

                // 首次联系：非管理员需验证会员身份
                if (!$isAdminUser && !$this->isUserVerified($userId)) {
                    $this->startVerification($userId, $chatId);
                    return;
                }
            }

            // ========== 命令处理 ==========

            // /start 命令特殊处理
            if ($text === '/start') {
                $welcome = "您好！我是 VP92 极速加速器的智能客服，请问有什么可以帮您的？\n\n常见问题：\n1. 如何下载客户端\n2. 如何连接使用\n3. 套餐和流量\n\n直接输入问题即可，我会为您解答。";
                $this->telegram->sendMessage($chatId, $welcome);
                return;
            }

            // /bind /traffic 等命令
            if (in_array($text, ['/bind', '/traffic', '/getlatesturl', '/unbind'])) {
                $this->telegram->sendMessage($chatId, '该命令需要先绑定账号。如需帮助请直接描述您的问题。');
                return;
            }

            // ========== AI 回复（管理员走工具调用，普通用户走纯聊天）==========

            $isAdminUser = User::where('telegram_id', $userId)->where('is_admin', 1)->exists();

            if ($isAdminUser) {
                // 管理员：使用工具调用（发公告等）
                $reply = $this->adminAiChat($chatId, $text);
                $this->telegram->sendMessage($chatId, $reply);
                $this->info("[{$chatId}] 管理员 AI 回复已发送");
            } else {
                // 普通用户：纯 AI 客服聊天
                $history = $this->getUserHistory($userId);

                // 查询数据库用户，构建用户上下文
                $dbUser = User::where('telegram_id', $userId)->first();
                $userContext = null;
                $dbUserId = 0;
                if ($dbUser) {
                    $dbUserId = $dbUser->id;
                    $userContext = $this->buildUserContext($dbUser);
                    $this->info("[{$chatId}] 用户上下文: {$userContext}");
                }

                // v20.5: 先发 "typing" 状态，让用户感知 Bot 在工作
                TelegramChatAction::send($this->botToken, $chatId);

                $result = $this->aiChat->chat($dbUserId, $text, $history, $userContext);
                $reply = $result['reply'] ?? '抱歉，我暂时无法回答，请稍后再试。';
                $this->saveUserHistory($userId, $text, $reply);
                $this->telegram->sendMessage($chatId, $reply, 'Markdown');
                $this->info("[{$chatId}] AI 回复已发送");
            }

        } catch (\Exception $e) {
            Log::error('Telegram AI 处理消息失败', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'text' => $text,
                'error' => $e->getMessage(),
            ]);
            try {
                $this->telegram->sendMessage($chatId, '抱歉，处理您的问题时出错，请稍后再试。');
            } catch (\Exception $e2) {
                Log::error('发送错误消息也失败', ['error' => $e2->getMessage()]);
            }
        }
    }

    // ========================================================
    // 用户上下文构建
    // ========================================================

    /**
     * 构建用户上下文字符串，注入到 AI system prompt
     */
    private function buildUserContext(User $user): ?string
    {
        $lines = [];

        // 邮箱
        if ($user->email) {
            $lines[] = "邮箱: {$user->email}";
        }

        // 套餐名称
        $planName = '无套餐';
        if ($user->plan_id) {
            $plan = Plan::find($user->plan_id);
            if ($plan) {
                $planName = $plan->name;
            }
        }
        $lines[] = "套餐: {$planName}";

        // 到期时间
        if ($user->expired_at) {
            $expiredStr = date('Y-m-d H:i:s', $user->expired_at);
            $isExpired = $user->expired_at <= time();
            $lines[] = "到期时间: {$expiredStr}" . ($isExpired ? '（已过期）' : '');
        } else {
            $lines[] = "到期时间: 永不过期";
        }

        // 流量用量
        $totalGB = round(($user->transfer_enable ?? 0) / 1024 / 1024 / 1024, 2);
        $usedGB = round($user->getTotalUsedTraffic() / 1024 / 1024 / 1024, 2);
        $remainGB = round($user->getRemainingTraffic() / 1024 / 1024 / 1024, 2);
        $lines[] = "流量: 已用 {$usedGB} GB / 总共 {$totalGB} GB（剩余 {$remainGB} GB）";

        // 账号状态
        if ($user->banned) {
            $lines[] = "状态: 已封禁";
        } elseif (!$user->isActive()) {
            $lines[] = "状态: 已过期/未订阅";
        } else {
            $lines[] = "状态: 正常";
        }

        return implode("\n", $lines);
    }

    // ========================================================
    // 会员验证
    // ========================================================

    /**
     * 检查用户是否已验证（telegram_id 已绑定活跃会员）
     */
    private function isUserVerified(int $tgUserId): bool
    {
        return User::where('telegram_id', $tgUserId)
            ->where('plan_id', '!=', null)
            ->where('banned', 0)
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', time());
            })
            ->exists();
    }

    /**
     * 首次联系 — 发起邮箱验证
     */
    private function startVerification(int $userId, int $chatId): void
    {
        Cache::put("tg_verify_{$userId}", [
            'source' => 'chat',
        ], self::VERIFY_TTL);

        $msg = "👋 欢迎联系 VP92 极速加速器！\n\n为了确认您的会员身份，请回复您在 VP92 注册时使用的【邮箱地址】。\n\n⏰ 请在 10 分钟内回复。";
        $this->telegram->sendMessage($chatId, $msg);
        $this->info("[验证] 用户 {$userId} 首次联系，已发送验证请求");
    }

    /**
     * 处理邮箱验证 — 用户回复了邮箱
     *
     * verifyState['source'] 可能是:
     *   'chat' — 首次联系验证
     *   'join' — 频道加入请求验证
     */
    private function handleEmailVerification(int $userId, int $chatId, string $text, array $verifyState): void
    {
        $email = strtolower(trim($text));
        $source = $verifyState['source'] ?? 'chat';

        // 清除验证状态
        Cache::forget("tg_verify_{$userId}");

        // 邮箱格式检查
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->telegram->sendMessage($chatId, '❌ 邮箱格式不正确，请重新输入您的注册邮箱。');
            // 重新进入验证状态
            Cache::put("tg_verify_{$userId}", ['source' => $source], self::VERIFY_TTL);
            return;
        }

        // 查找用户
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->telegram->sendMessage($chatId, "❌ 未找到邮箱 {$email} 对应的账号。\n\n请检查邮箱是否正确，或先在 VP92 官网注册。");
            return;
        }

        if (!$user->isActive()) {
            $reason = $user->banned ? '账号已被封禁' : '会员已过期';
            $this->telegram->sendMessage($chatId, "❌ 验证失败：{$reason}。\n\n请续费后重新联系。");
            return;
        }

        // 验证通过！保存 telegram_id
        $user->update(['telegram_id' => $userId]);
        $this->info("[验证] 用户 {$userId} 邮箱 {$email} 验证通过，已保存 telegram_id");

        if ($source === 'join') {
            // 频道加入请求验证 — 批准加入
            $targetChatId = $verifyState['chat_id'];
            try {
                $this->telegram->approveChatJoinRequest($targetChatId, $userId);
                $this->telegram->sendMessage($chatId, "✅ 验证通过！欢迎加入 VP92 会员频道！\n\n已绑定账号：{$email}");
            } catch (\Exception $e) {
                Log::error('批准加入请求失败', ['error' => $e->getMessage()]);
                $this->telegram->sendMessage($chatId, '✅ 验证通过！但加入频道时出错，请联系客服。');
            }
        } else {
            // 首次联系验证 — 自动邀请入频道 + 允许 AI 客服
            $this->telegram->sendMessage($chatId, "✅ 会员验证通过！\n\n已绑定账号：{$email}");
            $this->inviteToChannel($userId, $chatId);
        }
    }

    /**
     * 验证通过后自动邀请用户加入频道
     */
    private function inviteToChannel(int $userId, int $chatId): void
    {
        if ($this->discussChatId === null) {
            return;
        }

        try {
            // 尝试批准已有的加入请求（如果频道开启了审核）
            $this->telegram->approveChatJoinRequest($this->discussChatId, $userId);
            $this->telegram->sendMessage($chatId, '🎉 已为您开通会员频道访问权限！');
            $this->info("[频道] 已自动邀请用户 {$userId} 加入频道");
        } catch (\Exception $e) {
            // 如果没有待处理的加入请求，发频道链接让用户自己加
            $discussLink = admin_setting('telegram_discuss_link');
            if (!empty($discussLink)) {
                $this->telegram->sendMessage($chatId, "💬 您可以加入我们的会员频道：\n{$discussLink}");
            }
            $this->info("[频道] 用户 {$userId} 无待处理的加入请求，已发送频道链接");
        }
    }

    // ========================================================
    // 频道加入请求
    // ========================================================

    /**
     * 处理频道加入请求
     */
    private function processJoinRequest(array $joinRequest): void
    {
        $chatId = $joinRequest['chat']['id'] ?? null;
        $userId = $joinRequest['from']['id'] ?? null;
        $userName = $joinRequest['from']['first_name'] ?? '未知';
        $lastName = $joinRequest['from']['last_name'] ?? '';
        $joinRequestId = $joinRequest['chat_join_request_id'] ?? null;
        $tgUsername = $joinRequest['from']['username'] ?? '';

        if (!$chatId || !$userId || !$joinRequestId) {
            Log::warning('无效的 chat_join_request', $joinRequest);
            return;
        }

        $fullName = trim("$userName $lastName");
        $this->info("频道审核检用户 {$userId} ({$fullName}) 申请加入频道 {$chatId}");

        $verified = $this->isUserVerified($userId);

        // 记录所有加入请求到 Cache（供管理员AI查询）
        $this->recordJoinRequest($userId, $fullName, $tgUsername, $chatId, $verified);

        // 如果用户已验证，直接批准
        if ($verified) {
            try {
                $this->telegram->approveChatJoinRequest($chatId, $userId);
                $this->info("频道审核检用户 {$userId} 已验证，直接批准加入");
            } catch (\Exception $e) {
                Log::error('批准已验证用户加入失败', ['error' => $e->getMessage()]);
            }
            return;
        }

        // 未验证：发私信要求邮箱
        Cache::put("tg_verify_{$userId}", [
            'source' => 'join',
            'chat_id' => $chatId,
            'request_id' => $joinRequestId,
        ], self::VERIFY_TTL);

        try {
            $msg = "您好 {$fullName}！

您正在申请加入 VP92 会员专属频道。

请回复您在 VP92 注册时使用的【邮箱地址】来验证您的会员身份。

⏰ 请在 10 分钟内回复，否则需重新申请。";
            $this->telegram->sendMessage($userId, $msg);
            $this->info("频道审核检用户 {$userId} 已发送验证请求");
        } catch (\Exception $e) {
            Log::error('发送验证请求失败（用户可能未 start bot）', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            try {
                $this->telegram->declineChatJoinRequest($chatId, $userId);
                $this->info("频道审核检用户 {$userId} 未启动 bot，已拒绝");
            } catch (\Exception $e2) {
                Log::error('拒绝加入请求也失败', ['error' => $e2->getMessage()]);
            }
        }
    }

    /**
     * 记录频道加入请求到 Cache（供管理员AI的 recent_channel_joins 工具查询）
     */
    private function recordJoinRequest(int $telegramId, string $fullName, string $tgUsername, int $chatId, bool $verified): void
    {
        $joins = Cache::get('tg_recent_joins', []);
        // 避免重复（同一用户5分钟内不重复记录）
        $joins = array_filter($joins, fn($j) => !($j['telegram_id'] == $telegramId && ($j['time'] ?? 0) > time() - 300));
        $joins[] = [
            'telegram_id' => $telegramId,
            'username' => $tgUsername ?: $fullName,
            'full_name' => $fullName,
            'chat_id' => $chatId,
            'verified' => $verified,
            'time' => time(),
        ];
        // 最多保畅50条
        $joins = array_slice($joins, -50);
        Cache::put('tg_recent_joins', $joins, 86400); // 24小时
    }

    // ========================================================
    // 管理员公告
    // ========================================================

    /**
     * 处理 /announce 命令
     */
    private function handleAnnounce(int $userId, int $chatId, string $text): void
    {
        if ($this->discussChatId === null) {
            $this->telegram->sendMessage($chatId, '❌ 频道未配置，请联系管理员设置 telegram_discuss_id。');
            return;
        }

        // 验证是否为管理员
        $admin = User::where('telegram_id', $userId)->where('is_admin', 1)->first();
        if (!$admin) {
            $this->telegram->sendMessage($chatId, '❌ 仅管理员可以使用此命令。');
            return;
        }

        $content = trim(preg_replace('/^\/announce\s*/', '', $text));
        if (empty($content)) {
            $this->telegram->sendMessage($chatId, '请输入公告内容，格式：/announce <内容>');
            return;
        }

        $formatted = $this->formatAnnouncement($content, '系统通知');

        try {
            $this->telegram->sendMessage($this->discussChatId, $formatted, 'HTML');
            $this->telegram->sendMessage($chatId, "✅ 公告已发送到频道。\n\n预览：\n{$formatted}", 'HTML');
            $this->info("[公告] 管理员 {$userId} 发送公告到频道 {$this->discussChatId}");
        } catch (\Exception $e) {
            Log::error('发送公告失败', ['error' => $e->getMessage()]);
            $this->telegram->sendMessage($chatId, '❌ 发送公告失败，请检查频道配置。');
        }
    }

    // ========================================================
    // 管理员 AI 工具调用（DeepSeek Function Calling）
    // ========================================================

    /**
     * 管理员专属 AI 对话 — 带工具调用能力
     */
    private function adminAiChat(int|string $chatId, string $message): string
    {
        // v20.5: 先发 "typing" 状态，让用户感知 Bot 在工作
        TelegramChatAction::send($this->botToken, $chatId);

        $apiKey = env('DEEPSEEK_API_KEY', '');
        $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

        if (empty($apiKey)) {
            return '客服AI API Key 未配置。';
        }

        $systemPrompt = <<<'PROMPT'
你是 VP92极速加速器的管理员助手（通过客服Bot运行）。
你拥有发送公告到 Telegram 频道的工具。当管理员要求发公告、通知频道、告诉大家某事时，自动调用 send_channel_announce 工具。
根据公告内容自动判断分类：系统维护/版本更新选"系统通知"，优惠促销/限时活动选"活动公告"，紧急故障/安全提醒选"紧急通知"，其他选"系统通知"。
回复简洁专业，用中文。

【v15.16 增强信息面板 - 2026-08-27 admin SPA 注入】
当管理员问起 "📍 VP92 增强信息"、"v15.16 面板"、"用户详情面板" 时，按以下事实回答：

功能位置：admin SPA 的「用户详情」modal，点击用户列表 email 列触发模态框，在「订阅信息」卡片正下方自动注入 "📍 VP92 增强信息" 浮动面板。

面板内容（6 字段）：最后登录 IP / 注册 IP / 客户端+版本 / 注册地+最后登录（ip2region 5 段：中国/省/市/ISP）/ 📱 设备列表 / 📡 订阅记录。

v15.16 修复：cedar admin SPA kebab 只有「编辑」无「详情」→ 改点击 email 列打开 modal；panel 之前错位到 modal 末尾 → 用 div.rounded-lg.border.p-4 + startsWith('订阅信息') 精准定位；GeoIP ip2region off-by-one → parts[0,2,3,4]（parts[1] 是区域标记位总为 0）。

回复口径：这是 admin SPA 注入的浮动面板，不是 Xboard 原生功能，用于识别异常登录和批量注册。
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'model' => 'deepseek-v4-flash',
                'messages' => $messages,
                'tools' => $this->getAdminTools(),
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                Log::error('客服Bot 管理员 AI API 失败', ['status' => $response->status(), 'body' => $response->body()]);
                return "AI 调用失败 (HTTP {$response->status()})，请稍后再试。";
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

            // 执行工具调用
            foreach ($choice['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                $toolCallId = $toolCall['id'];

                $this->info("  🔧 管理员工具: {$functionName}(" . json_encode($arguments, JSON_UNESCAPED_UNICODE) . ")");

                $result = $this->executeAdminTool($functionName, $arguments);

                $this->info("  ✅ 工具结果: " . mb_substr($result, 0, 100));

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $result,
                ];
            }

            // 获取最终回复
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'model' => 'deepseek-v4-flash',
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];
                $content = $choice['content'] ?? '';
                if (empty($content) && !empty($choice['reasoning_content'])) {
                    $content = $choice['reasoning_content'];
                }
                return $content ?: '公告已发送。';
            }

            return '公告已发送，但获取确认回复失败。';
        } catch (\Throwable $e) {
            Log::error('客服Bot 管理员 AI 异常', ['error' => $e->getMessage()]);
            return 'AI 处理出错，请稍后再试。';
        }
    }

    /**
     * 管理员工具定义
     */
    private function getAdminTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_channel_announce',
                    'description' => '发送官方公告到 Telegram 频道。当管理员说"发公告"、"通知频道"、"告诉大家"、"发到频道"等时使用。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => '公告正文内容'],
                            'category' => ['type' => 'string', 'description' => '分类：系统通知/活动公告/紧急通知/版本更新，根据内容自动判断'],
                        ],
                        'required' => ['content'],
                    ],
                ],
            ],
        ];
    }

    /**
     * 执行管理员工具
     */
    private function executeAdminTool(string $name, array $arguments): string
    {
        return match ($name) {
            'send_channel_announce' => $this->toolSendAnnounce($arguments),
            default => "未知工具: {$name}",
        };
    }

    /**
     * 发送频道公告工具
     */
    private function toolSendAnnounce(array $args): string
    {
        $content = $args['content'] ?? '';
        if (empty($content)) {
            return '错误：公告内容不能为空。';
        }

        $category = $args['category'] ?? '系统通知';
        $allowedCategories = ['系统通知', '活动公告', '紧急通知', '版本更新'];
        if (!in_array($category, $allowedCategories)) {
            $category = '系统通知';
        }

        if ($this->discussChatId === null) {
            return '发送失败：频道未配置（telegram_discuss_id）。';
        }

        $formatted = $this->formatAnnouncement($content, $category);

        try {
            $this->telegram->sendMessage($this->discussChatId, $formatted, 'HTML');
            return "✅ 公告已发送到频道。\n\n预览：\n{$formatted}";
        } catch (\Throwable $e) {
            Log::error('客服Bot 发送频道公告失败', ['error' => $e->getMessage()]);
            return '❌ 发送失败，请检查频道配置。';
        }
    }

    /**
     * 排版公告为官方格式（HTML parse_mode）
     */
    private function formatAnnouncement(string $content, string $category = '系统通知'): string
    {
        $date = date('Y年n月j日');

        $config = match ($category) {
            '紧急通知' => [
                'emoji' => '🚨',
                'banner' => "⚠️  ⚠️  ⚠️",
                'line' => '🟥🟥🟥🟥🟥🟥🟥🟥🟥🟥🟥🟥',
            ],
            '活动公告' => [
                'emoji' => '🎉',
                'banner' => "✨  🎊  ✨",
                'line' => '🟨🟨🟨🟨🟨🟨🟨🟨🟨🟨🟨🟨',
            ],
            '版本更新' => [
                'emoji' => '🆕',
                'banner' => "🚀  ⬆️  🚀",
                'line' => '🟦🟦🟦🟦🟦🟦🟦🟦🟦🟦🟦🟦',
            ],
            default => [
                'emoji' => '📢',
                'banner' => "━━  VP92  ━━",
                'line' => '🟩🟩🟩🟩🟩🟩🟩🟩🟩🟩🟩🟩',
            ],
        };

        $emoji = $config['emoji'];
        $banner = $config['banner'];
        $line = $config['line'];

        return "{$banner}\n{$emoji} <b>VP92极速加速器 · {$category}</b>\n{$line}\n\n{$content}\n\n{$line}\n📅 {$date} ｜  🌐 https://vp92vip.com";
    }

    // ========================================================
    // 辅助方法
    // ========================================================

    private function getUserHistory(int $userId): array
    {
        $cached = Cache::get("tg_ai_history_{$userId}", []);
        return is_array($cached) ? $cached : [];
    }

    private function saveUserHistory(int $userId, string $userMsg, string $aiReply): void
    {
        $history = $this->getUserHistory($userId);
        $history[] = ['role' => 'user', 'content' => $userMsg];
        $history[] = ['role' => 'assistant', 'content' => $aiReply];

        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        Cache::put("tg_ai_history_{$userId}", $history, self::HISTORY_TTL);
    }

    private function deleteWebhook(): void
    {
        try {
            Http::timeout(10)->get($this->apiUrl . 'deleteWebhook');
            $this->info('Webhook 已删除，切换到 Long Polling 模式');
        } catch (\Exception $e) {
            $this->warn("删除 Webhook 失败: {$e->getMessage()}");
        }
    }

    private function stop(): void
    {
        $this->info('收到退出信号，停止 Long Polling...');
        exit(0);
    }
}
