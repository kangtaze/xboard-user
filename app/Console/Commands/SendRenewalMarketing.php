<?php

/**
 * 用途: 续费营销邮件发送命令 — 给已到期 VIP1/VIP2/VIP3 用户发续费提醒邮件 + 给已到期试用体验 (plan_id=9) 用户发升级引导邮件（含 7 天冷却去重 + --email 单发测试）
 * 调用方: 服务器 Cron (app/Console/Kernel.php 中 dailyAt 10:00)、Admin Bot 工具、手动 php artisan send:renewal-marketing
 * 被调用方: resources/views/mail/default/renewalMarketing.blade.php (VIP 续费模板)、resources/views/mail/default/trialMarketing.blade.php (试用升级模板)、Brevo SMTP API (https://api.brevo.com/v3/smtp/email)、v2_user 表 (查询到期用户)、v2_mail_log 表 (写入发送记录)
 * 关联记录: 首次入库 2026-08-31 (v20.8)，模板按 updateNotice/vip4/welcome 标杆样式（深蓝 #0f3460 + 红 #e94560 + 卡片化 + 编号区块 + 圆形步骤 badge）；v35 (2026-09-05) 加 plan_id=9 试用体验到期营销，引导升级到 VIP1/2/3（不引导 VIP4）
 */

namespace App\Console\Commands;

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

    public function handle(): int
    {
        $plans = [
            1 => ['name' => 'VIP1', 'monthly' => '19.90', 'quarterly' => '56', 'traffic' => '600GB', 'speed' => '1000Mbps'],
            2 => ['name' => 'VIP2', 'monthly' => '29.90', 'quarterly' => '85', 'traffic' => '1000GB', 'speed' => '2000Mbps'],
            3 => ['name' => 'VIP3', 'monthly' => '49.90', 'quarterly' => '142', 'traffic' => '2000GB', 'speed' => '3000Mbps'],
            9 => ['name' => '试用体验', 'monthly' => '0', 'quarterly' => '0', 'traffic' => '5GB', 'speed' => '500Mbps'],
        ];

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

            $html = $this->buildEmail($plan, $expiredDate, $daysExpired, $isTrial);

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

    private function buildEmail(array $plan, string $expiredDate, int $daysExpired, bool $isTrial = false): string
    {
        $template = $isTrial ? self::TEMPLATE_TRIAL : self::TEMPLATE_VIP;
        return view($template, [
            'plan' => $plan,
            'expired_date' => $expiredDate,
            'days_expired' => $daysExpired,
        ])->render();
    }
}
