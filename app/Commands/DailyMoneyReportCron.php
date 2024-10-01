<?php

namespace App\Commands;

use App\Models\Client;
use App\Commands\BaseCommand;
use Illuminate\Support\Facades\Log;
use App\Classes\NotificationReporter;
use App\Repository\Report\ReportRepository;

class DailyMoneyReportCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DailyMoneyReportCron:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $r = new ReportRepository();

            $start = time();//strtotime('yesterday');
            $end = strtotime('tomorrow');

            $clients = Client::all(['_id', 'nickname', 'db_connection', 'db_crypt_key', 'db_crypt_iv']);

            foreach ($clients as $client) {

                $this->setDBConnection($client->toArray());

                $payload = [
                    'clientId' => $client->_id,
                    'adjustment' => true,
                    'metrics' => ["revenue", "cost", "profit"],
                    'pivot' => [],
                    'timeframe' => date('Y-m-d', $start) . " - " . date('Y-m-d', $end)
                ];

                $result = $r->run($payload);

                $content = 'No Data';

                if (isset($result) && isset($result['totals'])) {

                    $cost = round($result['totals']['cost'] ?? 0, 0);
                    $revenue = round($result['totals']['deposit_revenue'] ?? 0, 0);
                    $margin = round(100 - (($cost / ($revenue == 0 ? 1 : $revenue)) * 100),0);
                    if ($cost == 0 && $revenue == 0) 
                    {
                        $margin = 0;
                    }

                    $content = 'DTT ' . date('d/m', $start) . " - " . date('d/m', $start) . PHP_EOL .
                        'Revenue: $' . $revenue . PHP_EOL .
                        'Costs: $' . $cost . PHP_EOL .
                        'Profit: $' . round($result['totals']['profit'] ?? 0, 0) . PHP_EOL .
                        'Margin: ' . $margin . '%';
                    // $content = str_replace('$-', '-$', $content);
                }

                NotificationReporter::to('hourly-financial-report', $client->_id)->slack($content);
                // Log::info('[' . $client->_id . ']');
                // Log::info($content);
            }

            Log::info("Cron DailyMoneyReportCron!");
        } catch (\Exception $ex) {
            Log::info("Cron DailyMoneyReportCron Error: " . $ex->getMessage());
        }
    }
}
