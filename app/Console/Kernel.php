<?php

namespace App\Console;

use App\Commands\InitDB;
use App\Commands\RatesCron;

use Illuminate\Support\Carbon;
use App\Classes\History\HistoryDB;
use App\Commands\CacheClientsCron;
use App\Commands\NotificationCron;
use Illuminate\Support\Facades\Log;
use App\Commands\DailyMoneyReportCron;
use App\Commands\ExecuteAlertsCommand;
use App\Commands\MontlyMoneyReportCron;
use App\Commands\DailyReportForAlexCron;
use App\Commands\GraphDataDashboardCron;
use Illuminate\Console\Scheduling\Schedule;
use App\Commands\StoreBillingBalanceHistoryCron;
use App\Commands\StoreMarketingBillingBalanceHistoryCron;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        RatesCron::class,
        NotificationCron::class,
        CacheClientsCron::class,
        StoreBillingBalanceHistoryCron::class,
        StoreMarketingBillingBalanceHistoryCron::class,
        GraphDataDashboardCron::class,
        DailyReportForAlexCron::class,
        MontlyMoneyReportCron::class,
        DailyMoneyReportCron::class,
        ExecuteAlertsCommand::class,
        InitDB::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        $schedule->call(function () {
            HistoryDB::clear();
        })->dailyAt('12:30');

        $schedule->command('rates:cron')->hourly();

        // $schedule->command('notifications:cron')->cron('* * * * *');

        $schedule->command('cacheclients:cron')->everyTenMinutes();

        $schedule->command('storebillingbalancehistory:cron')->dailyAt('23:55');//->everySixHours();

        $schedule->command('store_marketing_billing_balance_history:cron')->dailyAt('23:55');//->everySixHours();

        $schedule->command('GraphDataDashboard:cron')->everyThirtyMinutes();

        $schedule->command('DailyReportForAlexCron:cron')->dailyAt('07:30');

        $schedule->command('executeAlerts:command')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
