<?php

namespace App\Classes\Masters;

use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Mongo\MongoQuery;
use App\Helpers\GeneralHelper;
use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Models\Masters\MasterBillingPaymentMethod;
use App\Models\User;
use MongoDB\BSON\ObjectId;

class MasterChangeLogs
{
    private string $masterId;

    public function __construct(string $masterId)
    {
        $this->masterId = $masterId;
    }

    private function _get_history_billing_general_balances($collection = '', $limit = 20)
    {
        $collections = [
            'partner',
            'masters_billing_entities',
            'masters_billing_chargebacks',
            'masters_billing_adjustments',
            'masters_billing_payment_methods',
            'masters_billing_payment_requests'
        ];

        $where = [
            '$or' => [
                ['main_foreign_key' => new ObjectId($this->masterId)],
                ['primary_key' => new ObjectId($this->masterId)],
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

            // $where['$and'] = array_merge($where['$and'], [
            //     ['collection' => $collection],
            //     // ['diff.payment_method' => ['$exists' => true]],
            //     // ['diff.status' => ['$exists' => true]]
            // ]);

            $where['$and'][] = [
                '$or' => [
                    ['collection' => ['$in' => $collections]],
                    [
                        '$and' => [
                            ['collection' => 'masters_billing_payment_methods'],
                            // ['diff.payment_method' => ['$exists' => true]],
                            ['diff.status' => ['$exists' => true]]
                        ]
                    ]
                ]
            ];
        } elseif ($collection == 'masters_billing_payment_methods') {
            $where['$and'] = array_merge($where['$and'], [
                ['collection' => $collection],
                // ['diff.payment_method' => ['$exists' => true]],
                ['diff.status' => ['$exists' => true]]
            ]);
        } elseif (in_array($collection, $collections)) {
            $where['$and'][] = ['collection' => $collection];
        } else {
            $where['$and'][] = ['collection' => ['$in' => $collections]];
        }
        // echo json_encode($where);
        $mongo = new MongoDBObjects('history', $where);
        $items = $mongo->findMany([
            'sort' => ['timestamp' => -1],
            'limit' => $limit
        ]);
        return $items;
    }

    private function get_payment_methods()
    {
        $mongo = new MongoDBObjects('masters_billing_payment_methods', []);
        $array = $mongo->findMany();
        $payment_methods = [];
        foreach ($array as $payment_method) {
            $payment_method_id = (array)$payment_method['_id'];
            $payment_method_id = $payment_method_id['oid'];
            $payment_methods[$payment_method_id] = $payment_method;
        }
        return $payment_methods;
    }

    public function get_change_logs(bool $extended, string $collection, int $limit): array
    {
        $limit = min((int)$limit, 1000);
        $history_logs = $this->_get_history_billing_general_balances($collection, $limit);

        $collections = [
            'partner' => 'General',
            'masters_billing_entities' => 'Billing Entities',
            'masters_billing_payment_methods' => 'Payment Methods',
            'masters_billing_payment_requests' => 'Payment Requests',
            'masters_billing_adjustments' => 'Adjustments',
            'masters_billing_chargebacks' => 'Chargebacks'
        ];

        $payment_methods = $this->get_payment_methods();

        foreach ($payment_methods as &$pm) {
            if (($pm['payment_method'] ?? '') == 'wire') {
                $pm['title'] = strtoupper(($pm['payment_method'] ?? '') . ' - ' . ($pm['currency_code'] ?? '')) . ' - ' . ($pm['bank_name'] ?? '');
            } else {
                $pm['title'] = strtoupper(($pm['payment_method'] ?? '') . ' - ' . ($pm['currency_crypto_code'] ?? ''));
            }
        }

        $items = [];

        foreach ($history_logs as $history) {

            $data = '';
            $diff = (array)($history['action'] == 'INSERT' || $history['action'] == 'DELETE' ? $history['data'] : $history['diff']);

            switch ($history['collection']) {
                case 'partner': {
                        if (isset($diff['action_on_negative_balance'])) {
                            $actions = ['leave_running' => 'Leave Running', 'stop' => 'Stop if negative'];
                            $data .= 'Action on negative balance: ' . $actions[$diff['action_on_negative_balance']];
                        }
                        break;
                    }
                case 'masters_billing_entities': {
                        if (isset($diff['company_legal_name'])) {
                            $data .= 'Company Legal Name: ' . $diff['company_legal_name'];
                        }
                        if (isset($diff['country_code'])) {
                            $countries = GeneralHelper::countries();
                            $data .= (empty($data) ? '' : ', ') . 'Company Country: ' . $countries[$diff['country_code']];
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
                case 'masters_billing_chargebacks': {
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
                case 'masters_billing_adjustments': {
                        if (isset($diff['amount'])) {
                            $data .= 'Amount: ' . RenderHelper::format_money($diff['amount']);
                        }
                        if (isset($diff['description'])) {
                            $data .= (empty($data) ? '' : ', ') . 'Description: ' . $diff['description'];
                        }
                        break;
                    }
                case 'masters_billing_payment_methods': {
                        if (($diff['status'] ?? 0) == 1) {
                            $payment_method = $diff['payment_method'] ?? $history['data']['payment_method'] ?? '';
                            if (empty($payment_method)) {
                                $m = MasterBillingPaymentMethod::findOrFail((string)$history['primary_key']);
                                $payment_method = $m->_id;
                            }
                            $pm = $payment_methods[$payment_method] ?? [];

                            $data .= 'Changed Payment Method on "' . $pm['title'] . '"'; //  . $diff['status'];
                        } else if ($history['action'] == 'INSERT' || ($diff['status'] ?? 0) != 0) {
                            foreach ($diff as $d => $v) {
                                if ((is_string($v) || is_int($v) || is_bool($v)) && !empty($v) && !in_array($d, [$history['main_foreign_field'] ?? '', 'clientId', 'updated_at', 'created_at'])) {
                                    $data .= (!empty($data) ? ', ' : '') . $d . ' = ' . $v;
                                    // $data .= json_encode($diff);
                                }
                            }
                        }
                        break;
                    }
                case 'masters_billing_payment_requests': {
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
