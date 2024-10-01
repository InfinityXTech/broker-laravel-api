<?php

namespace App\Classes\Brokers;

use App\Models\User;
use MongoDB\BSON\ObjectId;
use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\BillingPaymentMethods;
use App\Models\Brokers\BrokerBillingPaymentMethod;

class BrokerChangeLogs
{
    private string $brokerId;
    private array $payload;

    public function __construct(string $brokerId, array $payload = [])
    {
        $this->brokerId = $brokerId;
        $this->payload = $payload;
    }

    private function _get_history_billing_general_balances($collection = '', $limit = 20)
    {
        $collections = [
            'partner',
            'broker_billing_entities',
            'broker_billing_chargebacks',
            'broker_billing_adjustments',
            'broker_billing_payment_methods',
            'broker_billing_payment_requests',
            'billings_log'
        ];

        $where = [
            '$or' => [
                ['main_foreign_key' => new ObjectId($this->brokerId)],
                ['primary_key' => new ObjectId($this->brokerId)],
            ],
            '$and' => [
                [
                    '$or' => [
                        ['action' => 'UPDATE', 'diff' => ['$exists' => true, '$not' => ['$size' => 0], '$ne' => null]], //'$type' => 'array', '$ne' => []
                        ['action' => ['$in' => ['INSERT', 'DELETE']]]
                    ]
                ]
            ]
        ];

        if (empty($collection)) {
            $where['$and'][] = [
                '$or' => [
                    ['collection' => ['$in' => $collections]],
                    [
                        '$and' => [
                            ['collection' => 'broker_billing_payment_methods'],
                            ['diff.payment_method' => ['$exists' => true]],
                            ['diff.status' => ['$exists' => true]]
                        ]
                    ]
                ]
            ];
        } elseif ($collection == 'broker_billing_payment_methods') {
            $where['$and'] = array_merge($where['$and'], [
                ['collection' => $collection],
                ['diff.payment_method' => ['$exists' => true]],
                ['diff.status' => ['$exists' => true]]
            ]);
        } elseif (in_array($collection, $collections)) {
            $where['$and'][] = ['collection' => $collection];
        } else {
            $where['$and'][] = ['collection' => ['$in' => $collections]];
        }

        // GeneralHelper::PrintR($where);die();
        $mongo = new MongoDBObjects('history', $where);
        return $mongo->findMany([
            'sort' => ['timestamp' => -1],
            'limit' => $limit
        ]);
    }

    private function get_payment_methods()
    {
        $array = BrokerBillingPaymentMethod::query()->where('type', '=', 'broker')->get()->toArray();
        $payment_methods = [];
        foreach ($array as $payment_method) {
            $payment_method_id = (string)$payment_method['_id'];
            $payment_methods[$payment_method_id] = $payment_method;
        }

        $array = BillingPaymentMethods::all()->toArray();
        foreach ($array as $payment_method) {
            $payment_method_id = (string)$payment_method['_id'];
            $payment_methods[$payment_method_id] = $payment_method;
        }

        // $mongo = new MongoDBObjects('billing_payment_methods', []);
        // $array = $mongo->findMany();
        // $payment_methods = [];
        // foreach ($array as $payment_method) {
        //     $payment_method_id = (array)$payment_method['_id'];
        //     $payment_method_id = $payment_method_id['oid'];
        //     $payment_methods[$payment_method_id] = $payment_method;
        // }
        return $payment_methods;
    }

