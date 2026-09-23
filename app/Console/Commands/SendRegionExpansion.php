<?php

/**
 * 用途: 新地区上线营销邮件发送命令 — 给已到期 VIP1/VIP2/VIP3 用户发 5 大新地区上线通知（香港/中国台湾/荷兰/韩国/法国），定位「加量不加价」品牌信任邮件（含 7 天冷却去重 + --email 单发测试）
 * 调用方: 服务器 Cron (app/Console/Kernel.php 中 dailyAt 11:00，与 send:renewal-marketing 错峰)、Admin Bot 工具、手动 php artisan send:region-expansion
 * 被调用方: resources/views/mail/default/regionExpansion.blade.php (5 地区上线模板)、Brevo SMTP API (https://api.brevo.com/v3/smtp/email)、v2_user 表 (查询到期用户)、v2_mail_log 表 (写入发送记录)
 * 关联记录: 首次入库 2026-09-23 (v48.2)，基于 SendRenewalMarketing.php 模板结构（深蓝 #0f3460 + 红 #e94560 + 卡片化 + FAQ 区块），主题「加量不加价,这次我们真没涨价」
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendRegionExpansion extends Command
{
    protected $signature = 'send:region-expansion
                            {--force : 跳过确认直接执行}
                            {--test : 仅输出待发送用户列表，不实际发送}
                            {--email= : 指定单发测试邮箱（仅发给该邮箱，跳过7天冷却）}';

    protected $description = '新地区上线营销邮件 — 给到期用户发送 5 大新地区通知（加量不加价主题）';

    private const TEMPLATE = 'mail.default.regionExpansion';
    private const COOLDOWN_DAYS = 7;

    public function handle(): int
    {
        $plans = [
            1 => ['name' => 'VIP1', 'price' => '19.90'],
            2 => ['name' => 'VIP2', 'price' => '29.90'],
            3 => ['name' => 'VIP3', 'price' => '49.90'],
        ];

        $testMode = $this->option('test');
        $cooldown = time() - (self::COOLDOWN_DAYS * 86400);
        $singleEmail = $this->option('email');

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
            // 查询到期用户（排除 plan_id=9 试用，因为试用营销走 trialMarketing 模板）
            // 排除冷却期内已发送 regionExpansion 模板的
            $users = DB::table('v2_user')
                ->where('expired_at', '<', time())
                ->where('banned', 0)
                ->whereNotNull('plan_id')
                ->whereIn('plan_id', [1, 2, 3])
                ->whereNotExists(function ($q) use ($cooldown) {
                    $q->select(DB::raw(1))
                      ->from('v2_mail_log')
                      ->whereColumn('v2_mail_log.email', 'v2_user.email')
                      ->where('template_name', self::TEMPLATE)
                      ->where('created_at', '>', $cooldown);
                })
                ->get();
        }

        if ($users->isEmpty()) {
            $this->info('没有需要发送新地区营销邮件的用户');
            return 0;
        }

        $this->table(['邮箱', '套餐', '到期日期', '已到期天数'], $users->map(function ($u) {
            return [
                $u->email,
                'VIP' . $u->plan_id,
                date('Y-m-d', $u->expired_at),
                floor((time() - $u->expired_at) / 86400) . '天',
            ];
        })->toArray());

        if ($testMode) {
            $this->warn('（测试模式，未实际发送）');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("确定要发送新地区邮件给 {$users->count()} 个用户吗？")) {
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

            $subject = '【VP92 极速加速器】5 大新地区上线 | 加量不加价,这次我们真没涨价';

            $html = view(self::TEMPLATE, [
                'plan' => $plan,
                'expired_date' => $expiredDate,
                'days_expired' => $daysExpired,
            ])->render();

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
                        'template_name' => self::TEMPLATE,
                        'error' => null,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ]);
                    $success++;
                } else {
                    $fail++;
                    Log::warning('新地区邮件发送失败', ['email' => $email, 'status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::error('新地区邮件异常', ['email' => $email, 'error' => $e->getMessage()]);
            }

            usleep(600000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ 发送完成！成功: {$success} | 失败: {$fail}");

        return 0;
    }
}