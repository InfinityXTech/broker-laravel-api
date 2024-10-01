<?php

namespace App\Commands;

use App\Models\Client;
use App\Commands\BaseCommand;
use Illuminate\Support\Facades\Log;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Billings\ManageBillings;

class StoreBillingBalanceHistoryCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storebillingbalancehistory:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store Billing Balance History Cron';

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
        $timestamp = new \MongoDB\BSON\UTCDateTime(time() * 1000);

        $clients = Client::all(['_id', 'nickname', 'db_connection', 'db_crypt_key', 'db_crypt_iv']);

        foreach ($clients as $client) {

            $this->setDBConnection($client->toArray());

            $billings = new ManageBillings([], $client->_id);

            $brokers = $billings->get_broker_balances();
            foreach ($brokers as $broker) {
                $insert = [
                    'timestamp' => $timestamp,
                    'broker' => $broker['id'],
                    'clientId' => $broker['clientId'],
                    'type' => 1,
                    'balance' => (float)$broker['balance'],
                ];
                $mongo = new MongoDBObjects('billings_log', $insert);
                $mongo->set_client_id($broker['clientId']);
                $mongo->insert();
            }

            $endpoints = $billings->get_endpoint_balances();

            foreach ($endpoints as $endpoint) {
                $insert = [
                    'timestamp' => $timestamp,
                    'endpoint' => $endpoint['id'],
                    'clientId' => $endpoint['clientId'],
                    'type' => 2,
                    'balance' => (float)$endpoint['balance'],
                ];
                $mongo = new MongoDBObjects('billings_log', $insert);
                $mongo->set_client_id($endpoint['clientId']);
                $mongo->insert();
            }
        }

        Log::info("StoreBillingBalanceHistoryCron finished!");
    }
}
