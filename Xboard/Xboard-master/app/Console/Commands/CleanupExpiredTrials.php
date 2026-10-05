<?php

/**
 * 用途: 列出"刚过期"的试用体验用户 (plan_id=9) — 仅供人工查看过期清单 (--dry 默认)
 * 调用方: 人工手动 php artisan detect:trial-expired-cleanup --dry (已撤自动 cron)
 * 被调用方: v2_user 表 (SELECT 不 UPDATE)
 * 关联记录: v59 教训 — plan_id=9 是用户**试用身份标识**,不是"当前在用套餐",升级靠 OrderService 把 plan_id 改 1/2/3,不该清成 NULL。
 *          v59 自动 cron 已被用户撤掉 (2026-10-05):17 个 24h 内新过期用户 plan_id=NULL 后导致 OrderService 升级分支走错路 (plan_id !== NULL 判定失败)。
 *          还原后 17 用户 plan_id=9 全部恢复;cron schedule 注释在 app/Console/Kernel.php:46。
 *
 * 铁律 (2026-10-05 用户决策):
 *   - plan_id=9 = 试用身份,过期不清,升级自动改 1/2/3
 *   - "僵尸堆积"是预期状态,不是 bug
 *   - 此命令仅 --dry 输出过期清单供老板查看,永远不 UPDATE
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredTrials extends Command
{
    protected $signature = 'detect:trial-expired-cleanup
                            {--force : 跳过确认直接执行}
                            {--dry : 仅输出待清理用户，不实际 UPDATE}';

    protected $description = '⚠️ 已撤回:仅 --dry 输出过期试用用户清单 (UPDATE 已禁用,v59 教训)';

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

        if (!$this->option('dry')) {
            $this->error('⛔ v59 已撤回 — UPDATE 路径已禁用,此命令仅供 --dry 输出过期清单');
            $this->warn('如确需批量修改 plan_id,必须先找用户拍板确认业务影响后再执行');
            return 1;
        }

        $this->info('[detect:trial-expired-cleanup] --dry 模式,仅输出过期清单:');
        $this->info("  total_plan_9_now:    " . DB::table('v2_user')->where('plan_id', $tryOutPlanId)->where('banned', 0)->count());
        $this->info("  active (试用期内):   " . DB::table('v2_user')->where('plan_id', $tryOutPlanId)->where('banned', 0)->where('expired_at', '>', $now)->count());
        $this->info("  expired (已过期):    " . DB::table('v2_user')->where('plan_id', $tryOutPlanId)->where('banned', 0)->where('expired_at', '<', $now)->count());

        return 0;
    }
}
