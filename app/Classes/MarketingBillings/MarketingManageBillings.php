<?php

namespace App\Classes\MarketingBillings;

use App\Models\User;
use App\Helpers\QueryHelper;
use App\Helpers\ClientHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\Advertisers\MarketingAdvertiserBillingPaymentRequest;
use App\Models\Affiliates\AffiliateBillingPaymentRequests;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\ObjectId;

class MarketingManageBillings
{
    private $payload;
    private $clientId = '';

    private $start;
    private $end;

    public function __construct(array $payload = null, string $clientId = '')
    {
        $this->payload = $payload;
        $this->clientId = $clientId;
    }

    private function attach_client_id(array &$where)
    {
        if (!empty($this->clientId)) {
            $where['clientId'] = $this->clientId;
        }
    }

    function get_advertiser_names()
    {
        $where = [];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = isset($partner['name']) ? $partner['name'] . ' (' . ($partner['token'] ?? '') . ')' : ($partner['token'] ?? '');
        }
        return $result;
    }

    function get_advertisers()
    {
        $where = [];
        // $where = ['_id' => new \MongoDB\BSON\ObjectId('6358d8479baf3c33a80e4c82')];
        $this->attach_client_id($where);
        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
        $cache_key = 'get_advertisers_' . $clientId . '_' . md5(serialize($where));
        $result = Cache::get($cache_key);
        // if ($result) {
        //     return $result;
        // }

        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = $partner;
        }

        $seconds = 60 * 60;
        Cache::put($cache_key, $result, $seconds);

        return $result;
    }

    function get_affiliate_names()
    {
        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();

        $result = Cache::get('affiliate_names_' . $clientId);
        if ($result) {
            return $result;
        }

        $where = [];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('marketing_affiliates', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = isset($partner['name']) ? $partner['name'] . ' (' . ($partner['token'] ?? '') . ')' : ($partner['token'] ?? '');
        }

        $seconds = 60 * 60;
        Cache::put('affiliate_names_' . $clientId, $result, $seconds);
        return $result;
    }

    function get_affiliates()
    {
        $where = [];
        $this->attach_client_id($where);

        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
        $cache_key = 'get_affiliates_' . $clientId . '_' . md5(serialize($where));
        // $result = Cache::get($cache_key);
        // if ($result) {
        //     return $result;
        // }

        $mongo = new MongoDBObjects('marketing_affiliates', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = $partner;
        }

        $seconds = 60 * 60;
        Cache::put($cache_key, $result, $seconds);

        return $result;
    }

    function calculate_chargebacks($id_field, $collection_name)
    {
        $chargebacks = [];
        $where = [];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects($collection_name, $where);
        $list = $mongo->aggregate([
            'group' => [
                '_id' => '$' . $id_field,
                'total' => ['$sum' => '$amount']
            ]
        ], false, false);

        foreach ($list as $data) {
            $id = $data['_id'];
            $chargebacks[$id] = $data['total'];
        }
        return $chargebacks;
    }

    public function calculate_adjustments($id_field, $collection_name)
    {
        $where = [];

        $adjustments = [];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects($collection_name, $where);
        $list = $mongo->aggregate([
            'group' => [
                '_id' => '$' . $id_field,
                'total' => ['$sum' => '$amount']
            ]
        ], false, false);

        foreach ($list as $data) {
            $id = $data['_id'];
            $adjustments[$id] = $data['total'];
        }
        return $adjustments;
    }

    function calculate_payment_requests($id_field, $collection_name)
    {
        $payment_requests = [];
        $where = ['final_status' => 1];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects($collection_name, $where);
        $list = $mongo->aggregate([
            'group' => [
                '_id' => [
                    'id' => '$' . $id_field,
                    'type' => '$type'
                ],
                'total' => ['$sum' => ['$toDouble' => '$total']],
            ]
        ], false, false);

        foreach ($list as $data) {
            $id = $data['_id']['id'];
            $type = $data['_id']['type'];
            $payment_requests[$id] = ($payment_requests[$id] ?? []);
            $payment_requests[$id][$type] = ($payment_requests[$id][$type] ?? 0);
            $payment_requests[$id][$type] += $data['total'];
        }
        return $payment_requests;
    }

    function get_advertiser_balances_revenues($from = null, $to = null)
    {

        $revenues = [];

        $this->start = $from ?? strtotime('00:00:00', -2147483648);
        $this->end = $to ?? strtotime('23:59:59');
        $time = [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($this->end * 1000),
        ];
        $time['where'] = [
            'EventTimeStamp' => ['$gte' => $time['start'], '$lte' => $time['end']],
            'EventType' => ['$ne' => "CLICK"],
        ];
        $query = $this->advertiser_billing_general_balances_buildParameterArray();

        $conditions = [
            'clientId' => !empty($this->clientId) ? $this->clientId : ClientHelper::clientId()
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $leads = QueryHelper::attachMarketingFormula($query_data, $query['formula']);
        foreach ($leads as $lead) {
            $id = ($lead['AdvertiserId'] ?? '');
            $revenues[$id] = ($revenues[$id] ?? 0) + ($lead['revenue'] ?? 0);
        }
        return $revenues;
    }

    function get_advertiser_balances()
    {
        $advertisers = $this->get_advertisers();

        // --- revenues ---
        $to = strtotime(date('Y-m-01 23:59:59') . ' - 1 day');
        $from = strtotime(date('Y-m-01 00:00:00'));
        $cache_key = 'billings_overall_advertiser_balances_';

        $revenues = $this->get_advertiser_balances_revenues();

        // $revenues = Cache::get($cache_key . $to);
        // if (!$revenues) {
        //     $revenues = $this->get_advertiser_balances_revenues(null, $to);
        //     $seconds = strtotime("last day of this month");
        //     Cache::put($cache_key . $to, $revenues, $seconds);
        // }

        // $revenues_new = Cache::get($cache_key . $from);
        // if (!$revenues_new) {
        //     $revenues_new = $this->get_advertiser_balances_revenues($from, null);
        //     $seconds = 60 * 5;
        //     Cache::put($cache_key . $from, $revenues_new, $seconds);
        // }

        // foreach ($revenues_new as $advertiserId => $revenue) {
        //     $revenues[$advertiserId] = ($revenues[$advertiserId] ?? 0) + ($revenue ?? 0);
        // }

        // --- chargebacks ---
        $chargebacks = $this->calculate_chargebacks('advertiser', 'marketing_advertiser_billing_chargebacks');

        // --- adjustments ---
        $adjustments = $this->calculate_adjustments('advertiser', 'marketing_advertiser_billing_adjustments');

        // --- payment requests ---
        $payment_requests = $this->calculate_payment_requests('advertiser', 'marketing_advertiser_billing_payment_requests');

        $balances = [];
        foreach ($advertisers as $id => $advertiser) {

            $clientId = $advertiser['clientId'] ?? '';

            $is_collection = (($advertiser['billing_manual_status'] ?? '') == 'collection');

            if (isset($this->payload['collection']) && (bool)$this->payload['collection'] == false && $is_collection) {
                continue;
            }

            $status = ((int)($advertiser['status'] ?? 0) == 1);
            $account_manager = $advertiser['account_manager'] ?? '';
            if (!empty($account_manager)) {
                $account_manager = User::query()->find($account_manager, ['_id', 'account_email', 'name']);
                if ($account_manager) {
                    $account_manager = $account_manager->toArray();
                }
            }

            $balance = (($payment_requests[$id]['prepayment'] ?? 0) + ($payment_requests[$id]['payment'] ?? 0) - ($revenues[$id] ?? 0) + ($chargebacks[$id] ?? 0) + ($adjustments[$id] ?? 0));

            // set active if balance > 0 and is collection
            if ($is_collection && $balance >= 0) {
                $where = [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'billing_manual_status' => ['$ne' => 'active']
                ];
                $this->attach_client_id($where);
                $mongo = new MongoDBObjects('marketing_advertisers', $where);
                $mongo->update(['billing_manual_status' => 'active']);
                $is_collection = false;
            }

            $balances[] = [
                'id' => $id,
                'clientId' => $clientId,
                'name' => $advertiser['name'] . ' (' . $advertiser['token'] . ')',
                'is_collection' => $is_collection,
                'status' => $status,
                'account_manager' => $account_manager,
                'balance' => $balance
            ];
        }
        return $balances;
    }

    function get_affiliate_balances_costs($from = null, $to = null)
    {

        $costs = [];

        $this->start = $from ?? strtotime('00:00:00', -2147483648);
        $this->end = $to ?? strtotime('23:59:59');

        $time = [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
        $time['where'] = [
            'EventTimeStamp' => ['$gte' => $time['start'], '$lte' => $time['end']],
            'EventType' => ['$ne' => "CLICK"],
        ];

        $query = $this->affiliate_billing_general_balances_buildParameterArray();

        $conditions = [
            'clientId' => !empty($this->clientId) ? $this->clientId : ClientHelper::clientId()
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $leads = QueryHelper::attachMarketingFormula($query_data, $query['formula']);
        foreach ($leads as $lead) {
            $id = $lead['AffiliateId'];
            $costs[$id] =  ($costs[$id] ?? 0) + ($lead['cost'] ?? 0);
        }

        return $costs;
    }

    function get_affiliate_balances()
    {
        $affiliates = $this->get_affiliates();

        // --- costs ---
        $to = strtotime(date('Y-m-01 23:59:59') . ' - 1 day');
        $from = strtotime(date('Y-m-01 00:00:00'));
        $cache_key = 'billings_overall_affiliate_balances_';

        $costs = $this->get_affiliate_balances_costs();

        // $costs = Cache::get($cache_key . $to);
        // if (!$costs) {
        //     $costs = $this->get_affiliate_balances_costs(null, $to);
        //     $seconds = strtotime("last day of this month");
        //     Cache::put($cache_key . $to, $costs, $seconds);
        // }

        // $costs_new = Cache::get($cache_key . $from);
        // if (!$costs_new) {
        //     $costs_new = $this->get_affiliate_balances_costs($from, null);
        //     $seconds = 60 * 5;
        //     Cache::put($cache_key . $from, $costs_new, $seconds);
        // }

        // foreach ($costs_new as $affiliateId => $cost) {
        //     $costs[$affiliateId] =  ($costs[$affiliateId] ?? 0) + ($cost ?? 0);
        // }

        // --- chargebacks ---
        $chargebacks = $this->calculate_chargebacks('affiliate', 'marketing_affiliate_billing_chargebacks');

        // --- adjustments ---
        $adjustments = $this->calculate_adjustments('affiliate', 'marketing_affiliate_billing_adjustments');

        // --- payment requests ---
        $payment_requests = $this->calculate_payment_requests('affiliate', 'marketing_affiliate_billing_payment_requests');

        $balances = [];
        foreach ($affiliates as $id => $affiliate) {

            $clientId = $affiliate['clientId'] ?? '';

            $is_collection = (($affiliate['billing_manual_status'] ?? '') == 'collection');

            if (isset($this->payload['collection']) && (bool)$this->payload['collection'] == false && $is_collection) {
                continue;
            }

            $status = ((int)($affiliate['status'] ?? 0) == 1);
            $account_manager = $affiliate['account_manager'] ?? '';
            if (!empty($account_manager)) {
                $account_manager = User::query()->find($account_manager, ['_id', 'account_email', 'name']);
                if ($account_manager) {
                    $account_manager = $account_manager->toArray();
                } else {
                    $account_manager = [];
                }
            }
            $balance = (($payment_requests[$id]['prepayment'] ?? 0) + ($payment_requests[$id]['payment'] ?? 0) - ($costs[$id] ?? 0) + ($chargebacks[$id] ?? 0) + ($adjustments[$id] ?? 0));

            // set active if balance <= 0 and is collection
            if ($is_collection && $balance <= 0) {
                $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
                $this->attach_client_id($where);
                $mongo = new MongoDBObjects('marketing_affiliates', $where);
                $mongo->update(['billing_manual_status' => 'active']);
                $is_collection = false;
            }

            $balances[] = [
                'id' => $id,
                'clientId' => $clientId,
                'name' => isset($affiliate['name']) ? $affiliate['name'] . ' (' . ($affiliate['token'] ?? '') . ')' : $affiliate['token'] ?? '',
                'is_collection' => $is_collection,
                'status' => $status,
                'account_manager' => $account_manager,
                'balance' => $balance
            ];
        }
        return $balances;
    }

    ///// ---- Overall ---- //////

    function _get_overall_advertisers_balance()
    {
        $balances = $this->get_advertiser_balances();

        $overall = 0;
        $prepayment = 0;
        $debt = 0;
        $collection = 0;
        foreach ($balances as $data) {
            $overall += $data['balance'];

            if ($data['balance'] < 0) {
                if ($data['is_collection']) {
                    $collection += $data['balance'];
                } else {
                    $debt += $data['balance'];
                }
            } else {
                $prepayment += $data['balance'];
            }
        }
        return [
            'overall' => $overall,
            'prepayment' => $prepayment,
            'collection' =>  round($collection, 2),
            'debt' => $debt,
        ];
    }

    function _get_overall_affiliates_balance()
    {
        $balances = $this->get_affiliate_balances();

        $overall = 0;
        $prepayment = 0;
        $debt = 0;
        $collection = 0;
        foreach ($balances as $data) {
            $overall += $data['balance'];

            if ($data['balance'] < 0) {
                $debt += $data['balance'];
            } else {
                if ($data['is_collection']) {
                    $collection += $data['balance'];
                } else {
                    $prepayment += $data['balance'];
                }
            }
        }
        return [
            'overall' => $overall,
            'prepayment' => $prepayment,
            'collection' =>  round($collection, 2),
            'debt' => $debt,
        ];
    }

    ///// ---- Pending Payments ---- //////

    public function feed_advertisers_pending_payments(): array
    {
        $names = $this->get_advertiser_names();

        $result = ['items' => []];

        $where = [
            'status' => ['$in' => ['1', 1]],
            'final_status' => ['$exists' => false]
        ];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects("marketing_advertiser_billing_payment_requests", $where);
        $array = $mongo->findMany(['sort' => ['timestamp' => -1]]);

        $overall = 0;
        foreach ($array as $data) {

            $ts = (array)$data['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $from = '-';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $from = date("Y-m-d", $seconds);
            }

            $to = '-';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $to = date("Y-m-d", $seconds);
            }

            $total = isset($data['total']) ? $data['total'] : 0;
            $overall += $total;

            $result['items'][] = [
                'advertiser' => [
                    '_id' => ($data['advertiser'] ?? ''),
                    'name' => ($names[$data['advertiser'] ?? ''] ?? '')
                ],
                'timestamp' => $timestamp,
                'from' => $from,
                'to' => $to,
                'type' => ($data['type'] ?? 'payment'),
                'total' => $total
            ];
        }

        $result['overall'] = $overall;
        return $result;
    }

    public function feed_affiliates_pending_payments(): array
    {

        $result = ['items' => []];

        $names = $this->get_affiliate_names();

        $where = [
            'status' => 1,
            'final_status' => ['$exists' => false],
        ];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('marketing_affiliate_billing_payment_requests', $where);
        $array = $mongo->findMany(['sort' => ['timestamp' => -1]]);

        $overall = 0;
        foreach ($array as $data) {

            $ts = (array)$data['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $from = '-';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $from = date("Y-m-d", $seconds);
            }

            $to = '-';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $to = date("Y-m-d", $seconds);
            }

            $total = isset($data['total']) ? $data['total'] : 0;
            $overall += $total;

            $result['items'][] = [
                'affiliate' => [
                    '_id' => $data['affiliate'],
                    'name' => $names[$data['affiliate']]
                ],
                'timestamp' => $timestamp,
                'from' => $from,
                'to' => $to,
                'type' => ($data['type'] ?? 'payment'),
                'total' => $total
            ];
        }

        $result['overall'] = $overall;

        return $result;
    }

    ///// ---- Brokers Balances ---- //////

    public function advertiser_billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            'AdvertiserId',
        ];

        $metrics = [
            'revenue',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_advertiserPayout = array('revenue' =>  [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                    $advertiserPayout = 0.0;
					if ( __(bool)Approved__ == TRUE || __(bool)Rejected__ == TRUE) {
						$advertiserPayout = __AdvertiserPayout__;
					}
					return (float)$advertiserPayout;
                ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {
            if ($metrics == 'revenue') {
                $array['revenue'] = $_advertiserPayout;
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    public function feed_billings_advertisers_balances()
    {
        $balances = $this->get_advertiser_balances();

        $html = '<table class="table table-payouts" style="width: 100%"><thead>';
        $html .= '<tr>';
        $html .= '<th>#</th>';
        $html .= '<th>Advertiser Name</th>';
        $html .= '<th>Balance</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $overall = 0;
        $c = 0;
        foreach ($balances as $data) {
            $c++;

            $html .= '<tr>';
            $html .= '<td>' . $c . '</td>';

            $html .= '<td style="text-transform: uppercase;">';
            $html .= '<a target="_blank" href="/Brokers/' . $data['id'] . '/">' . $data['name'] . '</a>';
            $html .= '</td>';

            $overall += $data['balance'];
            $html .= '<td>' . RenderHelper::format_money_color($data['balance']) . '</td>';

            $html .= '</tr>';
        }

        $html .=
            '<tr style="background-color: #00adff;color:white;">' .
            '<td colspan="2">Total</td>' .
            '<td>' . RenderHelper::format_money($overall) . '</td>' .
            '</tr>';

        $html .= '</tbody></table>';

        return $html;
    }

    ///// ---- Endpoint Balances ---- //////

    public function affiliate_billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            'AffiliateId',
        ];

        $metrics = [
            'cost',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_affiliatePayout  = array('cost' => [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                $affiliatePayout = 0.0;
				if ( __(bool)Approved__ == TRUE ) {
					$affiliatePayout = __AffiliatePayout__;
				}
                return (float)$affiliatePayout;
            ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {
            if ($metrics == 'cost') {
                $array['cost'] = $_affiliatePayout;
                $formula['cost'] = '__cost__';
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    function _feed_billings_affiliate_balances()
    {
        $balances = $this->get_affiliate_balances();

        $html = '<table class="table table-payouts" style="width: 100%"><thead>';
        $html .= '<tr>';
        $html .= '<th>#</th>';
        $html .= '<th>Affiliate</th>';
        $html .= '<th>Balance</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $overall = 0;
        $c = 0;
        foreach ($balances as $data) {
            $c++;

            $html .= '<tr>';
            $html .= '<td>' . $c . '</td>';

            $html .= '<td style="text-transform: uppercase;">';
            $html .= '<a target="_blank" href="/TrafficEndpoints/' . $data['id'] . '/">' . $data['name'] . '</a>';
            $html .= '</td>';

            $overall += $data['balance'];
            $html .= '<td>' . RenderHelper::format_money_color($data['balance']) . '</td>';

            $html .= '</tr>';
        }

        $html .=
            '<tr style="background-color: #00adff;color:white;">' .
            '<td colspan="2">Total</td>' .
            '<td>' . RenderHelper::format_money($overall) . '</td>' .
            '</tr>';

        $html .= '</tbody></table>';

        return $html;
    }

    public function get_approved(): array
    {
        $timeframe = $this->payload['timeframe'] ?? '';

        $time = explode(' - ', $timeframe);
        $start = strtotime($time[0] . " 00:00:00");
        $end = strtotime($time[1] . " 23:59:59");

        $start = new \MongoDB\BSON\UTCDateTime($start * 1000);
        $end   = new \MongoDB\BSON\UTCDateTime($end * 1000);

        $advertisers_approved = MarketingAdvertiserBillingPaymentRequest::query()
            ->where('final_status', '=', 1)
            ->where('final_status_changed_date', '>=', $start)
            ->where('final_status_changed_date', '<=', $end)
            ->with([
                'advertiser_data:name,token',
                'final_status_changed_user:name,account_email'
            ])
            ->get([
                'advertiser',
                'timestamp',
                'from',
                'to',
                'type',
                'total',
                'final_status',
                'final_status_changed_date',
                'final_status_changed_user_id',
                'final_status_changed_user_ip',
                'final_status_changed_user_ua',
                'final_status_date_pay',
                'transaction_id',
				'final_status_date_pay'
            ])
            ->toArray();

        $affiliates_approved = AffiliateBillingPaymentRequests::query()
            ->where('final_status', '=', 1)
            ->where('final_status_changed_date', '>=', $start)
            ->where('final_status_changed_date', '<=', $end)
            ->with([
                'affiliate_data:token',
                'final_status_changed_user:name,account_email'
            ])
            ->get([
                'affiliate',
                'timestamp',
                'from',
                'to',
                'type',
                'total',
                'final_status',
                'final_status_changed_date',
                'final_status_changed_user_id',
                'final_status_changed_user_ip',
                'final_status_changed_user_ua',
                'final_status_date_pay',
                'transaction_id',
				'final_status_date_pay'
            ])
            ->toArray();

        return [
            'advertisers_approved' => $advertisers_approved,
            'affiliates_approved' => $affiliates_approved
        ];
    }
}
