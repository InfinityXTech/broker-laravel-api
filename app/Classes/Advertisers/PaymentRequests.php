<?php

namespace App\Classes\Advertisers;

use App\Models\User;
use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Auth;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\BillingPaymentMethods;
use App\Notifications\SlackNotification;
use App\Classes\Advertisers\InvoiceDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Models\Advertisers\MarketingAdvertiserBillingPaymentRequest;
use Exception;

class PaymentRequests
{
    private string $advertiserId;

    private $start;
    private $end;

    private $collections = [
        'billing_entities' => 'marketing_advertiser_billing_entities',
        'billing_payment_companies_general' => 'billing_payment_companies', //'marketing_advertiser_payment_companies',
        'billing_payment_methods' => 'marketing_advertiser_billing_payment_methods',
        'billing_payment_methods_general' => 'billing_payment_methods',
        'billing_chargebacks' => 'marketing_advertiser_billing_chargebacks',
        'billing_adjustments' => 'marketing_advertiser_billing_adjustments',
        'billing_payment_requests' => 'marketing_advertiser_billing_payment_requests',
        'marketing_advertisers' => 'marketing_advertisers',
    ];

    public function __construct(string $advertiserId)
    {
        $this->advertiserId = $advertiserId;
    }

    public function feed(bool $only_completed): Collection
    {
        $where = [
            'advertiser' => $this->advertiserId,
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

        $where = ['advertiser' => $this->advertiserId];
        if ($only_completed) {
            $where['final_status'] = 1;
        }
        $collection = MarketingAdvertiserBillingPaymentRequest::where($where)->get();
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

    private function get_advertiser()
    {
        $token = $this->advertiserId;
        $where = ['_id' => new \MongoDB\BSON\ObjectId($token)];
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        return $mongo->find();
    }

    public function fin_approve(string $id, array $payload): bool
    {
        $date_pay = $payload['date_pay'];

        $model = MarketingAdvertiserBillingPaymentRequest::findOrFail($id);

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
        $advertiser = $this->get_advertiser();
        $subject = 'New Payment Request Financial Approv';
        $message = $subject . ' :';
        $message .= ' Advertiser ' . $advertiser['name'];
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
            $insert['advertiser'] = $this->advertiserId;
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
            if (!isset($billing_from) || isset($billing_from) && empty($billing_from['_id'])) {
                throw new Exception("General Payment is empty");
            }
            $billing_from['organization_address'] = array_filter(array_map('trim', explode("\n", $billing_from['organization_address'])));
        }
        // if (empty($billing_from)) {
        //     $billing_from = [
        //         'organization_name' => "Stolos Group Limited",
        //         'organization_address' => [
        //             "9/F Amtel Bldg.",
        //             "148 Des Vpeux Rd,",
        //             "Central Hong Kong."
        //         ]
        //     ];
        // }

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
                'advertiser' => $this->advertiserId,
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
                'advertiser' => $this->advertiserId,
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
                'advertiser' => $this->advertiserId,
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

            // if ($datas['revenue'] == 0 && strpos($groupKey, 'crg_deal[1]') === false) {
            //     continue;
            // }
            if ($datas['revenue'] == 0) {
                continue;
            }

            $response_row = [];

            $datas['cr'] = round($datas['Leads'] > 0 ? (($datas['Conversions'] / $datas['Leads']) * 100) : 0, 2);
            if (strpos($groupKey, 'EventSchema[cpl]') !== false) {
                //120 Leads Germany CPL 25 = $3000
                $response_row = [
                    'nomination' => '<strong>CPL. </strong>' . $countries[strtolower($datas['GeoCountryName'])],
                    'count' => number_format($datas['Leads'], 0, '.', ' ') . ' Leads',
                    'revenue' => $datas['revenue'],
                    'total' => $datas['total'],
                ];
            } else if (strpos($groupKey, 'EventSchema[cpa]') !== false) {
                //3 FTD IT CPA 985 = $2955
                $response_row = [
                    'nomination' =>
                    '<strong>CPA.</strong> ' . $countries[strtolower($datas['GeoCountryName'])] . ' cr ' . $datas['cr'] . '%' .
                        '<div style="color:gray;display:none">' . print_r($datas, true) . '</div>',

                    'count' => number_format($datas['Conversions'], 0, '.', ' ') .  ' FTD\'s',
                    'revenue' => $datas['revenue'],
                    'total' => $datas['total'],
                ];
            } else {
                $response_row = [
                    'nomination' => '<strong>Unrecognized Schema</div>',
                    'count' => number_format($datas['Conversions'], 0, '.', ' ') .  ' FTD\'s',
                    'revenue' => $datas['revenue'],
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

    private function _get_feed_billing_payment_request($timeframe)
    {
        // -- query
        $time_range = $this->buildTimestamp($timeframe);
        $query = $this->billing_payment_requests_buildParameterArray();

        $conditions = [
            'AdvertiserId' => [$this->advertiserId],
            // 'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time_range, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachMarketingFormula($query_data, $query['formula']);

        return $data;
    }

    private function billing_payment_requests_buildParameterArray()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
            'GeoCountryName',
            'Approved',
            'EventTypeSchema',
        ];

        $metrics = [
            'leads',
            'revenue',
            'cr',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_revenue = array('revenue' =>  [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                $advertiserPayout = 0.0;
                if ( __(bool)Approved__ == TRUE or  __(bool)Rejected__ == TRUE) {
                    $advertiserPayout = __AdvertiserPayout__;
				}
                return (float)$advertiserPayout;
            ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {

            if ($metrics == 'revenue') {
                $array['revenue'] = $_revenue;
            }

            if ($metrics == 'leads') {
                $array['Leads'] = array('Leads' => [
                    'type' => 'count',
                    'formula' => '
                        // if ( __EventTimeStamp__ >= ' . $this->start . ' && __EventTimeStamp__ <= ' . $this->end . ' ) {
						if ( strtoupper(__(string)EventType__) == "LEAD" || strtoupper(__(string)EventType__) == "POSTBACK" ) {
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
                            // if ( __EventTimeStamp__ >= ' . $this->start . ' && __EventTimeStamp__ <= ' . $this->end . ' ) {
							if ( strtoupper(__(string)EventType__) == "LEAD" || strtoupper(__(string)EventType__) == "POSTBACK" ) {
                                 return true;
                             }
                             return false;
                         ',
                        'formula_return' => false
                    ]);
                }

                if (!isset($array['Conversions'])) {
                    $array['Conversions'] = array('conversion' => [
                        'type' => 'count',
                        'formula' => '
                            if ( __(bool)Approved__ == TRUE || __(bool)Rejected__ == TRUE) {
								return true;
                            }
                            return false;
                         ',
                        'formula_return' => false
                    ]);
                }
                $formula['cr'] = 'round(__leads__ > 0 ? ((__conversions__/__leads__) * 100) : 0,0)';
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

        $mongo = new MongoDBObjects('marketing_advertisers', []);
        $find = $mongo->findMany();
        $advertisers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $advertisers[$id['oid']] = $supply['token'] ?? null; //TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        }

        $groupBy = [];
        foreach ($data as $ndata => $datas) {

            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('advertiserId' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $advertisers)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            $name = 'country[' . $datas['GeoCountryName'] . '] | EventSchema[' . strtolower($datas['EventTypeSchema'] ?? '') . ']';
            if (!isset($groupBy[$name])) {
                $groupBy[$name] = [];
            }

            $groupBy[$name][] = $datas;
        }

        $subGroupBy = [];

        foreach ($groupBy as $groupKey => $values) {
            foreach ($values as $datas) {
                // CPL
                if (strpos($groupKey, 'EventSchema[cpl]') !== false) {
                    $name = $groupKey . '///revenue[' . $datas['revenue'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['revenue'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['revenue'];
                    }
                }
                // CPA
                else if (strpos($groupKey, 'EventSchema[cpa]') !== false) {
                    $name = $groupKey . '///revenue[' . $datas['revenue'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['revenue'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = ($subGroupBy[$name]['total'] ?? 0) + ($datas['revenue'] ?? 0);
                    }
                } else {
                    $name = $groupKey . '///revenue[' . $datas['revenue'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['revenue'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = ($subGroupBy[$name]['total'] ?? 0) + ($datas['revenue'] ?? 0);
                    }
                }
            }
        }

        // recalc Conversion when revenue is zero
        foreach ($subGroupBy as $key0 => $data0) {
            if (($data0['revenue'] ?? 0) != 0) continue;

            $key0 = preg_replace('#\Wrevenue\[\d+\]#', '', $key0);

            // becouse of revenue zero put Conversion to another row
            foreach ($subGroupBy as $key1 => &$data1) {
                if (($data1['revenue'] ?? 0) == 0) continue;

                $key1 = preg_replace('#\Wrevenue\[\d+\]#', '', $key1);

                if ($key0 == $key1) {
                    $data1['Leads'] += $data0['Leads'] ?? 0;
                    $data1['Conversions'] += $data0['Conversions'] ?? 0;
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
