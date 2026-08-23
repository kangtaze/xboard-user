<?php

namespace App\Services;

use App\Models\User as UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\HttpClientException;

/**
 * AI 客服服务 - DeepSeek API 集成
 *
 * 处理与 DeepSeek AI 的交互，提供智能客服功能
 */
class AiChatService
{
    private string $apiUrl;
    private ?string $apiKey;
    private string $model;

    // 速率限制配置
    private const RATE_LIMIT_PER_USER = 50; // 每用户每小时最多 50 条消息
    private const RATE_LIMIT_WINDOW = 3600; // 1 小时窗口（秒）

    // 产品信息硬编码回退值（当 admin_setting 为空时使用，确保部署不中断）
    private const FALLBACK_APP_NAME = 'VP92极速加速器';
    private const FALLBACK_TRIAL_HOURS = 3;
    private const FALLBACK_WIN_VERSION = '1.0.9';
    private const FALLBACK_WIN_URL = 'https://vp92vip.cc.cd/windows/VP92-Setup-1.0.9.exe';
    private const FALLBACK_ANDROID_VERSION = '1.0.9';
    private const FALLBACK_ANDROID_URL = 'https://vp92vip.cc.cd/apk/vp92-1.0.9.apk';

    // 版本动态数据源（R2 上的版本检查文件，与客户端自动更新共用同一份）
    private const VERSION_JSON_ANDROID = 'https://vp92vip.cc.cd/version.json';
    private const VERSION_JSON_WINDOWS = 'https://vp92vip.cc.cd/version-windows.json';
    private const VERSION_INFO_CACHE_TTL = 1800; // 30 分钟缓存，发版后最迟半小时自动生效

