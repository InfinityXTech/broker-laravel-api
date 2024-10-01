<?php

namespace App\Classes\Brokers;

use App\Models\User;
use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\BillingPaymentMethods;
use App\Notifications\SlackNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Models\Brokers\BrokerBillingPaymentMethod;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Models\Brokers\BrokerBillingPaymentRequest;

class PaymentRequests
{
    private string $brokerId;

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

    private $start;
    private $end;

    public function __construct(string $brokerId)
    {
        $this->brokerId = $brokerId;
    }

    public function feed(bool $only_completed): Collection
    {
        $where = [
            'broker' => $this->brokerId,
            'payment_request' => ['$exists' => true]
        ];
        $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $where);
        $adjusted = $mongo->aggregate([
            'group' => [
                '_id' => '$payment_request',
                'total' => ['$sum' => '$amount']
            ]
        ], false, false);
        $adjusted_totals = [];
        foreach ($adjusted as $group) {
            $adjusted_totals[$group['_id']] = $group['total'];
        }

        $where = ['broker' => $this->brokerId];
        if ($only_completed) {
            $where['final_status'] = 1;
        }
        $collection = BrokerBillingPaymentRequest::where($where)
            ->get()
            ->sortByDesc(function ($item, $key) {
                $a = (array)($item->timestamp ?? []);
                return intval($a['milliseconds'] ?? 0);
            })
            ->values();

