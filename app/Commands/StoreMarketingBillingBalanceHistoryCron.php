<?php

namespace App\Commands;

use App\Models\Client;
use App\Commands\BaseCommand;
use Illuminate\Support\Facades\Log;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\MarketingBillings\MarketingManageBillings;

class StoreMarketingBillingBalanceHistoryCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store_marketing_billing_balance_history:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store Marketing Billing Balance History Cron';

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
            
            $billings = new MarketingManageBillings([], $client->_id);

            $advertisers = $billings->get_advertiser_balances();
            foreach ($advertisers as $advertiser) {
                $insert = [
                    'timestamp' => $timestamp,
                    'advertiser' => $advertiser['id'],
                    'clientId' => $advertiser['clientId'],
                    'type' => 1,
                    'balance' => (float)$advertiser['balance'],
                ];
                $mongo = new MongoDBObjects('marketing_billings_log', $insert);
                $mongo->set_client_id($advertiser['clientId']);
                $mongo->insert();
            }

            $affiliates = $billings->get_affiliate_balances();

            foreach ($affiliates as $affiliate) {
                $insert = [
                    'timestamp' => $timestamp,
                    'affiliate' => $affiliate['id'],
                    'clientId' => $affiliate['clientId'],
                    'type' => 2,
                    'balance' => (float)$affiliate['balance'],
                ];
                $mongo = new MongoDBObjects('marketing_billings_log', $insert);
                $mongo->set_client_id($affiliate['clientId']);
                $mongo->insert();
            }
        }

        Log::info("StoreMarketingBillingBalanceHistoryCron finished!");
    }
}
