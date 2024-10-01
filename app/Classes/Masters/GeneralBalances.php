<?php

namespace App\Classes\Masters;

use MongoDB\BSON\ObjectId;
use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;

class GeneralBalances
{
    private string $masterId;

    private $start;
    private $end;


    private $collections = [
        'billing_entities' => 'masters_billing_entities',
        'billing_payment_companies_general' => 'billing_payment_companies',
        'billing_payment_methods' => 'masters_billing_payment_methods',
        'billing_payment_methods_general' => 'billing_payment_methods',
        'billing_chargebacks' => 'masters_billing_chargebacks',
        'billing_adjustments' => 'masters_billing_adjustments',
        'billing_payment_requests' => 'masters_billing_payment_requests',
        'partner' => 'partner',
    ];

    public function __construct(string $masterId)
    {
        $this->masterId = $masterId;
    }

    public function get_general_balance(): array
    {
        $timeframe = date('m/d/Y', -2147483648) . ' - ' .  date('m/d/Y');

        $is_financial = Gate::allows('role:financial'); //permissionsManagement::is_current_user_role('financial');

        $leads = $this->_get_data_general_balances($timeframe);

        $cost = 0;
        foreach ($leads as $lead) {
            $cost += ($lead['cost'] ?? 0);
        }
        // $cost *= -1;
        $chargebacks_balans = $this->_get_billing_chargebacks_balans();

        $chargebacks_balans = $chargebacks_balans['total'];

        // $endpoint = $this->get_endpoint();
        // $credit_total = isset($endpoint['financial_limit']) && (int)$endpoint['financial_limit'] > 0 ? (int)$endpoint['financial_limit'] : 0;
        // $action_on_negative_balance = isset($endpoint['action_on_negative_balance']) && !empty($endpoint['action_on_negative_balance']) ? $endpoint['action_on_negative_balance'] : '';
        $action_on_negative_balance = 0;
        $credit_total = 0;

        // payment request
        $where = ['final_status' => 1, 'master' => $this->masterId];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $list = $mongo->findMany();
        $payment_request_total = ['undefined' => 0, 'payment' => 0, 'prepayment' => 0];

        $adjustment_amount = 0;
        $adjustments_balans = $this->_get_billing_adjustments_balans();
        $adjustment_amounts = $adjustments_balans['total'] ?? 0;

        foreach ($list as $data) {
            $type = isset($data['type']) ? $data['type'] : 'undefined';
            if (!isset($payment_request_total[$type])) {
                $payment_request_total[$type] = 0;
            }
            if (!isset($data['total'])) { // it must be deleted
                $leads = json_decode($data['json_leads'], true);
                $subGroupBy = $this->_get_feed_group_by_billing_payment_request($leads);
                foreach ($subGroupBy as $groupKey => $datas) {
                    $payment_request_total[$type] += $datas['total'];
                }
            } else {
                $payment_request_total[$type] += isset($data['total']) ? $data['total'] : 0;
                $adjustment_amount += isset($data['adjustment_amount']) ? $data['adjustment_amount'] : 0;
            }
        }

        $result = ($payment_request_total['prepayment'] + $payment_request_total['payment'] - $cost + $chargebacks_balans + $adjustment_amounts);

        $status = '';
        if ($result + $credit_total < 0) {
            $status = 'Inactive';
        } else {
            $status = 'Active';
        }

        if (empty($action_on_negative_balance) || $action_on_negative_balance == 'leave_running') {
            $status = 'Active';
        }

        $echo_result = number_format($result, 2, ',', ' ');
        $echo_result = str_replace('$-', '-$', '$' . $echo_result);

        $desc = 'cost: ' . RenderHelper::format_money($cost) . ' + chargebacks total: ' . RenderHelper::format_money($chargebacks_balans);
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

        return [
            'status' => $status,
            'balance' => $result,
            'chargebacks' => $chargebacks_balans,
            'credit' => $credit_total,
            'payment' => $payment_request_total['payment'] + $payment_request_total['undefined'],
            'prepayment' => $payment_request_total['prepayment'],
            'adjustments' => $adjustment_amounts,
            'payment_adjustments' => $adjustment_amount,
            'desc' => '[' . $desc . ']',
        ];
    }

    private function buildTimestamp($timeframe)
    {
        $explode = explode(' - ', $timeframe);

        $time_range = array();

        $givebackstamp = function ($d) {
            $array = explode('/', $d);
            if (count($array) > 1) {
                return $array[2] . '-' . $array[0] . '-' . $array[1];
            }
            return trim($d);
        };

        $this->start = strtotime($givebackstamp($explode[0]) . " 00:00:00");
        $this->end = strtotime($givebackstamp($explode[1]) . " 23:59:59");

        $start = new \MongoDB\BSON\UTCDateTime($this->start * 1000);
        $end = new \MongoDB\BSON\UTCDateTime($this->end * 1000);

        $time_range['start'] = $start;
        $time_range['end'] = $end;

        return $time_range;
    }

