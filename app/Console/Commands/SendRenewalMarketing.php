<?php

/**
 * 用途: 续费营销邮件发送命令 — 给已到期 VIP1/VIP2/VIP3 用户发续费提醒邮件 + 给已到期试用体验 (plan_id=9) 用户发升级引导邮件（含 7 天冷却去重 + --email 单发测试）
 * 调用方: 服务器 Cron (app/Console/Kernel.php 中 dailyAt 10:00)、Admin Bot 工具、手动 php artisan send:renewal-marketing
 * 被调用方: resources/views/mail/default/renewalMarketing.blade.php (VIP 续费模板)、resources/views/mail/default/trialMarketing.blade.php (试用升级模板)、Brevo SMTP API (https://api.brevo.com/v3/smtp/email)、v2_user 表 (查询到期用户)、v2_plan 表 (实时读套餐配置,v60 改动态)、v2_mail_log 表 (写入发送记录)
 * 关联记录: 首次入库 2026-08-31 (v20.8)，模板按 updateNotice/vip4/welcome 标杆样式（深蓝 #0f3460 + 红 #e94560 + 卡片化 + 编号区块 + 圆形步骤 badge）；v35 (2026-09-05) 加 plan_id=9 试用体验到期营销，引导升级到 VIP1/2/3（不引导 VIP4）；v60 (2026-10-05) 改 plan 配置动态化（traffic/speed 一律走 v2_plan，删除硬编码 $plans 数组，trial + VIP1/2/3 自动同步）
 */

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendRenewalMarketing extends Command
{
    protected $signature = 'send:renewal-marketing
                            {--force : 跳过确认直接执行}
                            {--test : 仅输出待发送用户列表，不实际发送}
                            {--email= : 指定单发测试邮箱（仅发给该邮箱，跳过7天冷却）}';

    protected $description = '自动发送续费/升级营销邮件给到期用户（每日执行）';

    private const TEMPLATE_VIP = 'mail.default.renewalMarketing';     // VIP 续费模板（plan_id 1/2/3）
    private const TEMPLATE_TRIAL = 'mail.default.trialMarketing';     // 试用升级模板（plan_id 9）
    private const COOLDOWN_DAYS = 7; // 同一用户至少间隔7天才会再次收到

    /**
     * v60 — 从 v2_plan 实时读套餐配置（traffic/speed/devices）。
     * 月费/季费、续费赠送福利、推荐档位映射放在营销配置里（见 blade 默认值兜底）。
     * 价格不在 v2_plan（套餐价是后端单独设置），blade 默认值兜底用。
     *
     * @param int $planId
     * @param string $planName 'VIP1' / 'VIP2' / 'VIP3' / '试用体验'
     * @param string $monthly  月费（兜底用,因 v2_plan 无此字段）
     * @param string $quarterly 季费
     * @return array{name:string,monthly:string,quarterly:string,traffic:string,speed:string}|null
     */
    private function loadPlan(int $planId, string $planName, string $monthly, string $quarterly): ?array
    {
        $row = Plan::find($planId);
        if (!$row) {
            // v2_plan 没配置 → 返回 fallback,保留 v60 之前的硬编码文案
            return [
                'name' => $planName,
                'monthly' => $monthly,
                'quarterly' => $quarterly,
                'traffic' => '不限量',
                'speed' => '不限速',
            ];
        }
        $traffic = $row->transfer_enable !== null
            ? ((int)$row->transfer_enable . 'GB')
            : '不限量';
        $speed = $row->speed_limit !== null
            ? ((int)$row->speed_limit . 'Mbps')
            : '不限速';
        return [
            'name' => $planName,
            'monthly' => $monthly,
            'quarterly' => $quarterly,
            'traffic' => $traffic,
            'speed' => $speed,
        ];
    }

    public function handle(): int
    {
        // v60 — 删除硬编码 $plans 数组,改从 v2_plan 实时读。价格仍用兜底（v2_plan 无此字段）。
        // 4 个套餐的「价格」字段保留：1=VIP1/19.90/56、2=VIP2/29.90/85、3=VIP3/49.90/142、9=试用/0/0
        $plans = [
            1 => $this->loadPlan(1, 'VIP1', '19.90', '56'),
            2 => $this->loadPlan(2, 'VIP2', '29.90', '85'),
            3 => $this->loadPlan(3, 'VIP3', '49.90', '142'),
            9 => $this->loadPlan(9, '试用体验', '0', '0'),
        ];
        $plans = array_filter($plans); // 去掉 loadPlan 返回 null 的

        $testMode = $this->option('test');
        $cooldown = time() - (self::COOLDOWN_DAYS * 86400);
        $singleEmail = $this->option('email');

        // --email 单发测试模式：跳过 7 天冷却，仅发给指定邮箱
        if ($singleEmail) {
            $users = DB::table('v2_user')
                ->where('email', $singleEmail)
                ->where('banned', 0)
                ->get();
            if ($users->isEmpty()) {
                $this->error("用户 {$singleEmail} 不存在或被封禁");
                return 1;
            }
        } else {
            // 查询到期用户（排除冷却期内已发送的）
            // 注意：plan_id=9 与 VIP 1/2/3 用不同 template_name，cooldown 互不干扰
            $users = DB::table('v2_user')
                ->where('expired_at', '<', time())
                ->where('banned', 0)
                ->whereNotNull('plan_id')
                ->whereIn('plan_id', [1, 2, 3, 9])
                ->whereNotExists(function ($q) use ($cooldown) {
                    $q->select(DB::raw(1))
                      ->from('v2_mail_log')
                      ->whereColumn('v2_mail_log.email', 'v2_user.email')
                      ->whereIn('template_name', [self::TEMPLATE_VIP, self::TEMPLATE_TRIAL])
                      ->where('created_at', '>', $cooldown);
                })
                ->get();
        }

        if ($users->isEmpty()) {
            $this->info('没有需要发送续费营销邮件的用户');
            return 0;
        }

        $this->table(['邮箱', '套餐', '到期日期', '已到期天数'], $users->map(function ($u) {
            return [
                $u->email,
                $u->plan_id == 9 ? '试用体验' : ('VIP' . $u->plan_id),
                date('Y-m-d', $u->expired_at),
                floor((time() - $u->expired_at) / 86400) . '天',
            ];
        })->toArray());

        if ($testMode) {
            $this->warn('（测试模式，未实际发送）');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("确定要发送续费营销邮件给 {$users->count()} 个用户吗？")) {
            return 0;
        }

        $apiKey = env('BREVO_API_KEY');
        $fromName = env('MAIL_FROM_NAME', 'VP92');
        $fromEmail = env('MAIL_FROM_ADDRESS', 'noreply@mail.ferrari.cc.cd');

        $success = 0;
        $fail = 0;
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $plan = $plans[$user->plan_id] ?? null;
            if (!$plan) { $bar->advance(); continue; }

            $expiredDate = date('Y年m月d日', $user->expired_at);
            $daysExpired = floor((time() - $user->expired_at) / 86400);
            $email = $user->email;

            // plan_id=9 走试用升级模板，其他走 VIP 续费模板
            $isTrial = ($user->plan_id == 9);
            $template = $isTrial ? self::TEMPLATE_TRIAL : self::TEMPLATE_VIP;
            $subject = $isTrial
                ? '【VP92】您的试用体验已到期 — 升级付费套餐立省 1/3'
                : "【VP92】您的{$plan['name']}服务到期通知 — 续费方案";

            $html = $this->buildEmail($plan, $expiredDate, $daysExpired, $isTrial, $plans);

            try {
                $response = Http::withHeaders([
                    'api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['name' => $fromName, 'email' => $fromEmail],
                    'to' => [['email' => $email]],
                    'subject' => $subject,
                    'htmlContent' => $html,
                ]);

                if ($response->successful()) {
                    DB::table('v2_mail_log')->insert([
                        'email' => $email,
                        'subject' => $subject,
                        'template_name' => $template,
                        'error' => null,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ]);
                    $success++;
                } else {
                    $fail++;
                    Log::warning('续费营销邮件发送失败', ['email' => $email, 'plan_id' => $user->plan_id, 'status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::error('续费营销邮件异常', ['email' => $email, 'plan_id' => $user->plan_id, 'error' => $e->getMessage()]);
            }

            usleep(600000); // 600ms 间隔，Brevo 免费版限流
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ 发送完成！成功: {$success} | 失败: {$fail}");

        return 0;
    }

    /**
     * v60 — 试用升级模板额外传 $upgradePlans（VIP1/2/3 完整数据,来自 v2_plan）;
     * VIP 续费模板不需要。trialMarketing blade 用 $currentPlanTraffic/$currentPlanSpeed 显示当前套餐真实值。
     */
    private function buildEmail(array $plan, string $expiredDate, int $daysExpired, bool $isTrial = false, array $allPlans = []): string
    {
        $template = $isTrial ? self::TEMPLATE_TRIAL : self::TEMPLATE_VIP;

        $viewData = [
            'plan' => $plan,
            'expired_date' => $expiredDate,
            'days_expired' => $daysExpired,
        ];

        if ($isTrial) {
            // 当前过期用户的套餐（plan 9）已经走 $plan;trialMarketing 用 $currentPlanTraffic/$currentPlanSpeed 显示
            $viewData['currentPlanTraffic'] = $plan['traffic'];
            $viewData['currentPlanSpeed']   = $plan['speed'];
            // 给试用升级 blade 提供 VIP1/2/3 完整数据,替换硬编码 $paidPlans
            $upgradePlans = [];
            foreach ([1, 2, 3] as $i => $pid) {
                if (!isset($allPlans[$pid])) continue;
                $p = $allPlans[$pid];
                $tags = ['入门首选', '热销推荐', '重度用户'];
                $upgradePlans[] = [
                    'level' => $p['name'],
                    'tag' => $tags[$i],
                    'monthly' => $p['monthly'],
                    'quarterly' => $p['quarterly'],
                    'traffic' => $p['traffic'],
                    'speed' => $p['speed'],
                    'recommended' => ($pid === 2), // VIP2 是热销推荐
                ];
            }
            $viewData['upgradePlans'] = $upgradePlans;
        }

        return view($template, $viewData)->render();
    }
}
