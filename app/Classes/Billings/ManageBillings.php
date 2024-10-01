<?php

namespace App\Classes\Billings;

use App\Models\User;
use App\Models\Broker;
use App\Helpers\QueryHelper;
use App\Helpers\ClientHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use App\Models\Brokers\BrokerBillingPaymentRequest;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentRequests;

class ManageBillings
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

    function get_broker_names()
    {
        $where = ['partner_type' => '1'];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('partner', $where);
        $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = GeneralHelper::broker_name($partner);
        }
        return $result;
    }

    function get_brokers()
    {
        $where = ['partner_type' => '1'];

        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_brokers = Broker::query()->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id']);
            foreach ($allow_brokers as $broker) {
                $in[] = new \MongoDB\BSON\ObjectId($broker->_id);
            }
            if (empty($in)) {
                $in = [new \MongoDB\BSON\ObjectId()];
            }
            $where['_id'] = ['$in' => $in];
        }

        $this->attach_client_id($where);
        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
        $userId = Auth::id();
        $cache_key = 'get_brokers_' . $clientId . '_' . $userId . '_' . md5(serialize($where));
        $result = Cache::get($cache_key);
        if ($result) {
            return $result;
        }

        $mongo = new MongoDBObjects('partner', $where);
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

    function get_enpoint_names()
    {
        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();

        $result = Cache::get('endpoint_names_' . $clientId);
        if ($result) {
            return $result;
        }

        $where = [];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = ($partner['token'] ?? '');
        }

        $seconds = 60 * 60;
        Cache::put('endpoint_names_' . $clientId, $result, $seconds);
        return $result;
    }

    function get_enpoints()
    {
        $where = ['$or' => [
            ['UnderReview' => 1],
            ['UnderReview' => null],
            ['UnderReview' => ['$exists' => false]]
        ]];

        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_endpoints = TrafficEndpoint::query()->orWhere('user_id', '=', $current_user_id)->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id']);
            foreach ($allow_endpoints as $endpoint) {
                $in[] = new \MongoDB\BSON\ObjectId($endpoint->_id);
            }
            if (empty($in)) {
                $in = [new \MongoDB\BSON\ObjectId()];
            }
            $where['_id'] = ['$in' => $in];
        }

        $this->attach_client_id($where);

        $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
        $userId = Auth::id();
        $cache_key = 'get_enpoints_' . $clientId . '_' . $userId . '_' . md5(serialize($where));
        $result = Cache::get($cache_key);
        if ($result) {
            return $result;
        }

        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
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
        if ($id_field == 'broker') {
            $where['final_status'] = 1;
        }
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

    function get_broker_balances_revenues($from = null, $to = null)
    {

        $revenues = [];

        $this->start = $from ?? strtotime('00:00:00', -2147483648);
        $this->end = $to ?? strtotime('23:59:59');
        $time = [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
        $query = $this->broker_billing_general_balances_buildParameterArray();

        $conditions = [
            'match_with_broker' => 1,
            'test_lead' => 0,
            'clientId' => !empty($this->clientId) ? $this->clientId : ClientHelper::clientId()
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        // $queryMongo = new MongoQueryCache($time, 'leads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $leads = QueryHelper::attachFormula($query_data, $query['formula']);
        foreach ($leads as $lead) {
            $id = ($lead['brokerId'] ?? '');
            $revenues[$id] = ($revenues[$id] ?? 0) + ($lead['deposit_revenue'] ?? 0);
        }

        return $revenues;
    }

    function get_broker_balances()
    {
        $brokers = $this->get_brokers();

        // --- revenues ---
        $to = strtotime(date('Y-m-01 23:59:59') . ' - 1 day');
        $from = strtotime(date('Y-m-01 00:00:00'));
        $cache_key = 'billings_overall_broker_balances_';

        $revenues = $this->get_broker_balances_revenues();
        // $revenues = Cache::get($cache_key . $to);
        // if (!$revenues) {
        //     $revenues = $this->get_broker_balances_revenues(null, $to);
        //     $seconds = strtotime("last day of this month");
        //     Cache::put($cache_key . $to, $revenues, $seconds);
        // }

        // $revenues_new = Cache::get($cache_key . $from);
        // if (!$revenues_new) {
        //     $revenues_new = $this->get_broker_balances_revenues($from, null);
        //     $seconds = 60 * 5;
        //     Cache::put($cache_key . $from, $revenues_new, $seconds);
        // }

        // foreach ($revenues_new as $brokerId => $revenue) {
        //     $revenues[$brokerId] = ($revenues[$brokerId] ?? 0) + ($revenue ?? 0);
        // }

        // --- chargebacks ---
        $chargebacks = $this->calculate_chargebacks('broker', 'broker_billing_chargebacks');

        // --- adjustments ---
        $adjustments = $this->calculate_adjustments('broker', 'broker_billing_adjustments');

        // --- payment requests ---
        $payment_requests = $this->calculate_payment_requests('broker', 'broker_billing_payment_requests');

        $balances = [];
        foreach ($brokers as $id => $broker) {

            $clientId = $broker['clientId'] ?? '';

            $is_collection = (($broker['billing_manual_status'] ?? '') == 'collection');

            if (isset($this->payload['collection']) && (bool)$this->payload['collection'] == false && $is_collection) {
                continue;
            }

            $status = ((int)($broker['status'] ?? 0) == 1);
            $account_manager = $broker['account_manager'] ?? '';
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
                $mongo = new MongoDBObjects('partner', $where);
                $mongo->update(['billing_manual_status' => 'active']);
                $is_collection = false;
            }

            $balances[] = [
                'id' => $id,
                'clientId' => $clientId,
                'name' => GeneralHelper::broker_name($broker),
                'is_collection' => $is_collection,
                'status' => $status,
                'account_manager' => $account_manager,
                'balance' => $balance
            ];
        }
        return $balances;
    }

    function get_endpoint_balances_costs($from = null, $to = null)
    {

        $costs = [];

        $this->start = $from ?? strtotime('00:00:00', -2147483648);
        $this->end = $to ?? strtotime('23:59:59');

        $time = [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
        $query = $this->endpoint_billing_general_balances_buildParameterArray();

        $conditions = [
            'match_with_broker' => 1,
            'test_lead' => 0,
            'clientId' => !empty($this->clientId) ? $this->clientId : ClientHelper::clientId()
            // ->where('UnderReview', '=', 1)->orWhere('UnderReview', '=', null)
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        // $queryMongo = new MongoQueryCache($time, 'leads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $leads = QueryHelper::attachFormula($query_data, $query['formula']);
        foreach ($leads as $lead) {
            $id = $lead['TrafficEndpoint'];
            $costs[$id] =  ($costs[$id] ?? 0) + ($lead['cost'] ?? 0);
        }

        return $costs;
    }

    function get_endpoint_balances()
    {
        $enpoints = $this->get_enpoints();

        // --- costs ---
        $to = strtotime(date('Y-m-01 23:59:59') . ' - 1 day');
        $from = strtotime(date('Y-m-01 00:00:00'));
        $cache_key = 'billings_overall_endpoint_balances_';

        $costs = $this->get_endpoint_balances_costs();

        // $costs = Cache::get($cache_key . $to);
        // if (!$costs) {
        //     $costs = $this->get_endpoint_balances_costs(null, $to);
        //     $seconds = strtotime("last day of this month");
        //     Cache::put($cache_key . $to, $costs, $seconds);
        // }

        // $costs_new = Cache::get($cache_key . $from);
        // if (!$costs_new) {
        //     $costs_new = $this->get_endpoint_balances_costs($from, null);
        //     $seconds = 60 * 5;
        //     Cache::put($cache_key . $from, $costs_new, $seconds);
        // }

        // foreach ($costs_new as $endpointId => $cost) {
        //     $costs[$endpointId] =  ($costs[$endpointId] ?? 0) + ($cost ?? 0);
        // }

        // --- chargebacks ---
        $chargebacks = $this->calculate_chargebacks('endpoint', 'endpoint_billing_chargebacks');

        // --- adjustments ---
        $adjustments = $this->calculate_adjustments('endpoint', 'endpoint_billing_adjustments');

        // --- payment requests ---
        $payment_requests = $this->calculate_payment_requests('endpoint', 'endpoint_billing_payment_requests');

        $balances = [];
        foreach ($enpoints as $id => $enpoint) {

            $clientId = $enpoint['clientId'] ?? '';

            $is_collection = (($enpoint['billing_manual_status'] ?? '') == 'collection');

            if (isset($this->payload['collection']) && (bool)$this->payload['collection'] == false && $is_collection) {
                continue;
            }

            $status = ((int)($enpoint['status'] ?? 0) == 1);
            $account_manager = $enpoint['account_manager'] ?? '';
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
                $mongo = new MongoDBObjects('TrafficEndpoints', $where);
                $mongo->update(['billing_manual_status' => 'active']);
                $is_collection = false;
            }

            $balances[] = [
                'id' => $id,
                'clientId' => $clientId,
                'name' => $enpoint['token'] ?? '',
                'is_collection' => $is_collection,
                'probation' => ((int)($enpoint['probation'] ?? 0)),
                'status' => $status,
                'account_manager' => $account_manager,
                'balance' => $balance
            ];
        }
        return $balances;
    }

    ///// ---- Overall ---- //////

    function _get_overall_brokers_balance()
    {
        $balances = $this->get_broker_balances();

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

    function _get_overall_endpoints_balance()
    {
        $balances = $this->get_endpoint_balances();

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

    public function feed_brokers_pending_payments(): array
    {
        $names = $this->get_broker_names();

        $result = ['items' => []];

        $where = [
            'status' => 1,
            'final_status' => ['$exists' => false]
        ];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects("broker_billing_payment_requests", $where);
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
                'broker' => [
                    '_id' => ($data['broker'] ?? ''),
                    'name' => ($names[$data['broker'] ?? ''] ?? '')
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

    public function feed_endpoints_pending_payments(): array
    {

        $result = ['items' => []];

        $names = $this->get_enpoint_names();

        $where = [
            'status' => 1,
            'final_status' => ['$exists' => false],
        ];
        $this->attach_client_id($where);
        $mongo = new MongoDBObjects('endpoint_billing_payment_requests', $where);
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
                'endpoint' => [
                    '_id' => $data['endpoint'],
                    'name' => $names[$data['endpoint']]
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

    public function broker_billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            'brokerId',
        ];

        $metrics = [
            'revenue',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_deposit_revenue = array('deposit_revenue' =>  [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                    $revenue = 0.0;
                    $crg= 0.0;
                    if ( __(bool)match_with_broker__ == FALSE ) {
                        $revenue = 0;
                    } elseif( __(bool)broker_cpl__ == TRUE){
						if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
							$revenue = __revenue__;
						}
                    } else{
                        if ( __(bool)broker_crg_deal__ == TRUE && __broker_crg_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                            $crg = __broker_crg_revenue__;
                        }
                        if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                            //__(bool)deposit_disapproved__ == FALSE &&

							if ((__(bool)broker_crg_ftd_uncount__ == TRUE || __(bool)broker_crg_already_paid__ == TRUE) && __(bool)broker_crg_deal__ == TRUE){
								//$crg = $crg;
							}else{
								$crg = $crg + __deposit_revenue__;
							}

                        }
                        $revenue = $crg;
                    }
                    return (float)$revenue;
                ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {
            if ($metrics == 'revenue') {
                $array['deposit_revenue'] = $_deposit_revenue;
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    public function feed_billings_brokers_balances()
    {
        $balances = $this->get_broker_balances();

        $html = '<table class="table table-payouts" style="width: 100%"><thead>';
        $html .= '<tr>';
        $html .= '<th>#</th>';
        $html .= '<th>Broker Name</th>';
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

    public function endpoint_billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            'TrafficEndpoint',
        ];

        $metrics = [
            'cost',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_cost = array('cost' => [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                $cost = 0.0;
                $crg = 0.0;

                if ( __(bool)crg_deal__ == TRUE && __crg_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                    $crg = __crg_revenue__;
                }

                if ( __(bool)isCPL__ == TRUE) {
					if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){
						$cost = __cost__;
					}
                } else
                if (
					((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
					&& __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
						//approved  FTD
						if ((__(bool)crg_ftd_uncount__ == TRUE || __(bool)crg_already_paid__ == TRUE) && __(bool)crg_deal__ == TRUE){
							$cost = $crg;
						}else{
							$cost = $crg + __cost__;
						}

                } else {
                    $cost = $crg;
                }

                return (float)$cost;
            ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {
            if ($metrics == 'cost') {
                $array['cost'] = $_cost;
                $formula['cost'] = '__cost__';
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    function _feed_billings_endpoint_balances()
    {
        $balances = $this->get_endpoint_balances();

        $html = '<table class="table table-payouts" style="width: 100%"><thead>';
        $html .= '<tr>';
        $html .= '<th>#</th>';
        $html .= '<th>Endpoint</th>';
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

        $brokers_approved = BrokerBillingPaymentRequest::query()
            ->where('final_status', '=', 1)
            ->where('final_status_changed_date', '>=', $start)
            ->where('final_status_changed_date', '<=', $end)
            ->with([
                'broker_data:partner_name,token,created_by,account_manager',
                'final_status_changed_user:name,account_email'
            ])
            ->get([
                'broker',
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
                'transaction_id'
            ])
            ->toArray();

        $endpoints_approved = TrafficEndpointBillingPaymentRequests::query()
            ->where('final_status', '=', 1)
            ->where('final_status_changed_date', '>=', $start)
            ->where('final_status_changed_date', '<=', $end)
            ->with([
                'traffic_endpoint_data:token',
                'final_status_changed_user:name,account_email'
            ])
            ->get([
                'endpoint',
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
                'transaction_id'
            ])
            ->toArray();

        return [
            'brokers_approved' => $brokers_approved,
            'endpoints_approved' => $endpoints_approved
        ];
    }
}
