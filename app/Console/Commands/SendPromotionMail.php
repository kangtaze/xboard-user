<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Models\User;
use Illuminate\Console\Command;

class SendPromotionMail extends Command
{
    protected $signature = 'send:promotion {--dry-run : 只显示将要发送的用户，不实际发送}';
    protected $description = '发送营销邮件给已到期用户（VIP1 月卡 ¥19.90 限时特惠）';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $appName = admin_setting('app_name', 'VP92极速加速器');
        $appUrl = admin_setting('app_url', 'https://vp92vip.com');

        // 查询已到期用户：expired_at < 当前时间，且有订阅过（group_id > 0），排除管理员和测试账号
        $users = User::where('expired_at', '<', now()->timestamp)
            ->where('group_id', '>', 0)
            ->where('is_admin', 0)
            ->where('banned', 0)
            ->get();

        // 排除明显的测试/内部邮箱
        $excluded = [
            'admin@demo.com',
            'ceshi@gmail.com',
            'ceshi2@gmail.com',
            'suppor@vp92vip.com',
            '29640944@qq.com',
        ];

        $filtered = $users->filter(function ($user) use ($excluded) {
            return !in_array($user->email, $excluded);
        });

        if ($filtered->isEmpty()) {
            $this->info('没有符合条件的已到期用户。');
            return self::SUCCESS;
        }

        $this->info(sprintf("找到 %d 个已到期用户（排除 %d 个测试/内部账号）", $filtered->count(), $users->count() - $filtered->count()));

        if ($dryRun) {
            $this->warn('=== DRY RUN 模式 - 不会实际发送邮件 ===');
            foreach ($filtered as $user) {
                $expiredDate = date('Y-m-d', $user->expired_at);
                $this->line("  {$user->email} | 到期: {$expiredDate} | 原套餐: VIP{$user->group_id}");
            }
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($filtered as $user) {
            dispatch(new SendEmailJob([
                'email'          => $user->email,
                'subject'        => "【{$appName}】VIP1 月卡特惠 ¥19.90 · 仅剩最后3天 ⚡",
                'template_name'  => 'promotion',
                'template_value' => [
                    'name' => $appName,
                    'url'  => $appUrl,
                ],
            ], 'send_email_mass'));
            $sent++;
        }

        $this->info("✅ 已成功入队 {$sent} 封营销邮件");
        $this->info("   模板: promotion");
        $this->info("   队列: send_email_mass");

        return self::SUCCESS;
    }
}
