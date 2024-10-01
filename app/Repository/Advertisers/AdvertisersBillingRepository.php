<?php

namespace App\Repository\Advertisers;

use App\Helpers\StorageHelper;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Billings\BillingPaymentMethod;

use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Advertisers\GeneralBalances;
use App\Classes\Advertisers\PaymentRequests;
use App\Classes\Advertisers\AdvertiserChangeLogs;

use App\Models\User;
use App\Models\MarketingAdvertiser;
use App\Models\Advertisers\MarketingAdvertiserBillingEntity;
use App\Models\Advertisers\MarketingAdvertiserBillingAdjustment;
use App\Models\Advertisers\MarketingAdvertiserBillingChargeback;
use App\Models\Advertisers\MarketingAdvertiserBillingPaymentMethod;
use App\Models\Advertisers\MarketingAdvertiserBillingPaymentRequest;

use App\Repository\Advertisers\IAdvertisersBillingRepository;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use phpDocumentor\Reflection\Types\Boolean;

class AdvertisersBillingRepository extends BaseRepository implements IAdvertisersBillingRepository
{
    /**
     * BaseRepository constructor.
     */
    public function __construct()
    {
    }

    public function get_general_balance(string $advertiserId): array
    {
        $billing = new GeneralBalances($advertiserId);
        return $billing->get_general_balance();
    }

    public function get_general_balance_logs(string $advertiserId, int $page, int $count_in_page = 20): array
    {
        $billing = new GeneralBalances($advertiserId);
        return $billing->get_balances_log($page, $count_in_page);
    }

    public function get_change_logs(string $advertiserId, bool $extended, string $collection, int $limit): array
    {
        $logs = new AdvertiserChangeLogs($advertiserId);
        return $logs->get_change_logs($extended, $collection, $limit);
    }

    private function parse_timeframe($timeframe)
    {
        $explode = explode(' - ', $timeframe);
        return [
            'start' => new UTCDateTime(strtotime($explode[0] . " 00:00:00") * 1000),
            'end' =>   new UTCDateTime(strtotime($explode[1] . " 23:59:59") * 1000),
        ];
    }

    public function get_general_crg_logs(string $advertiserId, array $payload): array
    {
        return [
            'count' => 0,
            'items' => 0
        ];
    }

    public function update_general_balance_logs(string $advertiserId, string $logId, array $payload): bool
    {
        $logs = new GeneralBalances($advertiserId, $payload);
        return $logs->update_general_balance_logs($logId);
    }

    public function set_negative_balance_action(string $advertiserId, string $action): bool
    {
        $billing = new GeneralBalances($advertiserId);
        return $billing->set_negative_balance_action($action);
    }

    public function set_credit_amount(string $advertiserId, int $amount): bool
    {
        $billing = new GeneralBalances($advertiserId);
        return $billing->set_credit_amount($amount);
    }

    public function feed_entities(string $advertiserId): Collection
    {
        $items = MarketingAdvertiserBillingEntity::where(['advertiser' => $advertiserId])->get();
        StorageHelper::injectFiles('billing_entity', $items, 'files');
        return $items;
    }

    public function get_entity(string $modelId): ?Model
    {
        $item = MarketingAdvertiserBillingEntity::findOrFail($modelId);
        StorageHelper::injectFiles('billing_entity', $item, 'files');
        return $item;
    }

