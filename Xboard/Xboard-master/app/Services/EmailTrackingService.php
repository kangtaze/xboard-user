<?php

namespace App\Services;

use App\Models\EmailEventLog;
use App\Models\MailLog;
use App\Models\UserIntent;
use Illuminate\Support\Facades\DB;

/**
 * 用途: v20 funnel 报表核心 — 聚合 mailed/opened/clicked/paid 漏斗
 * 调用方: TelegramAdminAiPoll (4 tool: funnel_report/open_rate/intent_stats/help) + v20.4 SendWeeklyReport
 * 被调用方: v2_mail_log + v2_email_event_log + v2_user_intent + v2_order (只读)
 * 关联记录: v20 plan §7.3 (2026-08-28)
 *
 * 关键决策:
 * - paid 状态 status IN(1,3) — 已支付未发货 + 已支付已发货
 * - paid_at 必须在窗口内（用户可能早期下过单）
 * - DISTINCT mail_log_id 防止同邮件多次 open/click 被重复计数
 * - 全程纯 SQL，无 JSON_EXTRACT（SQLite 不支持）
 */
class EmailTrackingService
{
    /** v2_order 视为"已支付"的状态码 */
    private const PAID_STATUSES = [1, 3];

    /**
     * 单 tag 漏斗
     *
     * @param string $tag    UserIntent::TAG_*
     * @param int    $days  窗口天数（默认 7 = 一周）
     * @return array{tag:string, mailed:int, opened:int, clicked:int, paid:int,
     *               open_rate:float, click_rate:float, paid_rate:float}
     */
    public function funnel(string $tag, int $days = 7): array
    {
        $now = time();
        $windowStart = $now - $days * 86400;

        // mailed: 该 tag 用户在窗口内成功的发送
        $mailed = (int) DB::table('v2_mail_log')
            ->join('v2_user_intent', 'v2_user_intent.user_id', '=', 'v2_mail_log.user_id')
            ->where('v2_user_intent.tag', $tag)
            ->whereNull('v2_mail_log.error')
            ->where('v2_mail_log.created_at', '>=', $windowStart)
            ->where('v2_mail_log.created_at', '<=', $now)
            ->distinct()
            ->count('v2_mail_log.id');

        // 拿到 mailed 的 mail_log_ids 子查询（funnel 其他 stage 共享）
        $mailedLogIds = DB::table('v2_mail_log')
            ->join('v2_user_intent', 'v2_user_intent.user_id', '=', 'v2_mail_log.user_id')
            ->where('v2_user_intent.tag', $tag)
            ->whereNull('v2_mail_log.error')
            ->where('v2_mail_log.created_at', '>=', $windowStart)
            ->where('v2_mail_log.created_at', '<=', $now)
            ->pluck('v2_mail_log.id');

        // opened: 这批邮件中至少被 open 一次的 DISTINCT 邮件数
        $opened = 0;
        $clicked = 0;
        if ($mailedLogIds->isNotEmpty()) {
            $opened = (int) DB::table('v2_email_event_log')
                ->whereIn('mail_log_id', $mailedLogIds)
                ->where('event_type', EmailEventLog::EVENT_OPEN)
                ->where('occurred_at', '>=', $windowStart)
                ->distinct()
                ->count('mail_log_id');

            $clicked = (int) DB::table('v2_email_event_log')
                ->whereIn('mail_log_id', $mailedLogIds)
                ->where('event_type', EmailEventLog::EVENT_CLICK)
                ->where('occurred_at', '>=', $windowStart)
                ->distinct()
                ->count('mail_log_id');
        }

        // paid: 这批用户中在窗口内已支付的 DISTINCT user 数
        $paidUserIds = DB::table('v2_user_intent')
            ->where('tag', $tag)
            ->pluck('user_id');

        $paid = 0;
        if ($paidUserIds->isNotEmpty()) {
            $paid = (int) DB::table('v2_order')
                ->whereIn('user_id', $paidUserIds)
                ->whereIn('status', self::PAID_STATUSES)
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $windowStart)
                ->where('paid_at', '<=', $now)
                ->distinct()
                ->count('user_id');
        }

