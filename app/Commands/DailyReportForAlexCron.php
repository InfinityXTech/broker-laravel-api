<?php

namespace App\Commands;

use App\Models\Client;
use App\Commands\BaseCommand;
use App\Helpers\ClientHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Repository\Report\ReportRepository;

class DailyReportForAlexCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DailyReportForAlexCron:cron';

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

            $start = strtotime(date('Y-m-01'));
            $end = strtotime('yesterday');

            if ($end < $start) {
                $start = strtotime('first day of last month');
            }

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
                    $content = 'MTD ' . date('d/m', $start) . " - " . date('d/m', $end) . PHP_EOL .
                        'Revenue: $' . round($result['totals']['deposit_revenue'] ?? 0, 0) . PHP_EOL .
                        'Endpoints costs: $' . round($result['totals']['cost'] ?? 0, 0) . PHP_EOL .
                        'Profit: $' . round($result['totals']['profit'] ?? 0, 0);
                }

                $html = nl2br(htmlspecialchars($content));

                // Mail::send(['html' => 'emails.simple'], ['title' => 'Daily Report For Alex (' . $client->nickname . ')', 'body' => $html], function ($message) use ($client) {
                //     $message->to('alex@roibees.com', 'Alex Vialkov')->subject('Daily Report For Alex (' . $client->nickname . ')');
                //     //alex@roibees.com
                //     // john@roibees.com
                //     // $message->from('xyz@gmail.com', 'Virat Gandhi');
                // });

                $email_config = ClientHelper::setEmailConfig($client->_id);
                Mail::mailer($email_config['mailer_name'])->send(['html' => 'emails.simple'], [
                    'title' => 'Daily Report For Alex (' . $client->nickname . ')',
                    'body' => $html
                ], function ($message) use ($client, $email_config) {
                    $message
                        ->to('alex@roibees.com', 'Alex Vialkov')
                        ->subject('Daily Report For Alex (' . $client->nickname . ')');
                    if (!empty($email_config['from'])) {
                        $message->from($email_config['from']['address'], $email_config['from']['name']);
                    }
                });
            }
            Log::info("Cron DailyReportForAlexCron!");
        } catch (\Exception $ex) {
            Log::info("Cron DailyReportForAlexCron Error: " . $ex->getMessage());
        }
    }
}
