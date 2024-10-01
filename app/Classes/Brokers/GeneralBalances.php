<?php

namespace App\Classes\Brokers;

use MongoDB\BSON\ObjectId;
use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\History\HistoryDB;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\NotificationReporter;
use App\Classes\History\HistoryDBAction;
use App\Helpers\CryptHelper;

class GeneralBalances
{
    private string $brokerId;
    private array $payload;

    private $start;
    private $end;

    private $collections = [
        'billing_entities' => 'broker_billing_entities',
        'billing_payment_companies_general' => 'billing_payment_companies',
        'billing_payment_methods' => 'broker_billing_payment_methods',
        'billing_payment_methods_general' => 'billing_payment_methods',
        'billing_chargebacks' => 'broker_billing_chargebacks',
        'billing_adjustments' => 'broker_billing_adjustments',
        'billing_payment_requests' => 'broker_billing_payment_requests',
        'partner' => 'partner',
    ];

    public function __construct(string $brokerId, array $payload = [])
    {
        $this->brokerId = $brokerId;
        $this->payload = $payload;
    }

    public function set_credit_amount(int $amount): bool
    {
        $financial_limit = abs($amount);
        $update = ['financial_limit' => $financial_limit];
        $where = ['_id' => new ObjectId($this->brokerId)];
        $mongo = new MongoDBObjects('partner', $where);
        return $mongo->update($update);
    }

    public function set_negative_balance_action(string $action): bool
    {
        $update = ['action_on_negative_balance' => $action];
        $where = ['_id' => new ObjectId($this->brokerId)];
        $mongo = new MongoDBObjects('partner', $where);
        $broker = $mongo->find();

        $action_on_negative_balance = $update['action_on_negative_balance'] ?? ''; // $broker['action_on_negative_balance'] ?? '';
        $financial_status = $broker['financial_status'] ?? '';
        if (
            (empty($action_on_negative_balance) || $action_on_negative_balance == 'leave_running') &&
            ($financial_status == 'hold')
        ) {
            $update['financial_status'] = 'active';
            $message = $subject = 'The broker "' . CryptHelper::decrypt_broker_name($broker['partner_name']) . '" is resumed work due to financial constraints.';
            NotificationReporter::to('financial')->slack($message);
            NotificationReporter::to('financial')->mail($message, $subject);
        }

        return $mongo->update($update);
    }

    public function get_balances_log(int $page, int $count_in_page)
    {
        $where = ['broker' => $this->brokerId];
        $mongo = new MongoDBObjects('billings_log', $where);
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
            $revenue += $lead['deposit_revenue'];
        }
        // $revenue *= -1;

        $chargebacks_balans = $this->_get_billing_chargebacks_balans();
        $chargebacks_balans = $chargebacks_balans['total'];

        $adjustments_balans = $this->_get_billing_adjustments_balans();
        $adjustment_amounts = $adjustments_balans['total'] ?? 0;

        $broker = $this->get_broker();
        $credit_total = isset($broker['financial_limit']) && (int)$broker['financial_limit'] > 0 ? (int)$broker['financial_limit'] : 0;
        $action_on_negative_balance = isset($broker['action_on_negative_balance']) && !empty($broker['action_on_negative_balance']) ? $broker['action_on_negative_balance'] : '';

        // payment request
        $where = ['final_status' => 1, 'broker' => $this->brokerId];
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
            $manual_status = $broker['billing_manual_status'] ?? 'active';
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
            'action_on_negative_balance' => $broker['action_on_negative_balance'] ?? '',
            'desc' => '[' . $desc . ']',
        ];
    }

    public function update_general_balance_logs(string $logId): bool
    {
        $where = ['_id' => new ObjectId($logId)]; //'broker' => $this->brokerId, 
        $mongo = new MongoDBObjects('billings_log', $where);
        $log = $mongo->find();
        if ($mongo->update($this->payload)) {
            $this->payload['broker'] = $this->brokerId;
            $dt = date('Y-m-d', ((array)$log['timestamp'])['milliseconds'] / 1000);
            $description = 'Changed real balance by date ' . $dt . ': $' . $this->payload['real_balance'];
            HistoryDB::add(HistoryDBAction::Update, 'billings_log', $this->payload, $logId, 'broker', [], null, $description);
            return true;
        }
        return false;
    }

    private function buildTimestamp($date_start, $date_end)
    {
        $this->start = strtotime("00:00:00", $date_start);
        $this->end = strtotime("23:59:59", $date_end);
        return [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end'   => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
    }

    private function _get_data_general_balances($date_start, $date_end)
    {
        // -- query 
        $time = $this->buildTimestamp($date_start, $date_end);
        $query = $this->billing_general_balances_buildParameterArray();

        $conditions = [
            'brokerId' => [$this->brokerId],
            'match_with_broker' => 1,
            'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachFormula($query_data, $query['formula']);

        return $data;
    }

    private function billing_general_balances_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            '_id',
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

    private function _get_billing_chargebacks_balans()
    {
        $where = ['broker' => $this->brokerId, 'final_status' => 1];
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
        $where = ['broker' => $this->brokerId];
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

    private function get_broker()
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($this->brokerId)];
        $mongo = new MongoDBObjects('partner', $where);
        return $mongo->find();
    }
}