        return [
            'tag' => $tag,
            'mailed' => $mailed,
            'opened' => $opened,
            'clicked' => $clicked,
            'paid' => $paid,
            'open_rate' => $mailed > 0 ? round($opened / $mailed * 100, 1) : 0.0,
            'click_rate' => $opened > 0 ? round($clicked / $opened * 100, 1) : 0.0,
            'paid_rate' => $mailed > 0 ? round($paid / $mailed * 100, 1) : 0.0,
        ];
    }

    /**
     * 全 tag 打开率表（每个 tag 一行）
     *
     * @return array<int, array{tag:string, mailed:int, opened:int, clicked:int, open_rate:float, click_rate:float}>
     */
    public function openRate(int $days = 7): array
    {
        $rows = [];
        foreach (UserIntent::ALL_TAGS as $tag) {
            $f = $this->funnel($tag, $days);
            $rows[] = [
                'tag' => $tag,
                'mailed' => $f['mailed'],
                'opened' => $f['opened'],
                'clicked' => $f['clicked'],
                'open_rate' => $f['open_rate'],
                'click_rate' => $f['click_rate'],
            ];
        }
        return $rows;
    }

    /**
     * 5 tag 用户统计 + 最后发邮件时间 + 7d 转化
     *
     * @return array{user_counts: array<string,int>, last_mailed_at: array<string,int|null>, conversion_7d: array<string,int>}
     */
    public function intentStats(int $days = 7): array
    {
        $now = time();
        $windowStart = $now - $days * 86400;

        $userCounts = [];
        $lastMailed = [];
        $conversion7d = [];

        foreach (UserIntent::ALL_TAGS as $tag) {
            // 当前 tag 用户数
            $userCounts[$tag] = (int) DB::table('v2_user_intent')
                ->where('tag', $tag)
                ->count();

            // 该 tag 最近一次发邮件时间
            $last = DB::table('v2_mail_log')
                ->join('v2_user_intent', 'v2_user_intent.user_id', '=', 'v2_mail_log.user_id')
                ->where('v2_user_intent.tag', $tag)
                ->whereNull('v2_mail_log.error')
                ->max('v2_mail_log.created_at');
            $lastMailed[$tag] = $last !== null ? (int) $last : null;

            // 该 tag 用户 7 天内 paid 数
            $tagUserIds = DB::table('v2_user_intent')
                ->where('tag', $tag)
                ->pluck('user_id');

            $conversion7d[$tag] = 0;
            if ($tagUserIds->isNotEmpty()) {
                $conversion7d[$tag] = (int) DB::table('v2_order')
                    ->whereIn('user_id', $tagUserIds)
                    ->whereIn('status', self::PAID_STATUSES)
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', $windowStart)
                    ->distinct()
                    ->count('user_id');
            }
        }

        return [
            'user_counts' => $userCounts,
            'last_mailed_at' => $lastMailed,
            'conversion_7d' => $conversion7d,
        ];
    }

    /**
     * 找 ROI 最高的 tag（按 paid_rate = paid/mailed 排序）
     *
     * @return array{tag:string, mailed:int, paid:int, paid_rate:float}
     */
    public function bestTagByRoi(int $days = 7): array
    {
        $best = ['tag' => '', 'mailed' => 0, 'paid' => 0, 'paid_rate' => 0.0];

        foreach (UserIntent::ALL_TAGS as $tag) {
            $f = $this->funnel($tag, $days);
            // 必须 mailed > 0 才能谈 ROI
            if ($f['mailed'] > 0 && $f['paid_rate'] > $best['paid_rate']) {
                $best = [
                    'tag' => $f['tag'],
                    'mailed' => $f['mailed'],
                    'paid' => $f['paid'],
                    'paid_rate' => $f['paid_rate'],
                ];
            }
        }

        return $best;
    }
}