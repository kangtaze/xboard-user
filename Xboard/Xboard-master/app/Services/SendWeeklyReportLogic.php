<?php

namespace App\Services;

/**
 * 用途: v20.4 周报推送 — 纯逻辑层（拼报告 + 推送调度），与 Laravel Command 解耦
 * 调用方: SendWeeklyReport Command (handle() 调用本类的 buildReport / pushToTelegram)
 * 被调用方: EmailTrackingService (聚合 funnel) + Illuminate\Support\Facades\Http (Telegram sendMessage)
 * 关联记录: v20.4 plan §15 (2026-08-28)
 *
 * 关键决策:
 * - 抽出 Logic 类让单测可直接验证（不依赖 Laravel Command / artisan 框架）
 * - 测试用 stub (SendWeeklyReportTest::SendWeeklyReportStub) 包装本类
 * - pushToTelegram() 返回成功推送数（便于 --dry-run 计数）
 * - 失败用 try/catch 静默（一个管理员推送失败不影响其他人）
 */
class SendWeeklyReportLogic
{
    /**
     * 构建周报 Markdown 消息
     *
     * @param EmailTrackingService $tracker
     * @param int $days 窗口天数（默认 7 = 上周）
     * @return string
     */
    public static function buildReport(EmailTrackingService $tracker, int $days = 7): string
    {
        $rows = [];
        foreach (\App\Models\UserIntent::ALL_TAGS as $tag) {
            $rows[$tag] = $tracker->funnel($tag, $days);
        }

        $best = $tracker->bestTagByRoi($days);

        $now = time();
        $windowStart = $now - $days * 86400;
        $startStr = date('Y-m-d', $windowStart);
        $endStr = date('Y-m-d', $now);

        $lines = [];
        $lines[] = "📊 *VP92 营销周报*";
        $lines[] = "_时间: {$startStr} ~ {$endStr}_";
        $lines[] = "";

        foreach (\App\Models\UserIntent::ALL_TAGS as $tag) {
            $r = $rows[$tag];
            $openPct = number_format((float) $r['open_rate'], 1) . '%';
            $clickPct = number_format((float) $r['click_rate'], 1) . '%';
            $lines[] = sprintf(
                "`%-16s`: mailed=%d opened=%d(%s) click=%d(%s) paid=%d",
                $tag,
                $r['mailed'],
                $r['opened'],
                $openPct,
                $r['clicked'],
                $clickPct,
                $r['paid']
            );
        }

        $lines[] = "";

        // 估算 GMV: paid * 平均客单价 ¥45（参考 VP92 主流套餐均价）
        $totalPaid = array_sum(array_column($rows, 'paid'));
        $estGmv = $totalPaid * 45;
        $lines[] = "💰 估算 GMV: ¥{$estGmv}";

        if (!empty($best['tag']) && $best['mailed'] > 0) {
            $bestRate = number_format((float) $best['paid_rate'], 1) . '%';
            $lines[] = "🏆 最佳 tag: `{$best['tag']}` ({$bestRate} 转化)";
        } else {
            $lines[] = "🏆 最佳 tag: 暂无数据";
        }

        return implode("\n", $lines);
    }

    /**
     * 推送给所有 is_admin=1 且 telegram_id IS NOT NULL 的用户
     *
     * @param string $message
     * @param string $botToken Telegram bot token
     * @param bool $dryRun true = 只统计不发
     * @return int 实际推送成功的用户数
     */
    public static function pushToTelegram(string $message, string $botToken, bool $dryRun = false): int
    {
        $admins = \DB::table('v2_user')
            ->where('is_admin', 1)
            ->whereNotNull('telegram_id')
            ->pluck('telegram_id');

        if ($dryRun) {
            return 0;
        }

        $success = 0;
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        foreach ($admins as $chatId) {
            try {
                $resp = \Http::post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                ]);

                if ($resp->successful()) {
                    $success++;
                }
            } catch (\Throwable $e) {
                // 单个失败不阻塞其他人
                continue;
            }
        }

        return $success;
    }
}