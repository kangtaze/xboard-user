<?php

namespace App\Console\Commands;

use App\Services\EmailTrackingService;
use App\Services\SendWeeklyReportLogic;
use Illuminate\Console\Command;

/**
 * 用途: v20.4 周报自动推送 — 拼装上周营销 funnel + 推送到所有管理员 Telegram
 * 调用方: Kernel.php cron (周一 09:30 weekly) + 管理员手动测试 (--dry-run / --to=)
 * 被调用方: SendWeeklyReportLogic (纯逻辑) + Telegram sendMessage HTTP API
 * 关联记录: v20.4 plan §15 (2026-08-28)
 *
 * 关键决策:
 * - 复用 admin-ai Bot 现有 token (单一 Bot 实体，feed 干净)
 * - --dry-run 只 echo 不发；--to=ID 单点推送测试
 * - 默认推所有 is_admin=1 + telegram_id NOT NULL 的管理员
 */
class SendWeeklyReport extends Command
{
    protected $signature = 'vp92:send-weekly-report
                            {--days=7 : 窗口天数（默认 7 = 上周）}
                            {--dry-run : 只打印报告，不发 Telegram}
                            {--to= : 指定单个 chat_id 测试推送}';

    protected $description = 'v20.4 周报推送 — 拼装 5 tag funnel + Telegram 推送给所有管理员';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');
        $singleChatId = $this->option('to');

        $this->info("生成周报（窗口 {$days} 天）...");

        $tracker = new EmailTrackingService();
        $report = SendWeeklyReportLogic::buildReport($tracker, $days);

        $this->line($report);
        $this->line('');

        if ($isDryRun) {
            $this->info('[dry-run] 跳过 Telegram 推送');
            return 0;
        }

        // 推 Telegram
        $token = env('TELEGRAM_ADMIN_BOT_TOKEN', '');
        if (empty($token)) {
            $this->error('TELEGRAM_ADMIN_BOT_TOKEN 未配置');
            return 1;
        }

        if (!empty($singleChatId)) {
            // 单点测试
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            try {
                $resp = \Http::post($url, [
                    'chat_id' => $singleChatId,
                    'text' => $report,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                ]);
                if ($resp->successful()) {
                    $this->info("✅ 已推送到 chat_id={$singleChatId}");
                    return 0;
                }
                $this->error("❌ 推送失败: HTTP " . $resp->status());
                return 1;
            } catch (\Throwable $e) {
                $this->error('❌ 推送异常: ' . $e->getMessage());
                return 1;
            }
        }

        // 全管理员推送
        $count = SendWeeklyReportLogic::pushToTelegram($report, $token);
        $this->info("✅ 已推送给 {$count} 个管理员");

        return 0;
    }
}