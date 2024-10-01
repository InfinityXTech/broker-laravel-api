<?php

namespace App\Classes\Advertisers;

use MongoDB\BSON\ObjectId;
use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\History\HistoryDB;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\NotificationReporter;
use App\Classes\History\HistoryDBAction;

class GeneralBalances
{
    private string $advertiserId;
    private array $payload;
    private $start;
    private $end;

    private $collections = [
        'billing_entities' => 'marketing_advertiser_billing_entities',
        'billing_payment_companies_general' => 'billing_payment_companies', //'marketing_advertiser_payment_companies',
        'billing_payment_methods' => 'marketing_advertiser_billing_payment_methods',
        'billing_payment_methods_general' => 'marketing_advertiser_payment_methods',
        'billing_chargebacks' => 'marketing_advertiser_billing_chargebacks',
        'billing_adjustments' => 'marketing_advertiser_billing_adjustments',
        'billing_payment_requests' => 'marketing_advertiser_billing_payment_requests',
        'marketing_advertisers' => 'marketing_advertisers',
    ];

    public function __construct(string $advertiserId, array $payload = [])
    {
        $this->advertiserId = $advertiserId;
        $this->payload = $payload;
    }

    public function set_credit_amount(int $amount): bool
    {
        $financial_limit = abs($amount);
        $update = ['financial_limit' => $financial_limit];
        $where = ['_id' => new ObjectId($this->advertiserId)];
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        return $mongo->update($update);
    }

    public function set_negative_balance_action(string $action): bool
    {
        $update = ['action_on_negative_balance' => $action];
        $where = ['_id' => new ObjectId($this->advertiserId)];
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        $advertiser = $mongo->find();

        $action_on_negative_balance = $update['action_on_negative_balance'] ?? ''; // $broker['action_on_negative_balance'] ?? '';
        $financial_status = $advertiser['financial_status'] ?? '';
        if (
            (empty($action_on_negative_balance) || $action_on_negative_balance == 'leave_running') &&
            ($financial_status == 'hold')
        ) {
            $update['financial_status'] = 'active';
            $message = $subject = 'The advertiser "' . $advertiser['token'] . '" is resumed work due to financial constraints.';
            NotificationReporter::to('marketing_financial')->slack($message);
            NotificationReporter::to('marketing_financial')->mail($message, $subject);
        }

        return $mongo->update($update);
    }

    public function get_balances_log(int $page, int $count_in_page)
    {
        $where = ['advertiser' => $this->advertiserId];
        $mongo = new MongoDBObjects('marketing_billings_log', $where);
        $count = $mongo->count();
        $list = $mongo->findMany([
            'sort' => ['timestamp' => -1],
            'skip' => (($page - 1) * $count_in_page),
            'limit' => $count_in_page
        ]);
        return ['count' => $count, 'items' => $list];
    }

    public function get_general_balance(): array
    {
        $leads = $this->_get_data_general_balances(-2147483648, time());
        $revenue = 0;
        foreach ($leads as $lead) {
            $revenue += $lead['revenue'];
        }
        // $revenue *= -1;

        $chargebacks_balans = $this->_get_billing_chargebacks_balans();
        $chargebacks_balans = $chargebacks_balans['total'];

        $adjustments_balans = $this->_get_billing_adjustments_balans();
        $adjustment_amounts = $adjustments_balans['total'] ?? 0;

        $advertiser = $this->get_advertiser();
        $credit_total = isset($advertiser['financial_limit']) && (int)$advertiser['financial_limit'] > 0 ? (int)$advertiser['financial_limit'] : 0;
        $action_on_negative_balance = isset($advertiser['action_on_negative_balance']) && !empty($advertiser['action_on_negative_balance']) ? $advertiser['action_on_negative_balance'] : '';

        // payment request
        $where = ['final_status' => 1, 'advertiser' => $this->advertiserId];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $list = $mongo->findMany();
        $adjustment_amount = 0;
        $payment_request_total = ['undefined' => 0, 'payment' => 0, 'prepayment' => 0];

        foreach ($list as $data) {
            $type = $data['type'] ?? 'undefined';
            $type = isset($payment_request_total[$type]) ? $type : 'undefined';

            $payment_request_total[$type] += $data['total'];
            $adjustment_amount += $data['adjustment_amount'] ?? 0;
        }

        $result = ($payment_request_total['prepayment'] + $payment_request_total['payment'] - $revenue + $chargebacks_balans + $adjustment_amounts);

        $status = '';
        if ($result + $credit_total < 0) {
            $status = 'Inactive';
        } else {
            $status = 'Active';
        }
        if (empty($action_on_negative_balance) || $action_on_negative_balance == 'leave_running') {
            $status = 'Active';
        }

        $desc = 'revenue: ' . RenderHelper::format_money($revenue) . ' + chargebacks total: ' . RenderHelper::format_money($chargebacks_balans);
        if ($credit_total > 0) {
            $desc .= ' | <span style="color:red;font-weight:bold;">you have credit: ' . RenderHelper::format_money($credit_total) . '</span>';
        }

        if ($payment_request_total['payment'] > 0 || $payment_request_total['undefined'] > 0) {
            $desc .= ' | payment request: ' . RenderHelper::format_money($payment_request_total['payment'] + $payment_request_total['undefined']);
        }
        if ($payment_request_total['prepayment'] > 0) {
            $desc .= ' | (pre) payment request: ' . RenderHelper::format_money($payment_request_total['prepayment']);
        }
        if (($adjustment_amount + $adjustment_amounts) != 0) {
            $desc .= ' | adjustment amount: ' . RenderHelper::format_money($adjustment_amount) . ' ^ ' . RenderHelper::format_money($adjustment_amounts);
        }

        $manual_status = false;
        if ($result + $credit_total < 0) {
            $manual_status = $advertiser['billing_manual_status'] ?? 'active';
        }

        $manual_statuses = [
            'active' => 'Active',
            'collection' => 'Collection',
        ];

        return [
            'status' => $status,
            'manual_status' => $manual_status,
            'manual_statuses' => $manual_statuses,
            'balance' => $result,
            'revenue' => $revenue,
            'chargebacks' => $chargebacks_balans,
            'credit' => $credit_total,
            'payment' => $payment_request_total['payment'] + $payment_request_total['undefined'],
            'prepayment' => $payment_request_total['prepayment'],
            'adjustments' => $adjustment_amounts,
            'payment_adjustments' => $adjustment_amount,
            'action_on_negative_balance' => $advertiser['action_on_negative_balance'] ?? '',
            'desc' => '[' . $desc . ']',
        ];
    }

