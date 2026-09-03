<?php

// 用途：检测刚到期用户，按「试用 vs VIP」分流推送 notice 通知 APP 自动断开 VPN
// 调用方：app/Console/Kernel.php schedule() 调度（每分钟）
// 被调用方：App\Jobs\PushMessageJob（复用 Task #59 已修通的 push_message queue + Horizon supervisor）
// 关联记录：v1.2.7 订阅到期文案分类 + 自动断开（commit f9d0e08 Task #59 后端基础设施可复用）

namespace App\Console\Commands;

use App\Jobs\PushMessageJob;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Console\Command;

class DetectExpiredSubscriptions extends Command
{
    protected $signature = 'detect:expired-subscriptions';

    protected $description = '检测刚到期用户,推送 notice 通知 APP 自动断开 VPN（按试用/VIP 分流文案）';

    /**
     * 去重窗口：只推送 24h 内新到期的用户（窗口外的视为已收到过，不重复推送）
     */
    private const DEDUP_WINDOW = 86400;

    /**
     * 单 Job 最大用户数（与 MessageService::pushNoticeToAll 一致，避免单次 insert 过大）
     */
    private const CHUNK_SIZE = 500;

    public function handle(): void
    {
        $now = time();
        $windowStart = $now - self::DEDUP_WINDOW;

        // 1. 扫描刚到期用户（含关联 plan 一次性查，避免 N+1）
        $users = User::with('plan')
            ->where('banned', 0)
            ->where('is_admin', 0)
            ->whereNotNull('plan_id')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', $now)
            ->where('expired_at', '>=', $windowStart)
            ->select(['id', 'plan_id', 'expired_at'])
            ->get();

        if ($users->isEmpty()) {
            $this->info('[detect:expired] no newly expired users in window');
            return;
        }

        // 2. 按 plan 名称分组（试用 plan 单独一类，其他按 plan.name 分流 VIP1/VIP2/VIP3）
        $tryOutPlanId = (int) admin_setting('try_out_plan_id', 0);
        $trialUsers = [];
        $vipUsersByPlan = [];

        foreach ($users as $user) {
            if ($tryOutPlanId > 0 && (int) $user->plan_id === $tryOutPlanId) {
                $trialUsers[] = $user->id;
                continue;
            }
            $planName = $user->plan->name ?? '套餐';
            $vipUsersByPlan[$planName][] = $user->id;
        }

        // 3. 推送试用用户 notice
        if (!empty($trialUsers)) {
            $this->dispatchNotice(
                $trialUsers,
                '您的 VP92 体验套餐已到期',
                '开通套餐即可解锁完整节点，立即续费享受稳定服务。',
                [UserMessage::TAG_URGENT, UserMessage::TAG_POPUP]
            );
            $this->info("[detect:expired] trial users notified: " . count($trialUsers));
        }

        // 4. 按 plan name 分组推送 VIP 用户 notice
        foreach ($vipUsersByPlan as $planName => $userIds) {
            $this->dispatchNotice(
                $userIds,
                "您的 {$planName} 套餐已到期",
                '续费可继续畅享服务，立即续费恢复稳定连接。',
                [UserMessage::TAG_URGENT, UserMessage::TAG_POPUP]
            );
            $this->info("[detect:expired] {$planName} users notified: " . count($userIds));
        }

        $this->info("[detect:expired] total pushed: {$users->count()}");
    }

    /**
     * 分批派发 notice 到 push_message queue
     */
    private function dispatchNotice(array $userIds, string $title, string $content, array $tags): void
    {
        foreach (array_chunk($userIds, self::CHUNK_SIZE) as $chunk) {
            PushMessageJob::dispatch([
                'user_ids' => $chunk,
                'type' => UserMessage::TYPE_NOTICE,
                'title' => $title,
                'content' => $content,
                'ref_id' => null,
                'tags' => $tags,
            ]);
        }
    }
}