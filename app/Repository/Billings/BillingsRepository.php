<?php

namespace App\Repository\Billings;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Classes\Billings\ManageBillings;
use App\Helpers\ClientHelper;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Billings\IBillingsRepository;

// use App\Classes\QualityReport;

class BillingsRepository extends BaseRepository implements IBillingsRepository
{
    public function __construct()
    {
    }

    public function overall(): array
    {
        $clientId = ClientHelper::clientId();
        $data = Cache::get('billings_overall_' . $clientId);
        if ($data) {
            return $data;
        }

        $service = new ManageBillings();

        $time = microtime(true);

        $feed_brokers = $service->_get_overall_brokers_balance();
        $feed_endpoints = $service->_get_overall_endpoints_balance();

        $time = microtime(true) - $time;

        $data = [
            'time' => $time,
            'brokers_overall_balance' => $feed_brokers['overall'],
            'brokers_prepayment_balance' => $feed_brokers['prepayment'],
            'brokers_debt_balance' => $feed_brokers['debt'],
            'brokers_collection_balance' => $feed_brokers['collection'],

            'endpoints_overall_balance' => $feed_endpoints['overall'],
            'endpoints_prepayment_balance' => $feed_endpoints['prepayment'],
            'endpoints_debt_balance' => $feed_endpoints['debt'],
            'endpoints_collection_balance' => $feed_endpoints['collection'],
        ];

        Cache::put('billings_overall_' . $clientId, $data, 60 * 5);

        return $data;
    }

    public function pending_payments(): array
    {
        $service = new ManageBillings();
        $feed_brokers = $service->feed_brokers_pending_payments();
        $feed_endpoints = $service->feed_endpoints_pending_payments();

        $data = [
            'brokers_pending_payments' => $feed_brokers,
            'endpoints_pending_payments' => $feed_endpoints
        ];
        return $data;
    }

    public function brokers_balances(array $payload): array
    {
        $service = new ManageBillings($payload);
        return $service->get_broker_balances();
    }

    public function endpoint_balances(array $payload): array
    {
        $service = new ManageBillings($payload);
        return $service->get_endpoint_balances(); //collect
    }

    public function approved(array $payload): array {
        $service = new ManageBillings($payload);
        return $service->get_approved(); //collect
    }
}
