<?php

/**
 * 用途: 清理"刚过期"的试用体验用户 (plan_id=9) — 把 24h 内新过期的试用 plan_id 置 NULL,避免僵尸状态堆积
 * 调用方: app/Console/Kernel.php schedule (dailyAt 03:00 UTC = HK 11:00)
 * 被调用方: v2_user 表 (UPDATE plan_id=NULL WHERE plan_id=9 AND expired_at 在 24h 内新过期)
 * 关联记录: v57 trial 改 72h 后清理需求 — 防止 95% 过期试用用户堆积 plan_id=9 (僵尸)
 *
 * 铁律:
 *   - 仅清理 24h 内新过期 (windowStart = now - 86400)
 *   - 不碰老数据 (281 个已存在僵尸) — 用户 v57 决策"不 update 老数据"
 *   - 不动 expired_at (保留历史查询)
 *   - 不动 transfer_enable (用户还能登录看历史数据)
 *   - 仅处理 banned=0 + is_admin=0 (避开管理员和封禁用户)
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredTrials extends Command
{
    protected $signature = 'detect:trial-expired-cleanup
                            {--force : 跳过确认直接执行}
                            {--dry : 仅输出待清理用户，不实际 UPDATE}';

    protected $description = '清理 24h 内新过期的试用体验用户 plan_id=9 (避免僵尸堆积)';

    /**
     * 去重窗口: 只清理 24h 内新过期用户,老数据不碰
     */
    private const DEDUP_WINDOW = 86400;

    public function handle(): int
    {
        $now = time();
        $windowStart = $now - self::DEDUP_WINDOW;
        $tryOutPlanId = (int) admin_setting('try_out_plan_id', 9);

        // 1. 扫描 24h 内新过期的试用用户 (不碰老数据)
        $query = DB::table('v2_user')
            ->where('banned', 0)
            ->where('is_admin', 0)
            ->where('plan_id', $tryOutPlanId)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', $now)
            ->where('expired_at', '>=', $windowStart);

        $total = (clone $query)->count();
        $sample = (clone $query)->select('id', 'email', 'expired_at', 'plan_id')
            ->orderBy('expired_at', 'desc')
            ->limit(5)
            ->get();

        if ($total === 0) {
            $this->info('[detect:trial-expired-cleanup] no newly expired trial users in 24h window');
            return 0;
        }

        $this->info("[detect:trial-expired-cleanup] 找到 {$total} 个 24h 内新过期试用用户");
        $this->table(
            ['id', 'email', 'expired_at', 'plan_id'],
            $sample->map(fn ($u) => [
                $u->id,
                $u->email,
                date('Y-m-d H:i:s', $u->expired_at),
                $u->plan_id,
            ])->toArray()
        );

        if ($this->option('dry')) {
            $this->warn('（dry 模式，未实际执行 UPDATE）');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("确定要把这 {$total} 个用户的 plan_id 置 NULL 吗？")) {
            $this->warn('已取消');
            return 0;
        }

        // 2. 执行清理 (UPDATE plan_id=NULL WHERE 同样条件)
        $affected = $query->update([
            'plan_id' => null,
            'updated_at' => $now,
        ]);

        $this->info("[detect:trial-expired-cleanup] ✅ 已清理 {$affected} 个试用用户 plan_id=NULL");

        // 3. 输出清理后状态
        $remaining = DB::table('v2_user')
            ->where('plan_id', $tryOutPlanId)
            ->where('banned', 0)
            ->count();
        $this->info("[detect:trial-expired-cleanup] 剩余试用用户 (含僵尸): {$remaining}");

        return 0;
    }
}
