<?php

namespace App\Repository\Affiliates;

use Exception;
use App\Models\User;
use App\Helpers\StorageHelper;

use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Affiliates\ManageBilling;

use App\Models\Affiliates\AffiliateBillingEntities;
use App\Models\Affiliates\AffiliateBillingPaymentMethods;
use App\Models\Affiliates\AffiliateBillingPaymentRequests;
use App\Models\MarketingAffiliate;
use App\Repository\Affiliates\IAffiliateBillingRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AffiliateBillingRepository extends BaseRepository implements IAffiliateBillingRepository
{
    public function __construct()
    {
    }

    public function get_payment_methods(string $affiliateId): array
    {
        $payment_methods = [
            'wire' => 'WIRE',
            'crypto' => 'CRYPTO'
        ];

        $_items  = AffiliateBillingPaymentMethods::all()
            ->where('affiliate', '=', $affiliateId)
            // ->whereIn('status', ['1', 1])
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

    public function get_payment_requests_for_chargeback(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $where = [
            'affiliate' => $affiliateId,
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

    public function feed_billing_general_balances(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_general_balances();
        return $result;
    }

    public function feed_billing_balances_log(string $affiliateId, int $page, int $count_in_page = 20): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_balances_log($page, $count_in_page);
        return $result;
    }

    public function history_log_billing_general_balances(string $affiliateId, array $payload): array
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->get_array_history_billing_general_balances();
        return $result;
    }

    public function update_billing_balances_log(string $affiliateId, string $logId, array $payload): bool
    {
        $billing = new ManageBilling($affiliateId, $payload);
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

    public function get_crg_logs(string $affiliateId, array $payload): array
    {
        return [
            'count' => 0,
            'items' => 0
        ];
    }

    public function feed_billing_entities(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_entities();
        return $result;
    }

    public function get_billing_entities(string $affiliateId, string $entityId): ?Model
    {
        $item  = AffiliateBillingEntities::findOrFail($entityId);
        StorageHelper::injectFiles('billing_entity', $item, 'files');
        return $item;
    }

    public function create_billing_entities(string $affiliateId, array $payload): ?Model
    {
        StorageHelper::syncFiles('billing_entity', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $payload['affiliate'] = $affiliateId;
        $model  = AffiliateBillingEntities::create($payload);
        return $model->fresh();
    }

    public function update_billing_entities(string $affiliateId, string $entityId, array $payload): bool
    {
        $model  = AffiliateBillingEntities::findOrFail($entityId);
        StorageHelper::syncFiles('billing_entity', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function remove_billing_entities(string $affiliateId, string $entityId): bool
    {
        $model  = AffiliateBillingEntities::findOrFail($entityId);
        StorageHelper::deleteFiles('billing_entity', $model, 'files');
        return $model->delete();
    }

    public function feed_billing_payment_methods(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_payment_methods();
        return $result;
    }

    public function create_billing_payment_methods(string $affiliateId, array $payload): string
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->add_billing_payment_methods();
        return $result;
    }

    public function active_billing_payment_methods(string $affiliateId, string $paymentMethodId): bool
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->active_billing_payment_methods($paymentMethodId);
        return $result;
    }

    public function feed_billing_payment_requests(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_payment_requests();
        return $result;
    }

    public function get_billing_payment_requests(string $affiliateId, string $paymentRequestId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->get_billing_payment_requests($paymentRequestId);
        return $result;
    }

    public function get_billing_payment_request_view_calculation(string $affiliateId, string $paymentRequestId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->get_billing_payment_request_feed($paymentRequestId);
        return $result;
    }

    public function crg_details(string $affiliateId, string $crgId, array $payload): array
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->_feed_crg_deal_details($crgId, $payload['timeframe']);
        return $result;
    }

    public function create_billing_payment_requests(string $affiliateId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = true;
            $result['data'] = $billing->save_billing_payment_requests();
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function pre_create_billing_payment_requests(string $affiliateId, array $payload): array
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->feed_billing_payment_request();
        return $result;
    }

    public function get_payment_request_invoice(string $affiliateId, string $paymentRequestId)
    {
        $billing = new ManageBilling($affiliateId);
        $billing->download_invoice($paymentRequestId);
    }

    public function files_billing_payment_requests(string $affiliateId, string $paymentRequestId): array
    {
        $fields = ['affiliate_invoices', 'master_approve_files', 'final_approve_files'];
        $model  = AffiliateBillingPaymentRequests::findOrFail($paymentRequestId);
        $result = [];
        foreach ($fields as $field) {
            StorageHelper::injectFiles('billing_payment_requests', $model, $field);
            if (isset($model[$field])) {
                $result[] = [
                    'title' => preg_replace_callback('#_(\w)#is', fn ($match) => (' ' . strtoupper($match[1])), '_' . $field),
                    'files' => $model[$field]
                ];
                unset($model[$field]);
            }
        }
        return $result;

        $billing = new ManageBilling($affiliateId);
        $result = $billing->billing_payment_requests_files($paymentRequestId);
        return $result;
    }

    public function approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function reject_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_reject($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function master_approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_master_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function real_income_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_real_income($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function final_reject_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_final_reject($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function final_approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->billing_payment_requests_final_approve($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function archive_rejected_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array
    {
        $result = [];
        try {
            $billing = new ManageBilling($affiliateId, $payload);
            $result['success'] = $billing->archive_billing_payment_requests($paymentRequestId);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function feed_completed_transactions(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_completed_transactions();
        return $result;
    }

    public function feed_adjustments(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_adjustments();
        return $result;
    }

    public function get_adjustment(string $affiliateId, string $modelId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->get_billing_adjustments($modelId);
        return $result;
    }

    public function create_adjustment(string $affiliateId, array $payload): array
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->add_billing_adjustments();
        return $result;
    }

    public function update_adjustment(string $affiliateId, string $modelId, array $payload): bool
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->update_billing_adjustments($modelId);
        return $result;
    }

    public function delete_adjustment(string $affiliateId, string $modelId): bool
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->remove_billing_adjustments($modelId);
        return $result;
    }

    public function feed_chargebacks(string $affiliateId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->feed_billing_chargebacks();
        return $result;
    }

    public function get_chargebacks(string $affiliateId, string $chargebackId): array
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->get_billing_chargebacks($chargebackId);
        return $result;
    }

    public function create_chargebacks(string $affiliateId, array $payload): array
    {
        $billing = new ManageBilling($affiliateId, $payload);
        $result = $billing->add_billing_chargebacks();
        return $result;
    }

    public function update_chargebacks(string $affiliateId, string $chargebackId): bool
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->update_billing_chargebacks($chargebackId);
        return $result;
    }

    public function delete_chargebacks(string $affiliateId, string $chargebackId): bool
    {
        $billing = new ManageBilling($affiliateId);
        $result = $billing->remove_billing_chargebacks($chargebackId);
        return $result;
    }

    public function set_manual_status(string $affiliateId, string $manual_status): bool
    {
        $model  = MarketingAffiliate::findOrFail($affiliateId);
        return $model->update(['billing_manual_status' => $manual_status]);
    }
}