        foreach ($collection as &$item) {

            $is_request_transaction_id = false;

            if (!empty($item->payment_method)) {
                $payment_method = BillingPaymentMethods::findOrFail($item->payment_method);
                $is_request_transaction_id = ($payment_method->payment_method == 'crypto' &&
                    ($payment_method->currency_crypto_code == 'usdt' || $payment_method->currency_crypto_code == 'btc' ||
                        $payment_method->currency_code == 'usdt' || $payment_method->currency_code == 'btc'
                    ));
            }

            $item->total_fee = InvoiceDocument::get_fee_value($item->total, $item->payment_fee);
            $item->total_adjust = $adjusted_totals[$item->_id] ?? 0;
            $item->is_request_transaction_id = $is_request_transaction_id;
        }
        return $collection;
    }

    public function approve(string $id, array $payload): bool
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $payment_request = $mongo->find();

        if ((($payment_request['type'] ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $update = [
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(time() * 1000),
            'status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 1,
            'payment_method' => $payload['payment_method'],
            'payment_fee' => $payload['payment_fee'],
            'billing_from' => $payload['billing_from'],
            'billing_entity' => $payload['billing_entity'],
        ];

        return $mongo->update($update);
    }

    public function change(string $id, array $payload): bool
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);

        $payment_request = $mongo->find();

        if (($payment_request['final_status'] ?? 0) != 0) {
            throw new \Exception('You cannot change financial approved or rejected request');
        }

        return $mongo->update([
            'payment_method' => $payload['payment_method'],
            'payment_fee' => $payload['payment_fee'],
            'billing_from' => $payload['billing_from'],
        ]);
    }

    public function reject(string $id): bool
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);

        return $mongo->update([
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(time() * 1000),
            'status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 2
        ]);
    }

    private function get_broker()
    {
        $token = $this->brokerId;
        $where = ['_id' => new \MongoDB\BSON\ObjectId($token)];
        $mongo = new MongoDBObjects('partner', $where);
        return $mongo->find();
    }

    public function fin_approve(string $id, array $payload): bool
    {
        $date_pay = $payload['date_pay'];

        $model = BrokerBillingPaymentRequest::findOrFail($id);

        if ((($model->type ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $payload['final_status_changed_date'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
        $payload['final_status_changed_user_id'] = GeneralHelper::get_current_user_token();
        $payload['final_status_changed_user_ip'] = GeneralHelper::get_user_ip();
        $payload['final_status_changed_user_ua'] = $_SERVER["HTTP_USER_AGENT"];
        $payload['final_status_date_pay'] = GeneralHelper::ToMongoDateTime($date_pay);
        $payload['final_status'] = 1;

        // NotificationReporter::to('finance_approved')->slack($message);
        // NotificationReporter::to('finance_approved')->mail($message, $subject);

        StorageHelper::syncFiles('billing_payment_requests', $model, $payload, 'final_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $user = Auth::user();
        $broker = $this->get_broker();
        $subject = 'New Payment Request Financial Approv';
        $message = $subject . ' :';
        $message .= ' Broker ' . GeneralHelper::broker_name($broker);
        $message .= ', User ' . ($user->name ?? '');
        $message .= ', Total $' . ($model->total ?? 0);

        Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('finance_approved', $message)));

        return $model->update($payload);
    }

    public function fin_reject(string $id): bool
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);

        return $mongo->update([
            'final_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'final_status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'final_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'final_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'final_status' => 2
        ]);
    }

    public function archive(string $id): bool
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);

        $payment_request = $mongo->find();

        if ($payment_request['status'] != 2 && $payment_request['final_status'] != 2) {
            throw new \Exception('You cannot archive payment request that in not rejected');
        }
        return $mongo->update([
            'sub_status' => 'archive',
        ]);
    }

    public function real_income(string $id, array $payload): bool
    {
        $real_income = (float)$payload['income'];

        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $payment_request = $mongo->find();

        $where = ['payment_request' => $id];
        $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $where);
        $adjusted = $mongo->aggregate([
            'group' => [
                'total' => ['$sum' => '$amount']
            ]
        ], true);
        $adjusted_income = $adjusted['total'] ?? 0;
        $expected_income = $payment_request['total'];

        $delta = round($real_income - $expected_income - $adjusted_income, 2);

        if ($delta != 0) {
            $ts = (array)$payment_request['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $description = 'Real income for payment request ' . $timestamp . ' has been adjusted.';
            if (isset($adjusted) && isset($adjusted['total'])) {
                $description .= ' Last Real Income: ' . ($expected_income + $adjusted_income) . '$ Current Real Income: ' . $real_income . '$';
            } else {
                $description .= ' Expected Income: ' . $expected_income . '$ Current Real Income: ' . $real_income . '$';
            }

            $insert = array();
            $insert['broker'] = $this->brokerId;
            $insert['payment_request'] = $id;
            $insert['amount'] = $delta;
            $insert['description'] = $description;

            $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $insert);
            $mongo->insert();
        }
        return true;
    }

    public function download_invoice(string $id)
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id)
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $payment_request = $mongo->find();

        if (!isset($payment_request['invoice'])) {
            $invoice_id = InvoiceDocument::insert($this->collections['billing_payment_requests'], $id);
            $mongo->update(['invoice' => $invoice_id]);
            $payment_request = $mongo->find();
        }

        $invoice = new InvoiceDocument($payment_request['invoice']);

        $billing_from = [];
        if (isset($payment_request['billing_from'])) {
            $where = [
                '_id' => new \MongoDB\BSON\ObjectId($payment_request['billing_from'])
            ];
            $mongo = new MongoDBObjects($this->collections['billing_payment_companies_general'], $where);
            $billing_from = $mongo->find();
            $billing_from['organization_address'] = array_filter(array_map('trim', explode("\n", $billing_from['organization_address'])));
        }
        if (empty($billing_from)) {
            $billing_from = [
                'organization_name' => "Stolos Group Limited",
                'organization_address' => [
                    "9/F Amtel Bldg.",
                    "148 Des Vpeux Rd,",
                    "Central Hong Kong."
                ]
            ];
        }

        $ts = (array)$payment_request['timestamp'];
        $mil = $ts['milliseconds'];
        $invoice->set_date($mil / 1000);

        $invoice->set_from($billing_from);

        $billing_entity_id = $payment_request['billing_entity'];
        if (!empty($billing_entity_id)) {
            $billing_entity = $this->_get_billing_entity($billing_entity_id);
            $invoice->set_to(InvoiceDocument::get_billing_entity_details($billing_entity));
        }

        $payment_method_id = $payment_request['payment_method'];
        if (!empty($payment_method_id)) {
            $payment_methods = $this->_get_billing_payment_methods();
            $payment_method = $payment_methods[$payment_method_id];
            $invoice->set_payment_method($payment_method);
        }

        $invoice->add_payment_request_items($payment_request);

        $invoice->update();
        $invoice->render_to_output();
    }

    public function create_payment_request(array $payload): string
    {
        $payment_request_type = $payload['payment_request_type'];

        $leads = 0;
        $cost = 0;
        $total = 0;
        $data = [];

        if (empty($payment_request_type) || $payment_request_type == 'payment') {

            $timeframe = $payload['timeframe'];
            $time_range = $this->buildTimestamp($timeframe);

            $id = false;

            $get_data_info = function ($data) {
                $id = (array)$data['_id'];
                $id = $id['oid'];

                $from = 'None';
                if (isset($data['from']) && !empty($data['from'])) {
                    $ts = (array)$data['from'];
                    $mil = $ts['milliseconds'];
                    $seconds = $mil / 1000;
                    $from = date("Y-m-d", $seconds);
                }

                $to = 'None';
                if (isset($data['to']) && !empty($data['to'])) {
                    $ts = (array)$data['to'];
                    $mil = $ts['milliseconds'];
                    $seconds = $mil / 1000;
                    $to = date("Y-m-d", $seconds);
                }

                $ts = (array)$data['timestamp'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $timestamp = date("Y-m-d H:i:s", $seconds);

                $user = User::query()->find((string)$data['created_by']);

                return [$id, $from, $to, $timestamp, $user];
            };

            // the same period
            $where = [
                'broker' => $this->brokerId,
                'from' => $time_range['start'],
                'to' => $time_range['end'],
                '$or' => [
                    ['chargeback' => ['$exists' => false]],
                    ['chargeback' => false]
                ]
            ];
            $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
            $requests = $mongo->findMany();
            foreach ($requests as $data) {
                if (isset($data) && isset($data['_id'])) {
                    list($id, $from, $to, $timestamp, $user) = $get_data_info($data);
                    if (
                        (isset($data['status']) && (int)$data['status'] == 2) ||
                        (isset($data['affiliate_status']) && (int)$data['affiliate_status'] == 2) ||
                        (isset($data['final_status']) && (int)$data['final_status'] == 2)
                    ) {
                    } else {
                        throw new \Exception('Payment request for such time [' . $from . ' - ' . $to . '] frame is already exist. Created by ' . $user->name . ' at ' . $timestamp);
                    }
                }
            }

            // cross period
            $where = [
                'broker' => $this->brokerId,
                'status' => ['$ne' => 2],
                'affiliate_status' => ['$ne' => 2],
                'final_status' => ['$ne' => 2],
                'chargeback' => ['$exists' => false],
                '$and' => [
                    [
                        '$or' => [
                            ['from' => ['$gte' => $time_range['start'], '$lte' => $time_range['end']]],
                            ['to' => ['$gte' => $time_range['start'], '$lte' => $time_range['end']]],
                        ]
                    ],
                    [
                        '$or' => [
                            ['chargeback' => ['$exists' => false]],
                            ['chargeback' => false]
                        ]
                    ]
                ]
            ];
            $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
            $data = $mongo->find();
            if (isset($data) && isset($data['_id'])) {
                list($id, $from, $to, $timestamp, $user) = $get_data_info($data);
                throw new \Exception('The period overlaps with the existing approved one [' . $from . ' - ' . $to . ']. Created by ' . $user->name . ' at ' . $timestamp);
            }

            $timeframe = $payload['timeframe'];
            $data = $this->_get_feed_billing_payment_request($timeframe);

            $subGroupBy = $this->_get_feed_group_by_billing_payment_request($data);

            foreach ($subGroupBy as $groupKey => $datas) {
                $leads += $datas['Leads'] ?? 0;
                $cost += $datas['cost'] ?? 0;
                $total += $datas['total'] ?? 0;
            }
        } else {
            $total = $cost = $payload['amount'];
        }

        // if ($id != false) {
        //     $where = ['_id' => new MongoDB\BSON\ObjectId($id)];
        //     $update = [
        //         'count' => count($data),
        //         'json_leads' => json_encode($data)
        //     ];
        //     $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        //     $mongo->update($update);
        // } else
        {

            $adjustment_amount = (int)$payload['adjustment_amount'];
            $adjustment_description = $payload['adjustment_description'] ?? '';
            $total += $adjustment_amount;

            $insert = [
                'broker' => $this->brokerId,
                'created_by' => GeneralHelper::get_current_user_token(),
                'type' => $payment_request_type,
                'from' => $time_range['start'] ?? null,
                'to' => $time_range['end'] ?? null,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'status' => 0,
                // 'count' => count($data),
                'leads' => $leads,
                'cost' => $cost,
                'adjustment_amount' => $adjustment_amount,
                'adjustment_description' => $adjustment_description,
                'total' => $total,
                'json_leads' => json_encode($data)
            ];

            $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);

            $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $insert);

            $id = $mongo->insertWithToken();
        }
        return $id;
    }

    public function view_calculations(string $id)
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $data = $mongo->find();
        $leads = json_decode($data['json_leads'], true);

        return $this->_feed_billing_payment_request($data, $leads);
    }

    public function pre_create_query(array $payload)
    {
        $timeframe = $payload['timeframe'];
        $payment_request = [
            'adjustment_amount' => $payload['adjustment_amount'],
            'adjustment_description' => $payload['adjustment_description'] ?? null,
        ];
        $data = $this->_get_feed_billing_payment_request($timeframe);

        return $this->_feed_billing_payment_request($payment_request, $data);
    }

    private function _feed_billing_payment_request($payment_request = [], $data = null)
    {
        $subGroupBy = $this->_get_feed_group_by_billing_payment_request($data);

        $countries = GeneralHelper::countries();

        // -- build

        $response = [];

        foreach ($subGroupBy as $groupKey => $datas) {

            // if ($datas['deposit_revenue'] == 0 && strpos($groupKey, 'crg_deal[1]') === false) {
            //     continue;
            // }
            if ($datas['deposit_revenue'] == 0) {
                continue;
            }

            $response_row = [];

            $datas['cr'] = round($datas['Leads'] > 0 ? (($datas['Depositors'] / $datas['Leads']) * 100) : 0, 2);
            if (strpos($groupKey, 'broker_crg_deal[1]') !== false) {

                $broker_crg_percentage_id = (!empty($datas['broker_changed_crg_percentage_id'] ?? '') ? $datas['broker_changed_crg_percentage_id'] : $datas['broker_crg_percentage_id']);

                $broker_crg_percentage = (!empty($datas['broker_changed_crg_percentage'] ?? '') ? $datas['broker_changed_crg_percentage'] : $datas['broker_crg_percentage']);

                //$broker_crg_payout= (!empty($datas['broker_changed_crg_payout'] ?? '') ? $datas['broker_changed_crg_payout'] : $datas['broker_crg_payout']);

                $cnt = 0;
                if ($datas['Depositors'] > $datas['Leads'] && $datas['deposit_revenue'] > 299) {
                    $cnt = number_format($datas['Depositors'], 0, '.', ' ') . ' FTD\'s';
                } elseif ($broker_crg_percentage > $datas['cr'] || $datas['deposit_revenue'] < 299) {
                    $cnt = number_format($datas['Leads'], 0, '.', ' ') . ' Leads';
                } else {
                    $cnt = number_format($datas['Depositors'], 0, '.', ' ') . ' FTD\'s';
                }

                //150 leads Poland with (min CRG 8%) cr 9% = $11475

                $counties_str = $countries[strtolower($datas['country'])]; //думаю нужно вытягивать со сделки
                // foreach($datas['countries'] as $country) {
                //     $counties_str .= (!empty($counties_str) ? ', ' : '') . $countries[$country];
                // }

                $response_row = [
                    '_id' => $broker_crg_percentage_id,
                    'nomination' =>
                    // '<pre>' . print_r($datas,true).'</pre>'.
                    '<strong>CRG. </strong>' . /*$datas['crg_leads'] . ' leads ' .*/ $counties_str .
                        ' with (min CRG ' . $broker_crg_percentage . '%) cr ' . $datas['cr'] . '%' .
                        (
                            (Gate::has('custom[show_crg_deal_id]') && Gate::allows('custom[show_crg_deal_id]'))
                            ?
                            '<small style="display:block;color:gray;font-size:10px;">CRG ID: ' . $broker_crg_percentage_id  . '</small>'
                            : ''
                        ) .
                        '<div style="display:none"><br/>(calculated by $' . $datas['broker_crg_payout'] . ' payout, $' . $datas['broker_crg_revenue'] . ' crg revenue)<br/></div>
                            <div style="color:gray;display:none">' . print_r($datas, true) . '</div>',
                    //<div style="color:gray;display:none">broker_crg_percentage_id: ' . $datas['broker_crg_percentage_id'] . ', Depositors: ' . $datas['Depositors'] . ', min CRG is ' . ($datas['broker_crg_revenue'] > 0 ? '' : 'NOT') . ' win!</div>' .

                    'count' => $cnt,
                    'revenue' => $datas['deposit_revenue'],
                    'total' => $datas['total'],
                ];
                // if CRG deal is fail
            } else if (strpos($groupKey, 'isCPL[1]') !== false) {
                //120 Leads Germany CPL 25 = $3000
                // $html .= $datas['Leads'] . ' leads ' . $countries[$datas['country']] . ' CPL ' . $datas['deposit_revenue'] . ' = $' . $datas['deposit_revenue'];

                $response_row = [
                    'nomination' => '<strong>CPL. </strong>' . $countries[strtolower($datas['country'])],
                    'count' => number_format($datas['Leads'], 0, '.', ' ') . ' Leads',
                    'revenue' => $datas['deposit_revenue'],
                    'total' => $datas['total'],
                ];
            } else {
                //3 FTD IT CPA 985 = $2955
                // $html .= $datas['Leads'] . ' FTD ' . $countries[$datas['country']] . ' CPA ' . $datas['deposit_revenue'] . ' = $' . $datas['deposit_revenue'];

                $response_row = [
                    'nomination' =>
                    '<strong>CPA.</strong> ' . $countries[strtolower($datas['country'])] . ' cr ' . $datas['cr'] . '%' .
                        '<div style="color:gray;display:none">' . print_r($datas, true) . '</div>',

                    'count' => number_format($datas['Depositors'], 0, '.', ' ') .  ' FTD\'s',
                    'revenue' => $datas['deposit_revenue'],
                    'total' => $datas['total'],
                ];
            }

            $response[] = $response_row;
        }

        $adjustment_amount = $payment_request['adjustment_amount'] ?? 0;
        if ($adjustment_amount != 0) {
            $response[] = [
                'nomination' => 'Adjustment Amount' . (isset($payment_request['adjustment_description']) && !empty($payment_request['adjustment_description']) ? ' (' . $payment_request['adjustment_description'] . ')' : ''),
                'count' => '',
                'revenue' => '',
                'total' => $adjustment_amount
            ];
        }

        return $response;
    }

    private function buildTimestamp($timeframe)
    {
        $time = explode(' - ', $timeframe);
        $this->start = strtotime($time[0] . " 00:00:00");
        $this->end = strtotime($time[1] . " 23:59:59");
        return [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end'   => new \MongoDB\BSON\UTCDateTime($this->end * 1000)
        ];
    }

    private function _get_feed_billing_payment_request($timeframe)
    {
        // -- query
        $time_range = $time = $this->buildTimestamp($timeframe);
        $query = $this->billing_payment_requests_buildParameterArray();

        $conditions = [
            'brokerId' => [$this->brokerId],
            'match_with_broker' => 1,
            'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $time = [
            'where' => [
                '$or' => [
                    ['Timestamp' => array('$gte' => $time_range['start'], '$lte' => $time_range['end'])],
                    ['depositTimestamp' => array('$gte' => $time_range['start'], '$lte' => $time_range['end'])]
                ]
            ]
        ];

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachFormula($query_data, $query['formula']);

        return $data;
    }

    private function billing_payment_requests_buildParameterArray()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
            'country',
            // 'isCPL',
            'broker_cpl',
            'broker_crg_deal',
            'broker_crg_percentage',
            'broker_crg_percentage_id',
            'broker_crg_payout',
            'broker_crg_revenue',
            'broker_changed_crg_percentage_id',
            'broker_changed_crg_percentage',
            'broker_changed_crg_payout',
        ];

        $metrics = [
            'leads',
            'revenue',
            'cr',
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

            if ($metrics == 'leads') {
                $array['Leads'] = array('Leads' => [
                    'type' => 'count',
                    'formula' => '
                         if ( __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                             return true;
                         }
                         return false;
                     ',
                    'formula_return' => false
                ]);
            }

            if ($metrics == 'cr') {
                if (!isset($array['Leads'])) {
                    $array['Leads'] = array('Leads' => [
                        'type' => 'count',
                        'formula' => '
                             if ( __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                 return true;
                             }
                             return false;
                         ',
                        'formula_return' => false
                    ]);
                }

                if (!isset($array['Depositors'])) {
                    $array['Depositors'] = array('depositor' => [
                        'type' => 'count',
                        'formula' => '
                             if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                                if ( __(bool)broker_crg_deal__ == TRUE && __(bool)broker_crg_already_paid__ == TRUE) {
									return false;
								}else{
									return true;
								}
                             }
                             return false;
                         ',
                        'formula_return' => false
                    ]);
                }
                $formula['cr'] = 'round(__leads__ > 0 ? ((__deposits__/__leads__) * 100) : 0,0)';
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    private function _get_feed_group_by_billing_payment_request($data)
    {

        $except = [];
        $DataSchema = QueryHelper::DataSchema($data[0] ?? [], $except);

        $mongo = new MongoDBObjects('partner', []);
        $find = $mongo->findMany();
        $brokers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $brokers[$id['oid']] = $supply['token'] ?? null; //TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        }

        $groupBy = [];
        foreach ($data as $ndata => $datas) {

            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('brokerId' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $brokers)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            // if (!$datas['crg_deal'] && !$datas['depositor'] && !$datas['isCPL']) {
            //     continue;
            // }

            $name = 'country[' . $datas['country'] . '] | broker_crg_deal[' . ($datas['broker_crg_deal'] ? '1' : '0') . '] | isCPL[' . (($datas['broker_cpl'] ?? false) ? '1' : '0') . ']';
            // $name = 'broker_crg_deal[' . ($datas['broker_crg_deal'] ? '1' : '0') . '] | isCPL[' . (($datas['broker_cpl'] ?? false) ? '1' : '0') . ']';
            // if (($datas['broker_crg_deal'] ?? false) == false) {
            //     $name = 'country[' . $datas['country'] . '] | ' . $name;
            // }

            if (!isset($groupBy[$name])) {
                $groupBy[$name] = [];
            }

            $groupBy[$name][] = $datas;
        }

        $subGroupBy = [];

        foreach ($groupBy as $groupKey => $values) {
            foreach ($values as $datas) {

                // echo $groupKey;
                // print_r($datas);
                // CRG
                if (strpos($groupKey, 'broker_crg_deal[1]') !== false) {
                    //$name = $groupKey . '///broker_crg_percentage_id[' . $datas['broker_crg_percentage_id'] . '] | broker_crg_percentage[' . $datas['broker_crg_percentage'] . '] | broker_crg_payout[' . $datas['broker_crg_payout'] . '] | deposit_revenue[' . $datas['deposit_revenue'] . ']'; //| cr[' . $datas['cr'] . ']
                    /* $name = $groupKey . '///' .
                        'broker_crg_percentage_id[' . ((!empty($datas['broker_changed_crg_percentage_id'] ?? '') ? $datas['broker_changed_crg_percentage_id'] : $datas['broker_crg_percentage_id'])) . '] | ' .
                        'broker_crg_percentage[' . $datas['broker_crg_percentage'] . '] | ' .
                        'broker_crg_payout[' . $datas['broker_crg_payout'] . '] | ' .
                        'deposit_revenue[' . $datas['deposit_revenue'] . ']'; */
                    // $broker_crg_percentage_id = $datas['broker_crg_percentage_id'];

                    $broker_crg_percentage_id = (!empty($datas['broker_changed_crg_percentage_id'] ?? '') ? $datas['broker_changed_crg_percentage_id'] : $datas['broker_crg_percentage_id']);

                    $broker_crg_percentage = (!empty($datas['broker_changed_crg_percentage'] ?? '') ? $datas['broker_changed_crg_percentage'] : $datas['broker_crg_percentage']);

                    $broker_crg_payout = (!empty($datas['broker_changed_crg_payout'] ?? '') ? $datas['broker_changed_crg_payout'] : $datas['broker_crg_payout']);

                    $name = $groupKey . '///broker_crg_percentage_id[' . $broker_crg_percentage_id . '] | broker_crg_percentage[' . $broker_crg_percentage . '] | broker_crg_payout[' . $broker_crg_payout . '] | deposit_revenue[' . $datas['deposit_revenue'] . ']';

                    if (!isset($subGroupBy[$name])) {
                        //$datas['items_by_row'] = 1;
                        $datas['total'] = $datas['deposit_revenue'];
                        $datas['Leads'] = 1;
                        $datas['Depositors'] = $datas['Depositors'] ?? 0;
                        // $datas['GLeads'][$broker_crg_percentage_id] = 1;
                        $subGroupBy[$name] = $datas;
                    } else {
                        //$subGroupBy[$name]['items_by_row'] += 1;
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['Depositors'] += $datas['Depositors'] ?? 0;
                        // $subGroupBy[$name]['GLeads'][$broker_crg_percentage_id] += 1;
                        $subGroupBy[$name]['broker_crg_leads'] = ($subGroupBy[$name]['broker_crg_leads'] ?? 0) + ($datas['broker_crg_leads'] ?? 0);
                        $subGroupBy[$name]['total'] = ($subGroupBy[$name]['total'] ?? 0) + ($datas['deposit_revenue'] ?? 0);
                    }

                    //    $subGroupBy[$name]['countries'] ??= [];
                    //   $subGroupBy[$name]['countries'][] = $datas['country'];

                    // echo 'leadID: ' . $datas['_id'] . ' crg_leads: ' . $subGroupBy[$name]['crg_leads'];

                    // if ($id == '614892199e6bdd0f2e016b71') {
                    //     echo '12345';
                    // }
                } else
                    // CPL
                    if (strpos($groupKey, 'isCPL[1]') !== false) {
                        $name = $groupKey . '///deposit_revenue[' . $datas['deposit_revenue'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['deposit_revenue'];
                            $datas['Leads'] = 1;
                            $datas['Depositors'] = $datas['Depositors'] ?? 0;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['Depositors'] += $datas['Depositors'] ?? 0;
                            $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['deposit_revenue'];
                        }
                    }
                    // CPA
                    else {
                        $name = $groupKey . '///deposit_revenue[' . $datas['deposit_revenue'] . ']';
                        if (!isset($subGroupBy[$name])) {
                            $datas['total'] = $datas['deposit_revenue'];
                            $datas['Leads'] = 1;
                            $datas['Depositors'] = $datas['Depositors'] ?? 0;
                            $subGroupBy[$name] = $datas;
                        } else {
                            $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                            $subGroupBy[$name]['Depositors'] += $datas['Depositors'] ?? 0;
                            $subGroupBy[$name]['total'] = ($subGroupBy[$name]['total'] ?? 0) + ($datas['deposit_revenue'] ?? 0);
                            $subGroupBy[$name]['broker_crg_revenue'] = (float)($subGroupBy[$name]['broker_crg_revenue'] ?? 0) + (float)($datas['broker_crg_revenue'] ?? 0);
                        }
                    }
            }
        }

        // recalc deposit when deposit_revenue is zero
        foreach ($subGroupBy as $key0 => $data0) {
            if (($data0['deposit_revenue'] ?? 0) != 0) continue;

            $key0 = preg_replace('#\Wdeposit_revenue\[\d+\]#', '', $key0);

            // becouse of deposit_revenue zero put deposit to another row
            foreach ($subGroupBy as $key1 => &$data1) {
                if (($data1['deposit_revenue'] ?? 0) == 0) continue;

                $key1 = preg_replace('#\Wdeposit_revenue\[\d+\]#', '', $key1);

                if ($key0 == $key1) {
                    $data1['Leads'] += $data0['Leads'] ?? 0;
                    $data1['Depositors'] += $data0['Depositors'] ?? 0;
                }
            }
        }

        return $subGroupBy;
    }

    private function is_billing_payment_requests_leads_changed($id)
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $payment_request = $mongo->find();

        $ts = (array)$payment_request['from'];
        $mil = $ts['milliseconds'] ?? 0;
        $seconds = $mil / 1000;
        $from = date("Y-m-d", $seconds);

        $ts = (array)$payment_request['to'];
        $mil = $ts['milliseconds'] ?? 0;
        $seconds = $mil / 1000;
        $to = date("Y-m-d", $seconds);

        $leads = json_decode($payment_request['json_leads'], true);

        $timeframe = $from . ' - ' . $to;
        $new_leads = $this->_get_feed_billing_payment_request($timeframe);

        // diff old and new leads
        for ($i = 0; $i < count($leads); $i++) {
            $leads[$i]['__e'] = false;
            $leads[$i]['__f'] = false;
            for ($j = 0; $j < count($new_leads); $j++) {
                if ($leads[$i]['_id'] == $new_leads[$j]['_id']) {
                    $leads[$i]['__e'] = true;
                    $new_leads[$j]['__e'] = true;

                    $leads[$i]['__f'] = true;
                    foreach ($leads[$i] as $field_key => $field_value) {
                        if ($field_key == '__e' || $field_key == '__f') {
                            continue;
                        }
                        $b = false;
                        foreach ($new_leads[$j] as $new_field_key => $new_field_value) {
                            if ($field_key == $new_field_key && $field_value == $new_field_value) {
                                $b = true;
                                break;
                            }
                        }
                        if (!$b) {
                            $leads[$i]['__f'] = false;
                            break;
                        }
                    }
                }
            }
        }

        $b = true;
        foreach ($leads as $lead) {
            if ($lead['__e'] == false || $lead['__f'] == false) {
                $b = false;
                break;
            }
        }
        foreach ($new_leads as $lead) {
            if (!isset($lead['__e']) || (isset($lead['__e']) && $lead['__e'] == false)) {
                $b = false;
                break;
            }
        }

        return !$b;
    }

    private function _get_billing_entity($id)
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_entities'], $where);
        $data = $mongo->find();
        $data['title'] = $data['company_legal_name'];
        return $data;
    }

    private function _get_billing_payment_methods()
    {
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods_general'], []);
        $array = $mongo->findMany();
        $payment_methods = [];
        $payment_method = [
            'wire' => 'WIRE',
            'crypto' => 'CRYPTO'
        ];
        foreach ($array as $ar) {
            $id = (array)$ar['_id'];
            $id = $id['oid'];
            $ar['title'] = $payment_method[$ar['payment_method']];
            if (isset($ar['bank_name']) && !empty($ar['bank_name'])) {
                $ar['title'] .= ' - ' . $ar['bank_name'];
            }
            if (isset($ar['account_name']) && !empty($ar['account_name'])) {
                $ar['title'] .= ' - ' . $ar['account_name'];
            }
            if (isset($ar['swift']) && !empty($ar['swift'])) {
                $ar['title'] .= ' - ' . $ar['swift'];
            }
            if (isset($ar['wallet']) && !empty($ar['wallet'])) {
                $ar['title'] .= ' - ' . $ar['wallet'];
            }
            if (isset($ar['wallet2']) && !empty($ar['wallet2'])) {
                $ar['title'] .= ' - ' . $ar['wallet2'];
            }
            $payment_methods[$id] = $ar;
        }
        return $payment_methods;
    }
}
