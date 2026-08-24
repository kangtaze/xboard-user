<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendUpdateNotice extends Command
{
    protected $signature = 'send:update-notice
                            {--dry : 预览模式，不发送邮件}
                            {--chunk-size=50 : 每批处理数量}
                            {--delay=6 : 批次间延迟秒数}
                            {--force : 跳过确认}';

    protected $description = '群发版本更新通知邮件给所有用户（含已到期，封禁不发）';

    public function handle(): int
    {
        $chunkSize = max(10, min(500, (int) $this->option('chunk-size')));
        $delay = max(0, (int) $this->option('delay'));

        $baseQuery = User::select('id', 'email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('is_admin', false)
            ->where('banned', false);

        $totalUsers = (clone $baseQuery)->count();

        if ($totalUsers === 0) {
            $this->warn('没有符合条件的用户');
            return 0;
        }

        // 信息表
        $batches = (int) ceil($totalUsers / $chunkSize);
        $estimatedMinutes = $delay > 0 ? round(($batches - 1) * $delay / 60, 1) : 0;

        $this->table(['项目', '值'], [
            ['筛选模式', '所有用户（is_admin=false AND banned=false，含已到期）'],
            ['符合条件的用户', number_format($totalUsers)],
            ['每批数量', $chunkSize],
            ['批次间延迟', $delay > 0 ? "{$delay} 秒" : '无'],
            ['预计批次', $batches],
            ['预计耗时', $estimatedMinutes > 0 ? "约 {$estimatedMinutes} 分钟" : '无延迟'],
        ]);

        // Dry-run 模式
        if ($this->option('dry')) {
            $this->newLine();
            $this->info('--- 预览模式（不会发送邮件）---');
            $this->newLine();

            $sampleEmails = $baseQuery->limit(10)->pluck('email')->toArray();
            $this->table(['#', '邮箱'], collect($sampleEmails)->map(fn($e, $i) => [$i + 1, $e])->values()->toArray());

            $subject = $this->buildSubject();
            $this->newLine();
            $this->line("邮件主题: <info>{$subject}</info>");
            $this->line("模板名称: <info>updateNotice</info>");
            $this->line("下载链接: <info>" . $this->getDownloadUrl() . '</info>');

            return 0;
        }

        // 确认
        if (!$this->option('force') && !$this->confirm("确定要发送版本更新通知给 {$totalUsers} 个用户吗？")) {
            return 0;
        }

        // 发送
        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $stats = ['sent' => 0, 'errors' => 0, 'total' => 0];
        $errors = [];

        $baseQuery->chunk($chunkSize, function ($users) use (&$stats, &$errors, $progressBar, $delay) {
            foreach ($users as $user) {
                $stats['total']++;
                try {
                    $params = $this->buildEmailParams($user->email);
                    $result = MailService::sendEmail($params);
                    if ($result['error']) {
                        $stats['errors']++;
                        $errors[] = ['email' => $user->email, 'error' => $result['error']];
                    } else {
                        $stats['sent']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $errors[] = ['email' => $user->email, 'error' => $e->getMessage()];
                }
                $progressBar->advance();
            }

            if ($delay > 0) {
                sleep($delay);
            }

            gc_collect_cycles();
        });

        $progressBar->finish();
        $this->newLine();

        // 结果
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('✅ 版本更新通知群发完成！');
        $this->table(['统计项', '数量'], [
            ['总处理用户', number_format($stats['total'])],
            ['发送成功', number_format($stats['sent'])],
            ['发送失败', number_format($stats['errors'])],
            ['总耗时', "{$duration} 秒"],
            ['平均速度', round($stats['sent'] / max($duration, 0.1), 1) . ' 封/秒'],
        ]);

        if (!empty($errors)) {
            $this->warn('--- 失败详情（最多显示 10 条）---');
            $this->table(['邮箱', '错误'], array_slice($errors, 0, 10));
        }

        Log::info('SendUpdateNotice 完成', $stats);

        return 0;
    }

    private function buildSubject(): string
    {
        return '【VP92】重要更新提醒 — 请升级最新版客户端，避免影响正常登录';
    }

    private function getDownloadUrl(): string
    {
        $appUrl = admin_setting('app_url', 'https://vp92vip.com');
        return rtrim($appUrl, '/');
    }

    private function buildEmailParams(string $email): array
    {
        return [
            'email' => $email,
            'subject' => $this->buildSubject(),
            'template_name' => 'updateNotice',
            'template_value' => [
                'name' => admin_setting('app_name', 'VP92'),
                'url' => $this->getDownloadUrl(),
            ],
        ];
    }
}
