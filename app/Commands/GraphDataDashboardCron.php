<?php

namespace App\Commands;

use App\Models\Stats;
use App\Models\Client;
use App\Scopes\ClientScope;
use App\Commands\BaseCommand;
use App\Classes\Stats\GraphDataDashboardHourly;
use App\Classes\Stats\GraphDataDashboardCountry;

class GraphDataDashboardCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphDataDashboard:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GraphDataDashboardCron';

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
        $clients = Client::all(['_id', 'db_connection', 'db_crypt_key', 'db_crypt_iv'])->toArray();
        foreach($clients as $client) {
            $clientId = $client['_id'];

            $this->setDBConnection($client);
            
            $stat = Stats::withoutGlobalScope(new ClientScope)->where('clientId', '=', $clientId)->get(['_id'])->first();
            if (!$stat) {
                $model = new Stats();
                $model->clientId = $clientId;
                $model->save();
            }

            $d = new GraphDataDashboardCountry(['clientId' => $clientId], 'stats');
            $d->queryForCountries();

            $d1 = new GraphDataDashboardHourly(['clientId' => $clientId], 'stats');
            $d1->todayTomorrowComparisonAccountLevel();
        }
        // Log::info("Cron Cache Clients!");
    }
}
