<?php

namespace App\Repository\TrafficEndpoints;

use Exception;
use App\Models\User;
use App\Helpers\StorageHelper;
use App\Models\TrafficEndpoint;

use App\Repository\BaseRepository;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Classes\TrafficEndpoints\ManageBilling;
use App\Models\TrafficEndpoints\TrafficEndpointPayout;
use App\Models\TrafficEndpoints\TrafficEndpointBillingEntities;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentMethods;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentRequests;
use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class TrafficEndpointBillingRepository extends BaseRepository implements ITrafficEndpointBillingRepository
{
    public function __construct()
    {
    }

    public function get_payment_methods(string $trafficEndpointId): array
    {
        $payment_methods = [
            'wire' => 'WIRE',
            'crypto' => 'CRYPTO'
        ];

        $_items = TrafficEndpointBillingPaymentMethods::all()
            ->where('endpoint', '=', $trafficEndpointId)
            // ->whereIn('status', ['1',1])
            ->map(function ($item) use ($payment_methods) {

                $title = $payment_methods[$item->payment_method] ?? '';
                if (isset($item->bank_name) && !empty($item->bank_name)) {
                    $title .= ' - ' . $item->bank_name;
                }
                if (isset($item->account_name) && !empty($item->account_name)) {
                    $title .= ' - ' . $item->account_name;
                }
                if (isset($item->swift) && !empty($item->swift)) {
                    $title .= ' - ' . $item->swift;
                }
                if (isset($wallet) && !empty($item->wallet)) {
                    $title .= ' - ' . $item->wallet;
                }
                if (isset($item->wallet2) && !empty($item->wallet2)) {
                    $title .= ' - ' . $item->wallet2;
                }

                return ['key' => $item->_id, 'value' => $title];
            })->toArray();

        $items = [];
        foreach ($_items as $item) {
            if (!empty($item['value'])) {
                $items[$item['key']] = $item['value'];
            }
        }

        return $items;
    }

    public function get_payment_requests_for_chargeback(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $where = [
            'endpoint' => $trafficEndpointId,
            'final_status' => 1,
            '$or' => [
                ['chargeback' => ['$exists' => false]],
                ['chargeback' => false]
            ]
        ];
        $payment_requests = $billing->get_billing_payment_requests($where);
        $_items = array_map(function ($item) {
            return ['key' => $item['id'], 'value' => $item['period']];
        }, $payment_requests);
        $items = [];
        foreach ($_items as $item) {
            $items[$item['key']] = $item['value'];
        }
        return $items;
    }

    public function feed_billing_general_balances(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_general_balances();
        return $result;
    }

    public function feed_billing_balances_log(string $trafficEndpointId, int $page, int $count_in_page = 20): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_balances_log($page, $count_in_page);
        return $result;
    }

    public function history_log_billing_general_balances(string $trafficEndpointId, array $payload): array
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->get_array_history_billing_general_balances();
        return $result;
    }

    public function update_billing_balances_log(string $trafficEndpointId, string $logId, array $payload): bool
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->update_billing_balances_log($logId);
        return $result;
    }

    private function parse_timeframe($timeframe)
    {
        $explode = explode(' - ', $timeframe);
        return [
            'start' => new UTCDateTime(strtotime($explode[0] . " 00:00:00") * 1000),
            'end' =>   new UTCDateTime(strtotime($explode[1] . " 23:59:59") * 1000),
        ];
    }

    public function get_recalculate_logs(string $trafficEndpointId, array $payload): array
    {

        $page = $payload['page'] ?? 1;
        $count_in_page = $payload['count_in_page'] ?? 20;
        $timeframe = $payload['timeframe'] ?? '';

        $times = $this->parse_timeframe($timeframe);

        $where = [
            'collection' => "leads",
            'category' => ['$in' => ['crg', 'cpl']],
            'timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
            "description" => ['$not' => new \MongoDB\BSON\Regex(('.*CRG No Deals Found.*'), 'i')]
        ];

        $where_lead = [
            "lead.TrafficEndpoint" => $trafficEndpointId,
        ];

        if (!empty($payload['action_by'])) {
            $where['action_by'] = new ObjectId($payload['action_by']);
        }

        $scheme_type = ($payload['scheme_type'] ?? '');
        if ($scheme_type == 'crg_yes') {
            // $where['$or'] = [
            $where['data.crg_deal'] = true;
            // ];
        } else if ($scheme_type == 'crg_no') {
            $where['data.crg_deal'] = false;
            // $where['$or'][] = ['data.crg_deal' => null];
            // $where['$or'][] = ['data.crg_deal' => ['$exists' => false]];
        } else if ($scheme_type == 'cpl_yes') {
            $where['data.isCPL'] = true;
            // $where['$or'][] = ['data.broker_cpl' => null];
            // $where['$or'][] = ['data.broker_cpl' => ['$exists' => false]];
        } else if ($scheme_type == 'cpl_no') {
            $where['data.isCPL'] = false;
            // $where['$or'][] = ['data.broker_cpl' => null];
            // $where['$or'][] = ['data.broker_cpl' => ['$exists' => false]];
        }

        if (!empty($payload['country_code'])) {
            $where_lead['lead.country'] = strtoupper($payload['country_code']);
        }

        $mongo = new MongoDBObjects('history', []);

        $data = $mongo->aggregate([
            'pipeline' => [
                [
                    '$match' => $where
                ],
                [
                    '$lookup' =>  [
                        'from' => "leads",
                        'localField' => "primary_key",
                        'foreignField' => "_id",
                        'as' => "lead"
                    ]
                ],
                [
                    '$unwind' =>  ['path' => '$lead', 'preserveNullAndEmptyArrays' => true]
                ],
                [
                    '$match' => $where_lead
                ],
                [
                    '$group' => [
                        '_id'     => null,
                        'count'   => ['$sum' => 1]
                    ]
                ]
            ]
        ], true, false);

        $count = $data['count'] ?? 0;

        $data = $mongo->aggregate([
            'pipeline' => [
                [
                    '$match' => $where
                ],
                [
                    '$lookup' =>  [
                        'from' => "leads",
                        'localField' => "primary_key",
                        'foreignField' => "_id",
                        'as' => "lead"
                    ]
                ],
                [
                    '$unwind' =>  ['path' => '$lead', 'preserveNullAndEmptyArrays' => true]
                ],
                [
                    '$match' => $where_lead
                ],
                ['$sort' => ['timestamp' => -1]],
                ['$skip' => (($page - 1) * $count_in_page)],
                ['$limit' => $count_in_page],
                [
                    '$project' => [
                        '_id' => 1,
                        'timestamp' => 1,
                        'action' => 1,
                        'action_by' => 1,
                        'TrafficEndpoint' => '$lead.TrafficEndpoint',
                        'primary_key' => 1,
                        'country' => '$lead.country',
                        'LeadTimestamp' => '$lead.Timestamp',
                        'collection' => 1,
                        'category' => 1,
                        'description' => 1,
                        'data' => 1,
                    ]
                ],
            ]
        ], false, false);

        $_users = User::all(['_id', 'name', 'account_email']);
        $users = [];
        foreach ($_users as $user) {
            $users[$user->_id] = $user;
        }

        foreach ($data as &$d) {
            $d['action_by_data'] = $users[(string)($d['action_by'] ?? '')] ?? ['name' => 'system'];
            if ($d['category'] == 'cpl') {
                $d['active'] = $d['data']['isCPL'] ?? false;
                $d['category'] = 'CPL';
            } else {
                $d['active'] = $d['data']['crg_deal'] ?? false;
                $d['category'] = 'CRG';
            }
        }

        return [
            'count' => $count,
            'items' => $data
        ];
    }

    public function feed_billing_entities(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_entities();
        return $result;
    }

    public function get_billing_entities(string $trafficEndpointId, string $entityId): ?Model
    {
        $item = TrafficEndpointBillingEntities::findOrFail($entityId);
        StorageHelper::injectFiles('billing_entity', $item, 'files');
        return $item;
    }

    public function create_billing_entities(string $trafficEndpointId, array $payload): ?Model
    {
        StorageHelper::syncFiles('billing_entity', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $payload['endpoint'] = $trafficEndpointId;
        $model = TrafficEndpointBillingEntities::create($payload);
        return $model->fresh();
    }

    public function update_billing_entities(string $trafficEndpointId, string $entityId, array $payload): bool
    {
        $model = TrafficEndpointBillingEntities::findOrFail($entityId);
        StorageHelper::syncFiles('billing_entity', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function remove_billing_entities(string $trafficEndpointId, string $entityId): bool
    {
        $model = TrafficEndpointBillingEntities::findOrFail($entityId);
        StorageHelper::deleteFiles('billing_entity', $model, 'files');
        return $model->delete();
    }

    public function feed_billing_payment_methods(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_payment_methods();
        return $result;
    }

    public function create_billing_payment_methods(string $trafficEndpointId, array $payload): string
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->add_billing_payment_methods();
        return $result;
    }

    public function update_billing_payment_methods($trafficEndpointId, $id, $payload): bool
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->update_billing_payment_methods($id);
        return $result;
    }

    public function active_billing_payment_methods(string $trafficEndpointId, string $paymentMethodId): bool
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->active_billing_payment_methods($paymentMethodId);
        return $result;
    }

    public function files_billing_payment_methods(string $trafficEndpointId, string $paymentMethodId): array
    {
        $fields = ['files'];
        $model = TrafficEndpointBillingPaymentMethods::findOrFail($paymentMethodId);
        $result = [];
        foreach ($fields as $field) {
            StorageHelper::injectFiles('billing_payment_requests', $model, $field);

            $title = preg_replace_callback('#_(\w)#is', fn ($match) => (' ' . strtoupper($match[1])), '_' . $field);
            if (isset($model[$field])) {
                $result[] = [
                    'title' => $title,
                    'files' => $model[$field]
                ];
                unset($model[$field]);
            }
        }
        return $result;

        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->billing_payment_method_files($paymentMethodId);
        return $result;
    }

    public function feed_billing_payment_requests(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_payment_requests();
        return $result;
    }

    public function get_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->get_billing_payment_requests($paymentRequestId);
        return $result;
    }

    public function get_billing_payment_request_view_calculation(string $trafficEndpointId, string $paymentRequestId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->get_billing_payment_request_feed($paymentRequestId);
        return $result;
    }

    public function crg_details(string $trafficEndpointId, string $crgId, array $payload): array
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->_feed_crg_deal_details($crgId, $payload['timeframe']);
        return $result;
    }

    public function create_billing_payment_requests(string $trafficEndpointId, array $payload): array
    {
        $result = [];
        $payment_request_id = '';
        try {
            $payload['note'] = $payload['proof_description'];

            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = true;
            $result['data'] = $billing->save_billing_payment_requests();

            $payment_request_id = $result['data'];

            $billing->billing_payment_requests_master_approve($payment_request_id);
            // $this->master_approve_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

            $ui_leads = $payload['leads'] ?? '';
            if (!empty($ui_leads)) {
                $ui_leads = json_decode($ui_leads, true);
            } else {
                $ui_leads = [];
            }

            $billing->send_email_billing_payment_requests($payment_request_id, $ui_leads ?? []);
        } catch (Exception $ex) {

            if (!empty($payment_request_id)) {
                $model = TrafficEndpointBillingPaymentRequests::findOrFail($payment_request_id);
                $model->delete();
            }
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function pre_create_billing_payment_requests(string $trafficEndpointId, array $payload): array
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->feed_billing_payment_request();
        return $result;
    }

    public function get_payment_request_invoice(string $trafficEndpointId, string $paymentRequestId)
    {
        $billing = new ManageBilling($trafficEndpointId);
        $billing->download_invoice($paymentRequestId);
    }

    public function files_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId): array
    {
        $fields = ['affiliate_invoices', 'master_approve_files', 'final_approve_files', 'proof_screenshots'];
        $model = TrafficEndpointBillingPaymentRequests::findOrFail($paymentRequestId);
        $result = [];
        foreach ($fields as $field) {
            StorageHelper::injectFiles('billing_payment_requests', $model, $field);

            $title = preg_replace_callback('#_(\w)#is', fn ($match) => (' ' . strtoupper($match[1])), '_' . $field);
            if ($field == 'master_approve_files') {
                $title = 'Invoice proof';
            }
            if (isset($model[$field])) {
                $result[] = [
                    'title' => $title,
                    'files' => $model[$field]
                ];
                unset($model[$field]);
            }
        }
        return $result;

        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->billing_payment_requests_files($paymentRequestId);
        return $result;
    }

    public function approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function reject_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_reject($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function master_approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_master_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function real_income_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_real_income($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function final_reject_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_final_reject($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function final_approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->billing_payment_requests_final_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function archive_rejected_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($trafficEndpointId, $payload);
            $result['success'] = $billing->archive_billing_payment_requests($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function feed_completed_transactions(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_completed_transactions();
        return $result;
    }

    public function feed_adjustments(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_adjustments();
        return $result;
    }

    public function get_adjustment(string $trafficEndpointId, string $modelId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->get_billing_adjustments($modelId);
        return $result;
    }

    public function create_adjustment(string $trafficEndpointId, array $payload): array
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->add_billing_adjustments();
        return $result;
    }

    public function update_adjustment(string $trafficEndpointId, string $modelId, array $payload): bool
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->update_billing_adjustments($modelId);
        return $result;
    }

    public function delete_adjustment(string $trafficEndpointId, string $modelId): bool
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->remove_billing_adjustments($modelId);
        return $result;
    }

    public function feed_chargebacks(string $trafficEndpointId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->feed_billing_chargebacks();
        return $result;
    }

    public function get_chargebacks(string $trafficEndpointId, string $chargebackId): array
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->get_billing_chargebacks($chargebackId);
        return $result;
    }

    public function create_chargebacks(string $trafficEndpointId, array $payload): array
    {
        $billing = new ManageBilling($trafficEndpointId, $payload);
        $result = $billing->add_billing_chargebacks();
        return $result;
    }

    public function update_chargebacks(string $trafficEndpointId, string $chargebackId): bool
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->update_billing_chargebacks($chargebackId);
        return $result;
    }

    public function delete_chargebacks(string $trafficEndpointId, string $chargebackId): bool
    {
        $billing = new ManageBilling($trafficEndpointId);
        $result = $billing->remove_billing_chargebacks($chargebackId);
        return $result;
    }

    public function set_manual_status(string $trafficEndpointId, string $manual_status): bool
    {
        $model = TrafficEndpoint::findOrFail($trafficEndpointId);
        return $model->update(['billing_manual_status' => $manual_status]);
    }
}