    public function get_change_logs(bool $extended, string $collection, int $limit): array
    {
        $limit = min((int)$limit, 1000);
        $history_logs = $this->_get_history_billing_general_balances($collection, $limit);

        $collections = [
            'partner' => 'General',
            'broker_billing_entities' => 'Billing Entities',
            'broker_billing_payment_methods' => 'Payment Methods',
            'broker_billing_payment_requests' => 'Payment Requests',
            'broker_billing_adjustments' => 'Adjustments',
            'broker_billing_chargebacks' => 'Chargebacks',
            'billings_log' => 'Billings Log'
        ];

        $payment_methods = $this->get_payment_methods();

        foreach ($payment_methods as &$pm) {
            if ($pm['payment_method'] == 'wire') {
                $pm['title'] = strtoupper($pm['payment_method'] . (!empty($pm['currency_code']) ?  ' - ' . $pm['currency_code'] : '')) . (!empty($pm['bank_name']) ? ' - ' . $pm['bank_name'] : '');
            } else {
                $pm['title'] = strtoupper($pm['payment_method'] . (!empty($pm['currency_crypto_code']) ? ' - ' . $pm['currency_crypto_code'] : ''));
            }
        }

        $items = [];

        foreach ($history_logs as $history) {

            $data = '';
            $diff = $history['action'] == 'INSERT' || $history['action'] == 'DELETE' ? $history['data'] : $history['diff'];
            switch ($history['collection']) {
                case 'billings_log': {
                        if (isset($diff['real_balance'])) {
                            $data .= $history['description'] ?? 'Changed Real Balance: $' . $diff['real_balance'];
                        }
                        break;
                    }
                case 'partner': {
                        if (isset($diff['action_on_negative_balance'])) {
                            $actions = ['leave_running' => 'Leave Running', 'stop' => 'Stop if negative'];
                            $data .= 'Action on negative balance: ' . $actions[$diff['action_on_negative_balance']];
                        }
                        break;
                    }
                case 'broker_billing_entities': {
                        if (isset($diff['company_legal_name'])) {
                            $data .= 'Company Legal Name: ' . $diff['company_legal_name'];
                        }
                        if (isset($diff['country_code'])) {
                            $countries = GeneralHelper::countries();
                            $data .= (empty($data) ? '' : ', ') . 'Company Country: ' . $countries[strtolower($diff['country_code'])];
                        }
                        if (isset($diff['region'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Address: ' . $diff['region'];
                        }
                        if (isset($diff['city'])) {
                            $data .= (empty($data) ? '' : ', ') . 'City: ' . $diff['city'];
                        }
                        if (isset($diff['zip_code'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Zip code: ' . $diff['zip_code'];
                        }
                        if (isset($diff['currency_code'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Currency: ' . strtoupper($diff['currency_code']);
                        }
                        // $data = json_encode($diff);
                        break;
                    }
                case 'broker_billing_chargebacks': {
                        // if (isset($diff['type'])) {
                        //     $data .= 'Type: ' . $diff['type'];
                        // }
                        if (isset($diff['payment_method'])) {
                            $pm = $payment_methods[$diff['payment_method']] ?? [];
                            $data .= (empty($data) ? '' : ', ') . 'Payment Method: ' . ($pm['title'] ?? '');
                        }
                        if (isset($diff['amount'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Sum: ' . RenderHelper::format_money($diff['amount']);
                        }
                        if (isset($diff['screenshots'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Screenshots';
                        }
                        break;
                    }
                case 'broker_billing_adjustments': {
                        if (isset($diff['amount'])) {
                            $data .= 'Amount: ' . RenderHelper::format_money($diff['amount']);
                        }
                        if (isset($diff['description'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Description: ' . $diff['description'];
                        }
                        break;
                    }
                case 'broker_billing_payment_methods': {

                        $payment_method = $diff['payment_method'] ?? $history['data']['payment_method'] ?? '';
                        if (empty($payment_method) && empty($payment_methods[$payment_method ?? ''])) {
                            $primary_key = (string)$history['primary_key'];
                            if (!empty($primary_key)) {
                                $m = BrokerBillingPaymentMethod::query()->where('_id', '=', $primary_key)->first();
                                if ($m != null) {
                                    $payment_method = $m->_id;
                                }
                            }
                        }

                        $pm = $payment_methods[$payment_method ?? ''] ?? [];
                        if (($diff['status'] ?? 0) == 1) {
                            $data .= 'Changed Payment Method on "' . ($pm['title'] ?? '') . '"'; //  . $diff['status'];
                        } else if ($history['action'] == 'INSERT' || ($diff['status'] ?? 0) != 0) {
                            $s = '';
                            foreach ($diff as $d => $v) {
                                if ((is_string($v) || is_int($v) || is_bool($v)) && !empty($v) && !in_array($d, [$history['main_foreign_field'] ?? '', 'clientId', 'updated_at', 'created_at'])) {
                                    $s .= (!empty($s) ? ', ' : '') . ucfirst(str_replace('_', ' ', $d)) . ' = ' . $v;
                                    // $data .= json_encode($diff);
                                }
                            }
                            if (!empty($s)) {
                                $data .= 'Changed Broker Payment Method: ' . $s;
                            }
                        }
                        break;
                    }
                case 'broker_billing_payment_requests': {
                        $status = [0 => 'Approval processing', 1 => 'Approved', 2 => 'Rejected'];
                        if (isset($diff['from'])) {
                            $_dt = date('Y-m-d', ((array)$diff['from'])['milliseconds'] / 1000);
                            $data .= (empty($data) ? '' : ', ') . 'From: ' . $_dt;
                        }
                        if (isset($diff['to'])) {
                            $_dt = date('Y-m-d', ((array)$diff['to'])['milliseconds'] / 1000);
                            $data .= (empty($data) ? '' : ', ') . 'To: ' . $_dt;
                        }
                        if (isset($diff['status'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Status: ' . $status[$diff['status']];
                        }
                        if (isset($diff['final_status'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Finance status: ' . $status[$diff['final_status']];
                        }
                        // $data = json_encode($diff);
                        break;
                    }
            }

            if (!empty($data)) {
                $items[] = [
                    'timestamp' => $history['timestamp'],
                    'collection' => $collections[$history['collection']],
                    'action' => $history['action'],
                    'changed_by' => User::query()->find((string)$history['action_by'])->name,
                    'data' => $data,
                ];
            }
        }

        if ($extended) {
            $options = [];
            foreach ($collections as $key => $value) {
                $options[] = ['value' => $key, 'label' => $value];
            }
            return [
                'collections' => $options,
                'items' => $items,
            ];
        }
        return $items;
    }
}
