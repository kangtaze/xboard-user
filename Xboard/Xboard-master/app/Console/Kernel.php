<?php

namespace App\Console;

use App\Services\Plugin\PluginManager;
use App\Utils\CacheKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        Cache::put(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null), time());
        // v2board
        $schedule->command('xboard:statistics')->dailyAt('0:10')->onOneServer();
        // check
        $schedule->command('check:order')->everyMinute()->onOneServer()->withoutOverlapping(5);
        $schedule->command('check:commission')->everyMinute()->onOneServer()->withoutOverlapping(5);
        $schedule->command('check:ticket')->everyMinute()->onOneServer()->withoutOverlapping(5);
        $schedule->command('check:traffic-exceeded')->everyMinute()->onOneServer()->withoutOverlapping(10)->runInBackground();
        // reset
        $schedule->command('reset:traffic')->everyMinute()->onOneServer()->withoutOverlapping(10);
        $schedule->command('reset:log')->daily()->onOneServer();
        // send
        $schedule->command('send:remindMail', ['--force'])->dailyAt('11:30')->onOneServer();

        // Intent campaign: daily refresh + optional auto send (v19 2026-08-27)
        $schedule->command('intent:refresh')->dailyAt('00:30')->onOneServer();

        // 自动发送（仅当 admin_setting('intent_auto_send', false) == true 时启用）
        if (admin_setting('intent_auto_send', false)) {
            $schedule->command('send:intent-campaign --tag=intent_high --force')
                ->weekly()->mondays()->at('10:00');
            $schedule->command('send:intent-campaign --tag=intent_cold --force')
                ->weekly()->wednesdays()->at('10:00');
            $schedule->command('send:intent-campaign --tag=expiring_soon --force')
                ->dailyAt('10:00');
            $schedule->command('send:intent-campaign --tag=churned_warm --force')
                ->weekly()->fridays()->at('10:00');
        }

        // horizon metrics
        $schedule->command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
        // cleanup stale online_count (GC for Redis TTL expiration)
        $schedule->command('cleanup:online-status')->everyFiveMinutes()->onOneServer();
        // backup Timing
        // if (env('ENABLE_AUTO_BACKUP_AND_UPDATE', false)) {
        //     $schedule->command('backup:database', ['true'])->daily()->onOneServer();
        // }
        app(PluginManager::class)->registerPluginSchedules($schedule);

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        try {
            app(PluginManager::class)->initializeEnabledPlugins();
        } catch (\Exception $e) {
        }
        require base_path('routes/console.php');
    }
}
