<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserIntent;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 用途: 按 tag 群发意向邮件给用户（复用现有模板，7天冷却防骚扰）
 * 调用方: Kernel.php cron (4 tag 不同频率) + 管理员手动触发
 * 被调用方: MailService::sendEmail + v2_user_intent 表 (更新 last_mailed_at)
 * 关联记录: v19 plan Task 5 (2026-08-27)
 *
 * 模板映射说明:
 *   expiring_soon → remindExpire (Blade, 即将到期提醒)
 *   churned_warm  → renewalCampaign (Blade, 到期续费营销)
 *   intent_high   → notify (Blade, 新注册未付费引导)
 *   intent_cold   → notify (Blade, 老注册未付费引导)
 */
class SendIntentCampaign extends Command
{
    protected $signature = 'send:intent-campaign
                            {--tag= : 必填，tag 名称或 all}
                            {--dry : 预览模式，不发送}
                            {--force : 跳过确认}
                            {--chunk-size=50 : 每批处理数量}
                            {--delay=6 : 批次间延迟秒数}
                            {--cooldown-days=7 : 自定义冷却天数}
                            {--limit=0 : 限制发送数，0=不限}';

    protected $description = '按 tag 群发意向邮件给用户（复用现有模板）';

    // tag → 邮件模板映射
    private const TEMPLATE_MAP = [
        UserIntent::TAG_EXPIRING_SOON => 'remindExpire',
        UserIntent::TAG_CHURNED_WARM => 'renewalCampaign',
        UserIntent::TAG_INTENT_HIGH => 'notify',
        UserIntent::TAG_INTENT_COLD => 'notify',
        UserIntent::TAG_INTENT_OUTDATED => 'notify',  // v20.2: 复用 notify 模板
    ];

    // tag → 邮件主题
    private const SUBJECT_MAP = [
        UserIntent::TAG_EXPIRING_SOON => '您的 VP92 订阅即将到期 - 续费优惠提醒',
        UserIntent::TAG_CHURNED_WARM => 'VP92 全新升级 - 更快更稳更多节点，欢迎回来！',
        UserIntent::TAG_INTENT_HIGH => '解锁 VP92 全球加速 - 新用户专属优惠',
        UserIntent::TAG_INTENT_COLD => 'VP92 限时回归优惠 - 老用户专享',
        UserIntent::TAG_INTENT_OUTDATED => 'VP92 想您了 - 专属回归优惠等你领取',  // v20.2
    ];

    // tag → notify 模板的 content（仅 intent_high/intent_cold/intent_outdated 用）
    private const CONTENT_MAP = [
        UserIntent::TAG_INTENT_HIGH => "感谢您注册 VP92！\n\n我们注意到您还未体验我们的全球加速服务。\n\n当前版本：{{latest_version}}\n{{latest_features}}\n\n新用户首月仅需 ¥{{price}}，立即解锁：\n• 全球 50+ 高速节点\n• 智能分流，4K 流畅观看\n• 7×24 专属客服支持",
        UserIntent::TAG_INTENT_COLD => "好久不见！\n\nVP92 已全新升级，更多节点、更快连接、更稳定。\n\n当前版本：{{latest_version}}\n{{latest_features}}\n\n老用户专属回归优惠，续费立享折扣。\n{{promo_end_date}}\n\n立即查看：{{url}}",
        UserIntent::TAG_INTENT_OUTDATED => "您已经有一段时间没用了，VP92 想念您！\n\n自从您上次订阅，VP92 已大幅升级：\n• 节点数量翻倍，全球 50+ 国家覆盖\n• 连接速度提升 30%，4K 视频秒开\n• 新增多端同步，电脑/手机/平板无缝切换\n\n当前版本：{{latest_version}}\n{{latest_features}}\n\n作为曾经的 VIP 用户，您可享受专属回归折扣：\n{{promo_end_date}}\n\n立即查看：{{url}}",  // v20.2
    ];