    public function create_entity(array $payload): ?Model
    {
        StorageHelper::syncFiles('billing_entity', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $model = MarketingAdvertiserBillingEntity::create($payload);
        return $model->fresh();
    }

    public function update_entity(string $modelId, array $payload): bool
    {
        $model = MarketingAdvertiserBillingEntity::findOrFail($modelId);
        StorageHelper::syncFiles('billing_entity', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_entity(string $modelId): bool
    {
        $model = MarketingAdvertiserBillingEntity::findOrFail($modelId);
        StorageHelper::deleteFiles('billing_entity', $model, 'files');
        return $model->delete();
    }

    public function feed_chargebacks(string $advertiserId): Collection
    {
        $items = MarketingAdvertiserBillingChargeback::where(['advertiser' => $advertiserId])->with('payment_method_data')->with('payment_request_data')->get();
        StorageHelper::injectFiles('billing_chargebacks', $items, 'screenshots');
        foreach ($items as &$item) {
            $item->amount = abs($item->amount ?? 0);
        }
        return $items;
    }

    public function get_chargeback(string $modelId): ?Model
    {
        $item = MarketingAdvertiserBillingChargeback::findOrFail($modelId);
        $item->amount = abs($item->amount ?? 0);
        StorageHelper::injectFiles('billing_chargebacks', $item, 'screenshots');
        return $item;
    }

    public function create_chargeback(array $payload): ?Model
    {
        if (!empty($payload['payment_request'])) {
            $payment_request = MarketingAdvertiserBillingPaymentRequest::query()->find($payload['payment_request']);
            $payment_request->update(['chargeback' => true]);
            $payload['amount'] = (float)$payment_request->total;
        }
        $payload['amount'] = -abs((float)$payload['amount']);

        StorageHelper::syncFiles('billing_chargebacks', null, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $model = MarketingAdvertiserBillingChargeback::create($payload);
        return $model->fresh();
    }

    public function update_chargeback(string $modelId, array $payload): bool
    {
        $model = MarketingAdvertiserBillingChargeback::findOrFail($modelId);

        if (!empty($model->payment_request)) {
            $payment_request = MarketingAdvertiserBillingPaymentRequest::query()->find($model->payment_request);
            $payment_request->update(['chargeback' => false]);
        }

        if (!empty($payload['payment_request'])) {
            $payment_request = MarketingAdvertiserBillingPaymentRequest::query()->find($payload['payment_request']);
            $payment_request->update(['chargeback' => true]);
            $payload['amount'] = (float)$payment_request->total;
        }
        $payload['amount'] = -abs((float)$payload['amount']);

        StorageHelper::syncFiles('billing_chargebacks', $model, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_chargeback(string $modelId): bool
    {
        $model = MarketingAdvertiserBillingChargeback::findOrFail($modelId);
        StorageHelper::deleteFiles('billing_chargebacks', $model, 'screenshots');
        return $model->delete();
    }

    public function feed_adjustments(string $advertiserId): Collection
    {
        return MarketingAdvertiserBillingAdjustment::where(['advertiser' => $advertiserId])->get();
    }

    public function get_adjustment(string $modelId): ?Model
    {
        return MarketingAdvertiserBillingAdjustment::findOrFail($modelId);
    }

    public function create_adjustment(array $payload): ?Model
    {
        $model = MarketingAdvertiserBillingAdjustment::create($payload);
        return $model->fresh();
    }

    public function update_adjustment(string $modelId, array $payload): bool
    {
        $model = MarketingAdvertiserBillingAdjustment::findOrFail($modelId);
        return $model->update($payload);
    }

    public function delete_adjustment(string $modelId): bool
    {
        return MarketingAdvertiserBillingAdjustment::findOrFail($modelId)->delete();
    }

    public function feed_payment_methods(string $advertiserId): Collection
    {
        $selected = MarketingAdvertiserBillingPaymentMethod::firstWhere(['advertiser' => $advertiserId]);
        $all = BillingPaymentMethod::all();
        if ($selected) {
            foreach ($all as $item) {
                if ($item['_id'] == $selected['payment_method']) {
                    $item['status'] = $selected['status'];
                }
            }
        }
        return $all;
    }

    public function select_payment_method(string $advertiserId, string $methodId): bool
    {
        $model = MarketingAdvertiserBillingPaymentMethod::firstOrCreate(['advertiser' => $advertiserId], ['status' => 1]);
        return $model->update(['payment_method' => $methodId]);
    }

    public function feed_payment_requests(string $advertiserId, bool $only_completed): Collection
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->feed($only_completed);
    }

    public function feed_payment_requests_query(string $advertiserId, array $payload): array
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->pre_create_query($payload);
    }

    public function create_payment_request(string $advertiserId, array $payload): string
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->create_payment_request($payload);
    }

    public function get_payment_request(string $modelId): ?Model
    {
        return MarketingAdvertiserBillingPaymentRequest::findOrFail($modelId);
    }

    public function get_payment_request_calculations(string $advertiserId, string $modelId): array
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->view_calculations($modelId);
    }

    public function get_payment_request_invoice(string $advertiserId, string $modelId): void
    {
        $payments = new PaymentRequests($advertiserId);
        $payments->download_invoice($modelId);
    }

    public function get_payment_request_files(string $modelId): array
    {
        $fields = ['affiliate_approve_files', 'final_approve_files', 'master_approve_files'];
        $model = MarketingAdvertiserBillingPaymentRequest::findOrFail($modelId);
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
    }

    public function payment_request_approve(string $advertiserId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->approve($modelId, $payload);
    }

    public function payment_request_change(string $advertiserId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->change($modelId, $payload);
    }

    public function payment_request_reject(string $advertiserId, string $modelId): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->reject($modelId);
    }

    public function payment_request_fin_approve(string $advertiserId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->fin_approve($modelId, $payload);
    }

    public function payment_request_fin_reject(string $advertiserId, string $modelId): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->fin_reject($modelId);
    }

    public function payment_request_real_income(string $advertiserId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->real_income($modelId, $payload);
    }

    public function payment_request_archive(string $advertiserId, string $modelId): bool
    {
        $payments = new PaymentRequests($advertiserId);
        return $payments->archive($modelId);
    }

    public function set_manual_status(string $advertiserId, string $manual_status): bool
    {
        $model = MarketingAdvertiser::findOrFail($advertiserId);
        return $model->update(['billing_manual_status' => $manual_status]);
    }
}
