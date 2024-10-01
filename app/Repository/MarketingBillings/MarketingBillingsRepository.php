<?php

namespace App\Repository\MarketingBillings;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Classes\MarketingBillings\MarketingManageBillings;
use App\Helpers\ClientHelper;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\MarketingBillings\IMarketingBillingsRepository;

class MarketingBillingsRepository extends BaseRepository implements IMarketingBillingsRepository
{
    public function __construct()
    {
    }

    public function overall(): array
    {
        $clientId = ClientHelper::clientId();
        // $data = Cache::get('marketing_billings_overall_' . $clientId);
        // if ($data) {
        //     return $data;
        // }

        $service = new MarketingManageBillings();

        $time = microtime(true);

        $feed_advertisers = $service->_get_overall_advertisers_balance();
        $feed_affiliates = $service->_get_overall_affiliates_balance();

        $time = microtime(true) - $time;

        $data = [
            'time' => $time,
            'advertisers_overall_balance' => $feed_advertisers['overall'],
            'advertisers_prepayment_balance' => $feed_advertisers['prepayment'],
            'advertisers_debt_balance' => $feed_advertisers['debt'],
            'advertisers_collection_balance' => $feed_advertisers['collection'],

            'affiliates_overall_balance' => $feed_affiliates['overall'],
            'affiliates_prepayment_balance' => $feed_affiliates['prepayment'],
            'affiliates_debt_balance' => $feed_affiliates['debt'],
            'affiliates_collection_balance' => $feed_affiliates['collection'],
        ];

        Cache::put('marketing_billings_overall_' . $clientId, $data, 60 * 5);

        return $data;
    }

    public function pending_payments(): array
    {
        $service = new MarketingManageBillings();
        $feed_advertisers = $service->feed_advertisers_pending_payments();
        $feed_affiliates = $service->feed_affiliates_pending_payments();

        $data = [
            'advertisers_pending_payments' => $feed_advertisers,
            'affiliates_pending_payments' => $feed_affiliates
        ];
        return $data;
    }

    public function advertisers_balances(array $payload): array
    {
        $service = new MarketingManageBillings($payload);
        return $service->get_advertiser_balances();
    }

    public function affiliates_balances(array $payload): array
    {
        $service = new MarketingManageBillings($payload);
        return $service->get_affiliate_balances(); //collect
    }

    public function approved(array $payload): array {
        $service = new MarketingManageBillings($payload);
        return $service->get_approved(); //collect
    }
}