    // System Prompt - 产品知识库 + 安全约束（占位符在 buildMessages() 中动态替换）
    private const SYSTEM_PROMPT = <<<PROMPT
你是 %APP_NAME% 的智能客服助手。你的职责是帮助用户解决产品使用问题。

【产品知识】
- 套餐：%TRIAL_INFO%，VIP1/2/3 不同流量和价格
- 连接：下载APP → 登录 → 选择节点 → 点击连接按钮
- 代理模式：全局代理（所有流量）、规则代理（国内直连国外代理）、直连（不代理）
- 核心技术：采用 VLESS + WebSocket 协议，结合最新端到端加密技术，通过全球分布式边缘节点加速，实现低延迟、高稳定性的安全连接。每个节点独立部署，智能路由确保最优连接路径
- 支持平台：Android、Windows（PC）
- 付款方式：支付宝、微信支付等
- 流量计算：上传+下载总和，套餐周期重置

【客户端下载地址】
当用户询问如何下载、在哪里下载、下载链接时，请提供以下信息（链接用 markdown 格式方便点击）：

%DOWNLOAD_LINKS%

【最新版本动态】
当用户询问"新版本"、"更新了什么"、"有什么新功能"、"要不要更新"时，基于以下真实发布信息回答（这是唯一事实来源，不要编造版本号和功能；如果此处没有内容，就回答不确定并建议联系人工客服确认）：

%VERSION_INFO%

转述风格要求（必须遵守）：
- 把发布说明翻译成普通用户的大白话，不要逐条复述，挑重点讲，最多 3~4 条，一条一句话
- 禁止出现技术术语（如"本地化""适配""兼容""异常处理""机制""场景""优化了XX功能"等），用户看不懂；要说结果和感受（更清晰、更稳定、更快、更省心）
- 每条都讲"对用户的实际好处"，而不是"我们做了什么工作"
- 语气自信克制，体现产品高级感，不要汇报工作的口吻
- 结尾轻引导一句：用着顺手可以继续使用，想体验新版可到官网下载，有问题随时找我

【使用教程】
当用户询问如何使用、怎么连接、怎么配置时，请提供教程链接（用 markdown 格式方便点击）：

完整使用教程（含 iOS 小火箭、Windows PC、Android 安卓三个平台的图文教程）：
https://vp92vip.com/vp92-tutorial.html

【下载和使用的回复示例】
用户问"怎么下载"时，回复格式如下（不要用星号分隔线，用编号列表保持整洁）：

%DOWNLOAD_REPLY_EXAMPLE%

【技术优势（对外宣传口径）】
- VLESS + WS 协议：最新一代代理协议，抗检测能力极强，连接稳定性远超传统协议
- 全球边缘节点加速：节点部署在全球各地边缘服务器，自动选择最近节点，延迟极低
- 端到端加密：采用军工级加密标准，所有数据传输全程加密，零泄露风险
- 智能分流：国内流量直连不走代理，国外流量自动加速，体验丝滑
- 无限设备：一个账号支持无限设备同时在线

【常见问题 - 高频问题优先回复】
- **节点不显示/看不到节点/订阅同步失败**：这是最常见问题！告诉用户：**退出账号 → 关闭APP → 重新打开APP → 重新登录账号**。这个操作能解决90%以上的节点相关问题，包括节点列表为空、连接异常、订阅获取失败等。
- **延迟测试不出结果/一直转圈**：告诉用户**多点击一次「延迟测试」按钮**，首次测试可能因节点握手超时无数据，第二次会正常返回。建议每次切换节点列表后都重新测一次。
- **移动5G/移动宽带用户延迟高/速度慢**：告诉用户**选择节点列表最下方的「移动节点」**，然后**连续测2-3次延迟**，选择延迟最低的那个节点使用。移动节点已针对中国移动骨干网优化，比默认节点快30%-50%。
- **无法连接（非节点问题）**：检查网络、检查套餐是否过期
- **速度慢（已选对运营商节点）**：切换到延迟更低的具体节点
- **账户问题**：找回密码用邮箱验证码
- **流量异常**：检查是否有其他设备在线，在账户页查看设备数

当用户说"用不了"、"连不上"、"看不到节点"、"没有节点"时，**首先**询问是否是节点不显示的问题，如果是则优先指导退出重登操作。

【网络选择引导】
当用户打招呼、询问如何选择节点、网络、连接时，主动提醒用户根据自身运营商选择对应节点：
- 电信用户：选择带有"电信"的节点（如：日本电信、美国电信、德国电信、新加坡电信）
- 移动用户：选择带有"移动"的节点（如：香港移动）
- 联通用户：选择带有"联通"的节点

节点名称格式为"地区+运营商"，例如"日本电信"表示该节点适合中国电信网络用户使用。选择匹配运营商的节点可以获得更快的速度和更稳定的连接。

用户打招呼时也可以简短提醒，例如："请问您是电信、移动还是联通用户？建议选择对应运营商的节点，速度更快更稳定哦！"

【严格保密 - 绝不透露】
- 数据库密码、API密钥、JWT密钥
- 服务器IP地址、SSH端口
- Cloudflare Worker 配置、KV存储结构
- Edgetunnel、CF Worker、边缘代理等内部技术实现名称
- 内部架构细节、技术实现代码
- 管理后台路径、后台操作方法
- 任何关于节点实际部署方式、DNS配置、Worker脚本的细节

如果用户询问技术细节、安全架构、内部配置，请回复：
"抱歉，这些是内部技术细节，我无法提供。如果您遇到使用问题，我可以帮您解决。"

【回答风格】
- 简洁明了，中文友好
- 排版整洁，用编号列表或换行分段，不要用星号、井号等特殊符号做分隔线
- 链接用 markdown 格式 [文字](URL) 方便用户点击
- 必要时给出步骤指导
- 无法回答的问题建议通过 Telegram 联系人工客服
- 当用户要求联系人工客服时，回复以下内容（排版整洁）：
  "您可以通过以下方式联系我们：
  1. Telegram 客服：[点击联系](https://t.me/vp92_01)
  2. Telegram 频道动态：[点击进入](https://t.me/vp92_High_Speed)
  客服会第一时间为您解答。"
PROMPT;

    // 推荐问题模板
    private const SUGGESTIONS = [
        "如何下载客户端？",
        "怎么连接使用？",
        "节点不显示怎么办？",
        "延迟测不出来？",
        "速度慢怎么办？",
        "联系人工客服",
    ];

    public function __construct()
    {
        $this->apiUrl = config('deepseek.api_url', 'https://api.deepseek.com/v1/chat/completions');
        $this->apiKey = config('deepseek.api_key');
        $this->model = config('deepseek.model', 'deepseek-v4-flash');

        if (empty($this->apiKey)) {
            Log::warning('DeepSeek API key not configured');
        }
    }

    /**
     * 聊天对话
     *
     * @param int $userId 用户ID
     * @param string $message 用户消息
     * @param array $history 对话历史（可选）
     * @param string|null $userContext 用户上下文信息（可选，追加到 system prompt）
     * @return array ['reply' => string, 'suggestions' => array]
     */
    public function chat(int $userId, string $message, array $history = [], ?string $userContext = null): array
    {
        // 检查 API Key
        if (empty($this->apiKey)) {
            Log::error('DeepSeek API key not configured');
            return [
                'reply' => 'AI 服务暂时不可用，请联系人工客服：https://t.me/vp92_01',
                'suggestions' => ['联系人工客服 https://t.me/vp92_01 →']
            ];
        }

        // 检查速率限制
        if (!$this->checkRateLimit($userId)) {
            Log::warning('Rate limit exceeded for user', ['user_id' => $userId]);
            return [
                'reply' => '您今天的对话次数已达上限，请联系人工客服：https://t.me/vp92_01',
                'suggestions' => ['联系人工客服 https://t.me/vp92_01 →']
            ];
        }

        // 构建 API 请求
        $messages = $this->buildMessages($userId, $message, $history, $userContext);

        try {
            $response = \Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false,
            ]);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('DeepSeek API error', [
                    'status' => $response->status(),
                    'error' => $error
                ]);
                return [
                    'reply' => 'AI 服务暂时不可用，请联系人工客服：https://t.me/vp92_01',
                    'suggestions' => ['联系人工客服 https://t.me/vp92_01 →']
                ];
            }

            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? '抱歉，我无法理解您的问题。';

            // 更新速率限制计数
            $this->incrementRateLimit($userId);

            // 根据回复内容智能推荐问题
            $suggestions = $this->generateSuggestions($message, $reply);

            return [
                'reply' => $reply,
                'suggestions' => $suggestions
            ];

        } catch (HttpClientException $e) {
            Log::error('DeepSeek HTTP client error', [
                'message' => $e->getMessage()
            ]);
            return [
                'reply' => '网络错误，请联系人工客服：https://t.me/vp92_01',
                'suggestions' => ['联系人工客服 https://t.me/vp92_01 →']
            ];
        } catch (\Exception $e) {
            Log::error('DeepSeek chat error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'reply' => 'AI 服务暂时不可用，请联系人工客服：https://t.me/vp92_01',
                'suggestions' => ['联系人工客服 https://t.me/vp92_01 →']
            ];
        }
    }

    /**
     * 构建消息列表（解析动态产品信息 + 追加用户上下文）
     */
    private function buildMessages(int $userId, string $userMessage, array $history, ?string $userContext = null): array
    {
        $prompt = self::SYSTEM_PROMPT;

        // ---- 1. 从数据库读取产品设置（admin_setting 已有 Redis + 请求内缓存） ----
        $appName = admin_setting('app_name', self::FALLBACK_APP_NAME) ?: self::FALLBACK_APP_NAME;
        $trialHours = (int) admin_setting('try_out_hour', self::FALLBACK_TRIAL_HOURS) ?: self::FALLBACK_TRIAL_HOURS;

        $winVer = admin_setting('windows_version', '') ?: '';
        $winUrl = admin_setting('windows_download_url', '') ?: '';
        $androidVer = admin_setting('android_version', '') ?: '';
        $androidUrl = admin_setting('android_download_url', '') ?: '';

        // 回退到硬编码值（确保部署时设置尚未填入也不中断）
        if (empty($winUrl)) {
            $winUrl = self::FALLBACK_WIN_URL;
            $winVer = self::FALLBACK_WIN_VERSION;
        }
        if (empty($androidUrl)) {
            $androidUrl = self::FALLBACK_ANDROID_URL;
            $androidVer = self::FALLBACK_ANDROID_VERSION;
        }

        // ---- 2. 替换简单占位符 ----
        $prompt = str_replace('%APP_NAME%', $appName, $prompt);
        $prompt = str_replace('%TRIAL_INFO%', "免费测试{$trialHours}小时", $prompt);

        // ---- 3. 构建下载地址段 ----
        $downloadLinks = '';
        if ($winUrl) {
            $label = "Windows PC 客户端" . ($winVer ? "（{$appName}{$winVer}）" : '') . "：";
            $downloadLinks .= $label . "\n" . $winUrl . "\n\n";
        }
        if ($androidUrl) {
            $label = "Android 安卓客户端" . ($androidVer ? "（{$appName}{$androidVer}）" : '') . "：";
            $downloadLinks .= $label . "\n" . $androidUrl . "\n\n";
        }
        $downloadLinks .= "iOS 客户端：\niOS 需要在 App Store 搜索下载「小火箭 Shadowrocket」，需要非中国大陆的 Apple ID（如香港、美国区账号）";

        // ---- 4. 构建回复示例段 ----
        $downloadExample = "您好！以下是 {$appName} 的下载地址：\n\n";
        $idx = 1;
        if ($winUrl) {
            $downloadExample .= "{$idx}. Windows PC 版下载：\n{$winUrl}\n\n";
            $idx++;
        }
        if ($androidUrl) {
            $downloadExample .= "{$idx}. Android 安卓版下载：\n{$androidUrl}\n\n";
            $idx++;
        }
        $downloadExample .= "{$idx}. iOS 用户请在 App Store 搜索「小火箭 Shadowrocket」（需要香港或美国区 Apple ID）\n\n";
        $downloadExample .= "下载安装后，打开 APP 注册登录即可使用。如需详细图文教程请访问：\nhttps://vp92vip.com/vp92-tutorial.html";

        $prompt = str_replace('%DOWNLOAD_LINKS%', $downloadLinks, $prompt);
        $prompt = str_replace('%DOWNLOAD_REPLY_EXAMPLE%', $downloadExample, $prompt);

        // ---- 5. 注入最新版本动态（R2 version.json，30 分钟缓存，失败则整段跳过） ----
        $prompt = str_replace('%VERSION_INFO%', $this->getVersionInfo(), $prompt);

        // ---- 6. 用户上下文：调用方未提供时自动按 userId 构建（让 AI"认识"登录客户） ----
        if (!$userContext && $userId > 0) {
            $userContext = $this->buildUserContext((int) $userId);
        }

        // ---- 7. 追加用户上下文（保留用户信息注入功能） ----
        if ($userContext) {
            $prompt .= "\n\n---\n【当前咨询客户信息】\n{$userContext}\n---\n"
                . "请根据以上客户信息提供个性化回复。如果客户询问与自己账号相关的问题"
                . "（如套餐、到期、流量），请直接引用上述信息回答，不要让客户提供，也不要反问客户。"
                . "\n打招呼/问候规则：如果客户只是打招呼（你好/在吗/hello），像老朋友一样热情迎接，"
                . "自然带上称呼和关怀（如提到套餐正常使用中、流量还充足），一句话即可，"
                . "然后主动问今天有什么可以帮忙的。禁止报菜名式罗列客户信息，禁止说\"根据系统显示\"这类话。";
        }

        // ---- 8. 组装消息数组 ----
        $messages = [
            ['role' => 'system', 'content' => $prompt]
        ];

        // 添加历史对话（限制最近 5 条以节省 token）
        $recentHistory = array_slice($history, -5);
        foreach ($recentHistory as $item) {
            $messages[] = [
                'role' => $item['role'],
                'content' => $item['content']
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    /**
     * 构建登录用户的个人上下文（供 system prompt 注入，让 AI"认识"客户）
     *
     * 与 TelegramAiPoll::buildUserContext() 同口径：邮箱/套餐/到期/流量/状态。
     * 任何字段缺失时跳过该行，用户不存在或查询异常返回 null（AI 按无上下文正常回答）。
     */
    private function buildUserContext(int $userId): ?string
    {
        try {
            $user = UserModel::find($userId);
            if (!$user) {
                return null;
            }

            $lines = [];

            if ($user->email) {
                $lines[] = "邮箱: {$user->email}";
            }

            $planName = '无套餐';
            if ($user->plan_id) {
                $plan = \App\Models\Plan::find($user->plan_id);
                if ($plan) {
                    $planName = $plan->name;
                }
            }
            $lines[] = "套餐: {$planName}";

            if ($user->expired_at) {
                $expiredStr = date('Y-m-d H:i:s', $user->expired_at);
                $isExpired = $user->expired_at <= time();
                $lines[] = "到期时间: {$expiredStr}" . ($isExpired ? '（已过期）' : '');
            } else {
                $lines[] = "到期时间: 永不过期";
            }

            $totalGB = round(($user->transfer_enable ?? 0) / 1024 / 1024 / 1024, 2);
            $usedGB = round($user->getTotalUsedTraffic() / 1024 / 1024 / 1024, 2);
            $remainGB = round($user->getRemainingTraffic() / 1024 / 1024 / 1024, 2);
            $lines[] = "流量: 已用 {$usedGB} GB / 总共 {$totalGB} GB（剩余 {$remainGB} GB）";

            if ($user->banned) {
                $lines[] = "状态: 已封禁";
            } elseif (!$user->isActive()) {
                $lines[] = "状态: 已过期/未订阅";
            } else {
                $lines[] = "状态: 正常";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('AiChatService buildUserContext failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取最新版本动态（供 system prompt 注入）
     *
     * 数据源为 R2 上的 version.json / version-windows.json（与客户端自动更新共用同一份），
     * 30 分钟缓存 —— 客户端发版上传后，AI 客服最迟半小时内自动同步，无需手动维护。
     * 任一数据源拉取失败或格式异常时跳过该平台，全部失败返回空串（prompt 中该段留空，
     * 风格规则会引导 AI 回答"不确定"而不是编造）。
     */
    private function getVersionInfo(): string
    {
        $sections = [];

        $android = $this->fetchVersionChangelog(self::VERSION_JSON_ANDROID);
        if ($android !== null) {
            $changelog = trim($android['changelog'] ?? '');
            if ($changelog !== '') {
                // 发布说明原文注入，由 prompt 中的风格规则引导 AI 转述成用户语言
                $sections[] = "Android 最新版本：V{$android['version']}（官方发布说明原文，仅供你理解参考，回答时要按上面的风格要求转述）：\n{$changelog}";
            }
        }

        $windows = $this->fetchVersionChangelog(self::VERSION_JSON_WINDOWS);
        if ($windows !== null) {
            $changelog = trim($windows['changelog'] ?? '');
            if ($changelog !== '') {
                $sections[] = "Windows 最新版本：V{$windows['version']}（官方发布说明原文，仅供你理解参考，回答时要按上面的风格要求转述）：\n{$changelog}";
            }
        }

        return implode("\n\n", $sections);
    }

    /**
     * 拉取单个版本 JSON 并提取版本号 + changelog
     *
     * @return array{version: string, changelog: string}|null 失败或格式异常返回 null
     */
    private function fetchVersionChangelog(string $url): ?array
    {
        try {
            $json = Cache::remember(
                'ai_chat_version_info_' . md5($url),
                self::VERSION_INFO_CACHE_TTL,
                function () use ($url) {
                    $response = \Http::timeout(5)->get($url);
                    if (!$response->successful()) {
                        return null;
                    }
                    $data = $response->json();
                    if (!is_array($data) || empty($data['version'])) {
                        return null;
                    }
                    return [
                        'version' => (string) $data['version'],
                        'changelog' => (string) ($data['changelog'] ?? ''),
                    ];
                }
            );
        } catch (\Throwable $e) {
            Log::warning('AiChatService fetch version info failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        return is_array($json) ? $json : null;
    }

    /**
     * 检查用户速率限制
     */
    private function checkRateLimit(int $userId): bool
    {
        $cacheKey = "ai_chat_rate_limit_{$userId}";
        $count = Cache::get($cacheKey, 0);
        return $count < self::RATE_LIMIT_PER_USER;
    }

    /**
     * 增加用户速率限制计数
     */
    private function incrementRateLimit(int $userId): void
    {
        $cacheKey = "ai_chat_rate_limit_{$userId}";
        Cache::increment($cacheKey, 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * 根据对话内容生成推荐问题
     */
    private function generateSuggestions(string $userMessage, string $reply): array
    {
        // 简单关键词匹配来推荐问题
        $messageLower = mb_strtolower($userMessage);

        // 连接相关问题
        if (str_contains($messageLower, '连接') || str_contains($messageLower, '连不上')) {
            return ["如何切换节点？", "无法连接怎么办？", "联系人工客服"];
        }

        // 节点相关问题
        if (str_contains($messageLower, '节点') || str_contains($messageLower, '服务器')) {
            return ["如何切换节点？", "哪个节点速度快？", "联系人工客服"];
        }

        // 流量相关问题
        if (str_contains($messageLower, '流量') || str_contains($messageLower, '套餐')) {
            return ["流量怎么算？", "怎么查看剩余流量？", "联系人工客服"];
        }

        // 账号相关问题
        if (str_contains($messageLower, '密码') || str_contains($messageLower, '账号') || str_contains($messageLower, '登录')) {
            return ["忘记密码怎么办？", "怎么找回账号？", "联系人工客服"];
        }

        // 默认推荐
        return array_slice(self::SUGGESTIONS, 0, 3);
    }
}