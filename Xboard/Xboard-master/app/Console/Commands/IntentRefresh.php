<?php

namespace App\Console\Commands;

use App\Models\UserIntent;
use App\Services\IntentClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 用途: 每日重算 4 个 tag 用户清单到 v2_user_intent 表
 * 调用方: Kernel.php 每日 00:30 cron（onOneServer）
 * 被调用方: IntentClassifier 计算每 tag 用户 + v2_user_intent 表写入
 * 关联记录: v19 plan Task 4 (2026-08-27)
 */
class IntentRefresh extends Command
{
    protected $signature = 'intent:refresh {--dry : 预览模式，不写入数据库}';
    protected $description = '每日重算 4 个 tag 用户清单到 v2_user_intent 表';

    public function handle(): int
    {
        $isDry = $this->option('dry');
        $now = time();

        $counts = [];
        foreach (UserIntent::ALL_TAGS as $tag) {
            $userIds = IntentClassifier::usersByTag($tag);
            $counts[$tag] = count($userIds);

            if (!$isDry && !empty($userIds)) {
                // 取 (user_id, last_mailed_at) 历史映射
                $existingMailed = DB::table('v2_user_intent')
                    ->where('tag', $tag)
                    ->whereIn('user_id', $userIds)
                    ->pluck('last_mailed_at', 'user_id')
                    ->toArray();

                // 删 (user_id, tag) 旧记录
                DB::table('v2_user_intent')
                    ->where('tag', $tag)
                    ->whereIn('user_id', $userIds)
                    ->delete();

                // 重插，保留 last_mailed_at（避免 UNIQUE 冲突 + 保留发过邮件的历史）
                $rows = [];
                foreach ($userIds as $userId) {
                    $rows[] = [
                        'user_id' => $userId,
                        'tag' => $tag,
                        'last_mailed_at' => $existingMailed[$userId] ?? null,
                        'computed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('v2_user_intent')->insert($chunk);
                }
            }
        }

        $this->table(['Tag', '匹配用户数'], array_map(
            fn($tag, $count) => [$tag, number_format($count)],
            array_keys($counts),
            array_values($counts)
        ));

        if ($isDry) {
            $this->warn('--dry 模式：未写入数据库');
        } else {
            $this->info("✅ 重算完成，已写入 v2_user_intent");
        }

        return 0;
    }
}