    public function handle(): int
    {
        $tagOption = $this->option('tag');
        if (empty($tagOption)) {
            $this->error('--tag 必填。可选: ' . implode(', ', UserIntent::ALL_TAGS) . ', all');
            return 1;
        }

        $isDry = $this->option('dry');
        $isForce = $this->option('force');
        $chunkSize = max(10, min(500, (int) $this->option('chunk-size')));
        $delay = max(0, (int) $this->option('delay'));
        $cooldownDays = max(0, (int) $this->option('cooldown-days'));
        $limit = max(0, (int) $this->option('limit'));

        $tags = $tagOption === 'all' ? UserIntent::ALL_TAGS : [$tagOption];

        // 验证 tag
        foreach ($tags as $t) {
            if (!in_array($t, UserIntent::ALL_TAGS)) {
                $this->error("未知 tag: {$t}");
                return 1;
            }
        }

        $stats = ['sent' => 0, 'skipped_cooldown' => 0, 'skipped_invalid' => 0, 'failed' => 0];
        $startTime = microtime(true);

        // 公共模板变量
        $appUrl = admin_setting('app_url', 'https://vp92vip.com');
        $latestVersion = admin_setting('intent_latest_version', 'v1.2.6');
        $latestFeatures = admin_setting('intent_latest_features', '');
        $promoEndDate = admin_setting('intent_promo_end_date', '');

        foreach ($tags as $tag) {
            $this->info("=== 处理 tag: {$tag} ===");

            $cooldownTime = time() - $cooldownDays * 86400;
            $userIds = DB::table('v2_user_intent')
                ->where('tag', $tag)
                ->where(function ($q) use ($cooldownTime) {
                    $q->whereNull('last_mailed_at')
                      ->orWhere('last_mailed_at', '<', $cooldownTime);
                })
                ->pluck('user_id')
                ->toArray();

            if ($limit > 0) {
                $userIds = array_slice($userIds, 0, $limit);
            }

            $this->info("匹配用户: " . count($userIds));

            if (empty($userIds)) {
                continue;
            }

            if (!$isDry && !$isForce) {
                if (!$this->confirm("确定发送邮件给 " . count($userIds) . " 个用户吗？")) {
                    continue;
                }
            }

            if ($isDry) {
                // 仅打印前 10 个样本
                $samples = User::whereIn('id', array_slice($userIds, 0, 10))
                    ->select('id', 'email', 'plan_id', 'expired_at')
                    ->get();
                $this->table(['ID', 'Email', 'Plan', 'Expired'],
                    $samples->map(fn($u) => [
                        $u->id,
                        $u->email,
                        $u->plan_id ?? 'NULL',
                        $u->expired_at ? date('Y-m-d', $u->expired_at) : 'NULL',
                    ])->toArray()
                );
                continue;
            }

            // 分批发送
            $chunks = array_chunk($userIds, $chunkSize);
            $totalChunks = count($chunks);
            foreach ($chunks as $i => $chunk) {
                $users = User::whereIn('id', $chunk)->get();

                foreach ($users as $user) {
                    if ($user->banned || $user->is_admin || empty($user->email)) {
                        $stats['skipped_invalid']++;
                        continue;
                    }

                    // 构造模板变量
                    $templateValue = [
                        'name' => $user->name ?? '用户',
                        'url' => $appUrl,
                        'latest_version' => $latestVersion,
                        'latest_features' => $latestFeatures,
                        'promo_end_date' => $promoEndDate,
                    ];

                    // notify 模板需要 content 字段
                    if (in_array($tag, [UserIntent::TAG_INTENT_HIGH, UserIntent::TAG_INTENT_COLD, UserIntent::TAG_INTENT_OUTDATED])) {
                        $content = self::CONTENT_MAP[$tag] ?? '';
                        $content = str_replace(
                            ['{{latest_version}}', '{{latest_features}}', '{{promo_end_date}}', '{{url}}'],
                            [$latestVersion, $latestFeatures, $promoEndDate, $appUrl],
                            $content
                        );
                        $templateValue['content'] = $content;
                    }

                    try {
                        $result = MailService::sendEmail([
                            'email' => $user->email,
                            'subject' => self::SUBJECT_MAP[$tag] ?? 'VP92 通知',
                            'template_name' => self::TEMPLATE_MAP[$tag],
                            'template_value' => $templateValue,
                        ]);

                        if (empty($result['error'])) {
                            // 发送成功
                            DB::table('v2_user_intent')
                                ->where('user_id', $user->id)
                                ->where('tag', $tag)
                                ->update(['last_mailed_at' => time(), 'updated_at' => time()]);
                            $stats['sent']++;
                        } else {
                            $this->error("发送失败 user_id={$user->id}: " . $result['error']);
                            $stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->error("发送异常 user_id={$user->id}: " . $e->getMessage());
                        $stats['failed']++;
                    }
                }

                if ($delay > 0 && $i < $totalChunks - 1) {
                    sleep($delay);
                }

                $this->getOutput()->write(".");
            }
            $this->newLine();
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->table(['项目', '数量'], [
            ['✅ 成功发送', $stats['sent']],
            ['⏭️  冷却跳过', $stats['skipped_cooldown']],
            ['🚫 无效用户(banned/admin/无邮箱)', $stats['skipped_invalid']],
            ['❌ 发送失败', $stats['failed']],
            ['⏱️  耗时(秒)', $duration],
        ]);

        return 0;
    }
}