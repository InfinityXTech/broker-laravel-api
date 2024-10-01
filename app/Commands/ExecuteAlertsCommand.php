<?php

namespace App\Commands;

use App\Models\Alert;
use App\Models\Client;
use App\Commands\BaseCommand;
use Illuminate\Support\Facades\Log;

class ExecuteAlertsCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'executeAlerts:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute alerts command';

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
        $clients = Client::all(['_id', 'nickname', 'db_connection', 'db_crypt_key', 'db_crypt_iv']);

        foreach ($clients as $client) {

            $this->setDBConnection($client->toArray());

            Alert::where('execution_at', '>=', now())->whereIn('status', ['1', 1])->update(['status' => 2]);
        }
        // Log::info("Execute alerts command!");
    }
}