    // Master Affiliate
    private function _get_feed_group_by_billing_payment_request_master_affiliate($data)
    {
        $groupBy = [];
        foreach ($data as $ndata => $datas) {

            $name = 'country[' . $datas['country'] . '] | crg_deal[' . ($datas['crg_deal'] ? '1' : '0') . '] | isCPL[' . ($datas['isCPL'] ? '1' : '0') . ']';

            if (!isset($groupBy[$name])) {
                $groupBy[$name] = [];
            }

            $groupBy[$name][] = $datas;
        }

        $subGroupBy = [];
        foreach ($groupBy as $groupKey => $values) {
            foreach ($values as $datas) {

                // CRG
                if (strpos($groupKey, 'crg_deal[1]') !== false) {

                    $name = $groupKey . '///crg_payout[' . $datas['crg_master_payout'] . '] | cost[' . $datas['cost'] . ']'; //| cr[' . $datas['cr'] . '] 
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['cost'];
                        $datas['Leads'] = 1;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['crg_leads'] = $subGroupBy[$name]['crg_leads'] + $datas['crg_leads'];
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                    }
                } else
                    // CPL
                    if (strpos($groupKey, 'isCPL[1]') !== false) {
                        $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['cost'];
                            $datas['Leads'] = 1;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                        }
                    }
                    // CPA
                    else {
                        $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['cost'];
                            $datas['Leads'] = 1;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                            $subGroupBy[$name]['crg_master_revenue'] = $subGroupBy[$name]['crg_master_revenue'] +  $datas['crg_master_revenue'];
                            // $subGroupBy[$name]['crg_payout'] = $subGroupBy[$name]['crg_payout'] +  $datas['crg_payout'];
                        }
                    }
            }
        }

        // recalc deposit when cost is zero (вроде это для расчета CR убрали так как вроде CR вроде нет пока)
        // foreach ($subGroupBy as $groupKey => $datas) {
        //     if ($datas['cost'] == 0) {
        //         // becouse of cost zero put deposit to another row
        //         foreach ($subGroupBy as $groupKey2 => $datas2) {
        //             $name = 'country[' . $datas2['country'] . '] | crg_deal[' . ($datas2['crg_deal'] ? '1' : '0') . '] | isCPL[' . ($datas2['isCPL'] ? '1' : '0') . ']';
        //             $name = $name . '///' .
        //                 'crg_percentage_id[' . $datas2['crg_percentage_id'] . '] | ' .
        //                 'crg_percentage[' . $datas2['crg_percentage'] . '] | ' .
        //                 'crg_payout[' . $datas2['crg_payout'] . ']';
        //             if ($datas2['cost'] != 0 && $name == substr($groupKey2, 0, strlen($name))) {
        //                 $subGroupBy[$groupKey2]['Depositors'] += $datas['Depositors'] ?? 0;
        //             }
        //         }
        //     }
        // }

        return $subGroupBy;
    }

    // Master Brand
    private function _get_feed_group_by_billing_payment_request_master_brand($data)
    {
        $groupBy = [];
        foreach ($data as $ndata => $datas) {

            $name = 'country[' . $datas['country'] . '] | crg_deal[' . ($datas['broker_crg_deal'] ? '1' : '0') . '] | isCPL[' . ($datas['broker_cpl'] ? '1' : '0') . ']';

            if (!isset($groupBy[$name])) {
                $groupBy[$name] = [];
            }

            $groupBy[$name][] = $datas;
        }

        $subGroupBy = [];
        foreach ($groupBy as $groupKey => $values) {
            foreach ($values as $datas) {

                // CRG
                if (strpos($groupKey, 'crg_deal[1]') !== false) {


                    $name = $groupKey . '///crg_payout[' . $datas['broker_crg_master_payout'] . '] | cost[' . $datas['cost'] . ']'; //| cr[' . $datas['cr'] . '] 
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['cost'];
                        $datas['Leads'] = 1;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['broker_crg_leads'] = $subGroupBy[$name]['broker_crg_leads'] + $datas['broker_crg_leads'];
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                    }
                } else
                    // CPL
                    if (strpos($groupKey, 'isCPL[1]') !== false) {
                        $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['cost'];
                            $datas['Leads'] = 1;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                        }
                    }
                    // CPA
                    else {
                        $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['cost'];
                            $datas['Leads'] = 1;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                            $subGroupBy[$name]['broker_crg_master_revenue'] = $subGroupBy[$name]['broker_crg_master_revenue'] +  $datas['broker_crg_master_revenue'];
                            // $subGroupBy[$name]['crg_payout'] = $subGroupBy[$name]['crg_payout'] +  $datas['crg_payout'];
                        }
                    }
            }
        }

        // recalc deposit when cost is zero (вроде это для расчета CR убрали так как вроде CR вроде нет пока)
        // foreach ($subGroupBy as $groupKey => $datas) {
        //     if ($datas['cost'] == 0) {
        //         // becouse of cost zero put deposit to another row
        //         foreach ($subGroupBy as $groupKey2 => $datas2) {
        //             $name = 'country[' . $datas2['country'] . '] | crg_deal[' . ($datas2['crg_deal'] ? '1' : '0') . '] | isCPL[' . ($datas2['isCPL'] ? '1' : '0') . ']';
        //             $name = $name . '///' .
        //                 'crg_percentage_id[' . $datas2['crg_percentage_id'] . '] | ' .
        //                 'crg_percentage[' . $datas2['crg_percentage'] . '] | ' .
        //                 'crg_payout[' . $datas2['crg_payout'] . ']';
        //             if ($datas2['cost'] != 0 && $name == substr($groupKey2, 0, strlen($name))) {
        //                 $subGroupBy[$groupKey2]['Depositors'] += $datas['Depositors'] ?? 0;
        //             }
        //         }
        //     }
        // }

        return $subGroupBy;
    }

    private function _get_feed_group_by_billing_payment_request($data)
    {
        // ['1' => 'Master Affiliate', '2' => 'Master Brand']
        $master = $this->get_master();
        if ((int)$master['type'] == 1) {
            return $this->_get_feed_group_by_billing_payment_request_master_affiliate($data);
        } else {
            return $this->_get_feed_group_by_billing_payment_request_master_brand($data);
        }
    }

    private function _get_data_general_balances($timeframe)
    {
        $master = $this->get_master();
        $query_fields = [
            '1' => 'MasterAffiliate',
            '2' => 'master_brand'
        ];
        $query_field = $query_fields[$master['type']];

        // -- query 
        $time = $this->buildTimestamp($timeframe);
        $query = $this->billing_general_balances_buildParameterArray();

        $conditions = [
            $query_field => [$this->masterId],
            'match_with_broker' => 1,
            'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachFormula($query_data, $query['formula']);

        return $data;
    }

    public function billing_general_balances_buildParameterArray()
    {
        // ['1' => 'Master Affiliate', '2' => 'Master Brand']
        $master = $this->get_master();
        if ((int)$master['type'] == 1) {
            return $this->billing_general_balances_buildParameterArray_master_affiliate();
        } else {
            return $this->billing_general_balances_buildParameterArray_master_brand();
        }
    }

    // Master Affiliate
    public function billing_general_balances_buildParameterArray_master_affiliate()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
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
                if ( __(bool)match_with_broker__ == FALSE ) {
                    $cost = 0;
                }elseif( __(bool)isCPL__ == TRUE) {
					if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){	
						$cost = __Mastercost__;
					}
                }else{
                    
                    if ( __(bool)crg_deal__ == TRUE && __crg_master_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                        $crg = __crg_master_revenue__;
                    }
                    
                    if ( 
						((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
						&& __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
							//approved  FTD
							if ((__(bool)crg_ftd_uncount__ == TRUE || __(bool)crg_already_paid__ == TRUE) && __(bool)crg_deal__ == TRUE){
								$cost = $crg;
							}else{
								$cost = $crg + __master_affiliate_payout__;								
							}
                    }else{
                        $cost = $crg;
                    }
                    
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

    // Master Brand
    public function billing_general_balances_buildParameterArray_master_brand()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
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
                if ( __(bool)match_with_broker__ == FALSE ) {
                    $cost = 0;
                }elseif( __(bool)broker_cpl__ == TRUE) {
					if(__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ){	
						$cost = __Master_brand_cost__;
					}
                }else{
                    
                    if ( __(bool)broker_crg_deal__ == TRUE && __broker_crg_master_revenue__ > 0 &&__Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                        $crg = __broker_crg_master_revenue__;
                    }
                    
                    if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                        //&& __(bool)deposit_disapproved__ == FALSE
                        //approved  FTD
                        if ((__(bool)broker_crg_ftd_uncount__ == TRUE || __(bool)broker_crg_already_paid__ == TRUE) && __(bool)broker_crg_deal__ == TRUE){
                            $cost = $crg;
                        }else{
                            $cost = $crg + __master_brand_payout__;								
                        }
                    }else{
                        $cost = $crg;
                    }
                    
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

    private function _get_billing_chargebacks_balans()
    {
        $where = ['master' => $this->masterId];
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
        $where = ['master' => $this->masterId];
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

    private function get_master()
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($this->masterId)];
        $mongo = new MongoDBObjects('Masters', $where);
        return $mongo->find();
    }
}