    public function update_general_balance_logs(string $logId): bool
    {
        $where = ['_id' => new ObjectId($logId)]; //'broker' => $this->advertiserId,
        $mongo = new MongoDBObjects('marketing_billings_log', $where);
        $log = $mongo->find();
        if ($mongo->update($this->payload)) {
            $this->payload['advertiser'] = $this->advertiserId;
            $dt = date('Y-m-d', ((array)$log['timestamp'])['milliseconds'] / 1000);
            $description = 'Changed real balance by date ' . $dt . ': $' . $this->payload['real_balance'];
            HistoryDB::add(HistoryDBAction::Update, 'billings_log', $this->payload, $logId, 'advertiser', [], null, $description);
            return true;
        }
        return false;
    }

    private function buildTimestamp($date_start, $date_end)
    {
        $this->start = strtotime("00:00:00", $date_start);
        $this->end = strtotime("23:59:59", $date_end);

        $time_range = [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end'   => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
        $time_range['where'] = [
            'EventType' => ['$ne' => 'CLICK'],
            'EventTimeStamp' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
                '$lte' => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
            ],
        ];

        return $time_range;

        // return [
        //     'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
        //     'end'   => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        // ];
    }

    private function _get_data_general_balances($date_start, $date_end)
    {
        // -- query
        $time = $this->buildTimestamp($date_start, $date_end);
        $query = $this->billing_general_balances_buildParameterArray();

        $conditions = [
            'AdvertiserId' => [$this->advertiserId],
            // 'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'mleads', $query['query'], $condition);
        // $queryMongo = new MongoQuery($time, ['mleads', 'mleads_event'], $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachMarketingFormula($query_data, $query['formula']);

        return $data;
    }

    private function billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            '_id',
            'AdvertiserId'
        ];

        $metrics = [
            'revenue',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_revenue = array('revenue' =>  [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                    $revenue = 0.0;
                    if ( __(bool)Approved__ == TRUE or  __(bool)Rejected__ == TRUE) {
                        $revenue = __AdvertiserPayout__;
					}
                    return (float)$revenue;
                ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {
            if ($metrics == 'revenue') {
                $array['revenue'] = $_revenue;
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    private function _get_billing_chargebacks_balans()
    {
        $where = ['advertiser' => $this->advertiserId];
        $mongo = new MongoDBObjects($this->collections['billing_chargebacks'], $where);
        $data = $mongo->aggregate([
            'group' => [
                'count' => [
                    '$sum' => 1
                ],
                'total' => [
                    '$sum' => '$amount'
                ]
            ]
        ], true);

        if (!isset($data)) {
            $data = ['total' => 0, 'count' => 0];
        }
        if (!isset($data['total'])) {
            $data = ['total' => 0, 'count' => 0];
        }
        return $data;
    }

    private function _get_billing_adjustments_balans()
    {
        $where = ['advertiser' => $this->advertiserId];
        $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $where);
        $data = $mongo->aggregate([
            'group' => [
                'count' => [
                    '$sum' => 1
                ],
                'total' => [
                    '$sum' => '$amount'
                ]
            ]
        ], true);

        if (!isset($data)) {
            $data = ['total' => 0, 'count' => 0];
        }
        if (!isset($data['total'])) {
            $data = ['total' => 0, 'count' => 0];
        }
        return $data;
    }

    private function get_advertiser()
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($this->advertiserId)];
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        return $mongo->find();
    }
}
