<?php

namespace App\Repository\Masters;

use App\Helpers\StorageHelper;
use App\Classes\StorageWrapper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Log;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Classes\Masters\GeneralBalances;
use App\Classes\Masters\PaymentRequests;
use App\Classes\Masters\MasterChangeLogs;
use App\Models\Masters\MasterIntegration;
use App\Models\Masters\MasterBillingEntity;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Billings\BillingPaymentMethod;
use App\Models\Masters\MasterBillingAdjustment;
use App\Models\Masters\MasterBillingChargeback;
use App\Models\Masters\MasterBillingPaymentMethod;
use App\Models\Masters\MasterBillingPaymentRequest;
use App\Repository\Masters\IMasterBillingRepository;
use Merkeleon\PhpCryptocurrencyAddressValidation\Validation;

class MasterBillingRepository extends BaseRepository implements IMasterBillingRepository
{

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

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct()
    {
    }

    public function get_payment_methods(string $masterId): array
    {
        $payment_methods = [
            'wire' => 'WIRE',
            'crypto' => 'CRYPTO'
        ];

        $_items = MasterBillingPaymentMethod::all()
            ->where('master', '=', $masterId)
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

    public function get_payment_requests_for_chargeback(string $masterId): array
    {
        $where = [
            'master' => $masterId,
            'final_status' => 1,
            '$or' => [
                ['chargeback' => ['$exists' => false]],
                ['chargeback' => false]
            ]
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $list = $mongo->findMany();

        foreach ($list as &$data) {
            $from = '';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $from = date("Y-m-d", $seconds);
            }

            $to = '';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $to = date("Y-m-d", $seconds);
            }

            $data['period'] = $from . ' - ' . $to . ' $' . $data['total'];
        }

        $items = [];
        foreach ($list as $item) {
            if (!empty($item['period'])) {
                $items[MongoDBObjects::get_id($item)] = $item['period'];
            }
        }

        return $items;
    }

    public function get_change_logs(string $masterId, bool $extended, string $collection, int $limit): array
    {
        $logs = new MasterChangeLogs($masterId);
        return $logs->get_change_logs($extended, $collection, $limit);
    }

    public function get_general_balance(string $masterId): array
    {
        $billing = new GeneralBalances($masterId);
        return $billing->get_general_balance();
    }

    public function feed_entities(string $masterId): Collection
    {
        $items = MasterBillingEntity::where(['master' => $masterId])->get();
        StorageHelper::injectFiles('billing_entity', $items, 'files');
        return $items;
    }

    public function get_entity(string $modelId): ?Model
    {
        $item = MasterBillingEntity::findOrFail($modelId);
        StorageHelper::injectFiles('billing_entity', $item, 'files');
        return $item;
    }

    public function create_entity(array $payload): ?Model
    {
        StorageHelper::syncFiles('billing_entity', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $model = MasterBillingEntity::create($payload);
        return $model->fresh();
    }

    public function update_entity(string $modelId, array $payload): bool
    {
        $model = MasterBillingEntity::findOrFail($modelId);
        StorageHelper::syncFiles('billing_entity', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_entity(string $modelId): bool
    {
        $model = MasterBillingEntity::findOrFail($modelId);
        StorageHelper::deleteFiles('billing_entity', $model, 'files');
        return $model->delete();
    }

    public function feed_chargebacks(string $masterId): Collection
    {
        $items = MasterBillingChargeback::where(['master' => $masterId])->with('payment_method_data')->get();
        foreach ($items as &$item) {
            $item->amount = abs($item->amount);
        }
        StorageHelper::injectFiles('billing_chargebacks', $items, 'screenshots');
        return $items;
    }

    public function get_chargeback(string $modelId): ?Model
    {
        $item = MasterBillingChargeback::findOrFail($modelId);
        $item->amount = abs($item->amount);
        StorageHelper::injectFiles('billing_chargebacks', $item, 'screenshots');
        return $item;
    }

    public function create_chargeback(array $payload): ?Model
    {
        $payload['amount'] = (float)($payload['amount'] ?? 0);

        if (!empty($payload['payment_request'])) {
            $payment_request = MasterBillingPaymentRequest::query()->find($payload['payment_request']);
            $payload['amount'] = (float)$payment_request->total;
        }

        if ($payload['amount'] > 0) {
            $payload['amount'] *= -1;
        }
        StorageHelper::syncFiles('billing_chargebacks', null, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $model = MasterBillingChargeback::create($payload);
        $result = $model->fresh();

        if (!empty($payload['payment_request'])) {
            $query = MasterBillingPaymentRequest::findOrFail($payload['payment_request']);
            $query->update(['chargeback' => true]);
        }

        return $result;
    }

    public function update_chargeback(string $modelId, array $payload): bool
    {
        $model = MasterBillingChargeback::findOrFail($modelId);

        if (!empty($model->payment_request)) {
            $query = MasterBillingPaymentRequest::findOrFail($model->payment_request);
            $query->update(['chargeback' => false]);
        }

        if (!empty($payload['payment_request'])) {
            $payment_request = MasterBillingPaymentRequest::findOrFail($payload['payment_request']);
            $payment_request->update(['chargeback' => true]);
            $payload['amount'] = (float)$payment_request->total;
        }

        if ($payload['amount'] > 0) {
            $payload['amount'] *= -1;
        }

        $payload['amount'] = (float)$payload['amount'];
        StorageHelper::syncFiles('billing_chargebacks', $model, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_chargeback(string $modelId): bool
    {
        $model = MasterBillingChargeback::findOrFail($modelId);

        if (!empty($model->payment_request)) {
            echo 111;
            $query = MasterBillingPaymentRequest::findOrFail($model->payment_request);
            $query->update(['chargeback' => false]);
        }

        StorageHelper::deleteFiles('billing_chargebacks', $model, 'screenshots');

        return $model->delete();
    }

    public function feed_adjustments(string $masterId): Collection
    {
        return MasterBillingAdjustment::where(['master' => $masterId])->get();
    }

    public function get_adjustment(string $modelId): ?Model
    {
        return MasterBillingAdjustment::findOrFail($modelId);
    }

    public function create_adjustment(array $payload): ?Model
    {
        $model = MasterBillingAdjustment::create($payload);
        return $model->fresh();
    }

    public function update_adjustment(string $modelId, array $payload): bool
    {
        $model = MasterBillingAdjustment::findOrFail($modelId);
        return $model->update($payload);
    }

    public function delete_adjustment(string $modelId): bool
    {
        return MasterBillingAdjustment::findOrFail($modelId)->delete();
    }

    public function feed_payment_methods(string $masterId): array
    {
        $all = MasterBillingPaymentMethod::query()->where('master', '=', $masterId)->get()->toArray();
        foreach ($all as &$billing) {
            $billing['wallet_validate'] = true;

            if (!empty($billing['wallet']) && $billing['payment_method'] == 'crypto' && (($billing['currency_crypto_code'] ?? '') == 'btc' || ($billing['currency_crypto_code'] ?? '') == 'usdt')) {
                $iso_code = $billing['currency_crypto_code'] ?? '';
                if ($billing['currency_crypto_code'] == 'usdt') {
                    if (($billing['currency_crypto_wallet_type'] ?? '') == 'erc_20') {
                        $iso_code = 'eth';
                    } else if (($billing['currency_crypto_wallet_type'] ?? '') == 'trc_20') {
                        $iso_code = 'trx';
                    }
                }
                if (!empty($iso_code) && in_array($iso_code, ['btc', 'eth', 'trx'])) {
                    $validator = Validation::make(strtoupper($iso_code));
                    $billing['wallet_validate'] = $validator->validate($billing['wallet']);
                } else {
                    $billing['wallet_validate'] = false;
                }
            }
        }
        return $all;
    }

    private function _set_default_active_billing_payment_methods(string $masterId)
    {
        $partner = $masterId;

        $where = ['master' => $partner, 'status' => ['$in' => ['1', 1]]];
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        if ($mongo->count() > 0) {
            return;
        }

        $update = [];
        $update['status'] = 1;

        $where = ['master' => $partner];
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        $data = $mongo->find();
        if (isset($data) && isset($data['_id'])) {
            $id = (array)$data['_id'];
            $id = $id['oid'];
            $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        }
        $mongo->update($update);
    }

    public function _active_billing_payment_methods(string $masterId, $id): bool
    {
        $partner = $masterId;

        $where = ['master' => $partner];
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        $update = [];
        $update['status'] = 0;
        $mongo->updateMulti($update);

        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        $update = [];
        $update['status'] = 1; //$this->request->post('status');
        $mongo->update($update);

        return true;
    }

    public function create_billing_payment_methods(string $masterId, array $payload): string
    {
        $model = new MasterBillingPaymentMethod();

        $insert = array();
        $insert['master'] = $masterId;

        $insert['payment_method'] = $payload['payment_method'] ?? '';
        $insert['currency_code'] = $payload['currency_code'] ?? '';
        $insert['currency_crypto_code'] = $payload['currency_crypto_code'] ?? '';
        $insert['currency_crypto_wallet_type'] = $payload['currency_crypto_wallet_type'] ?? '';
        $insert['bank_name'] = $payload['bank_name'] ?? '';
        $insert['swift'] = $payload['swift'] ?? '';
        $insert['account_name'] = $payload['account_name'] ?? '';
        $insert['account_number'] = $payload['account_number'] ?? '';
        $insert['notes'] = $payload['notes'] ?? '';
        $insert['wallet'] = $payload['wallet'] ?? '';
        $insert['wallet2'] = $payload['wallet2'] ?? '';
        $insert['status'] = 0;

        StorageHelper::syncFiles('billing_payment_methods', null, $insert, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = new MasterBillingPaymentMethod();
        $model->fill($insert);
        $model->save();
        $id = $model->id;

        // $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $insert);

        // $id = $mongo->insertWithToken();

        $this->_set_default_active_billing_payment_methods($masterId);

        return $id;
    }

    public function update_billing_payment_methods(string $masterId, string $payment_method_id, array $payload): bool
    {
        $model = MasterBillingPaymentMethod::findOrFail($payment_method_id);
        StorageHelper::syncFiles('billing_payment_methods', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function files_billing_payment_methods(string $masterId, string $payment_method_id): array
    {
        $fields = ['files'];
        $model = MasterBillingPaymentMethod::findOrFail($payment_method_id);
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
    }

    public function select_payment_method(string $masterId, string $methodId): bool
    {
        $result = $this->_active_billing_payment_methods($masterId, $methodId);
        return $result;
    }

    public function feed_payment_requests(string $masterId, bool $only_completed): Collection
    {
        $payments = new PaymentRequests($masterId);
        return $payments->feed($only_completed);
    }

    public function feed_payment_requests_query(string $masterId, array $payload): array
    {
        $payments = new PaymentRequests($masterId);
        return $payments->pre_create_query($payload);
    }

    public function create_payment_request(string $masterId, array $payload): array
    {
        // $payments = new PaymentRequests($masterId);
        // return $payments->create_payment_request($payload);

        $result = [];
        $payment_request_id = '';
        try {
            $payload['note'] = $payload['proof_description'];

            $billing = new PaymentRequests($masterId, $payload);
            $result['success'] = true;
            $result['data'] = $billing->create_payment_request($payload);

            $payment_request_id = $result['data'];

            $billing->master_approve($payment_request_id, $payload);

            $ui_leads = $payload['leads'] ?? '';
            if (!empty($ui_leads)) {
                $ui_leads = json_decode($ui_leads, true);
            } else {
                $ui_leads = [];
            }

            $billing->send_email_billing_payment_requests($payment_request_id, $ui_leads ?? []);
        } catch (\Exception $ex) {

            if (!empty($payment_request_id)) {
                $model = MasterBillingPaymentRequest::findOrFail($payment_request_id);
                $model->delete();
            }
            Log::error($ex);
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return $result;
    }

    public function get_payment_request(string $modelId): ?Model
    {
        return MasterBillingPaymentRequest::findOrFail($modelId);
    }

    public function get_payment_request_calculations(string $masterId, string $modelId): array
    {
        $payments = new PaymentRequests($masterId);
        return $payments->view_calculations($modelId);
    }

    public function get_payment_request_invoice(string $masterId, string $modelId): void
    {
        $payments = new PaymentRequests($masterId);
        $payments->download_invoice($modelId);
    }

    public function get_payment_request_files(string $modelId): array
    {
        $fields = ['affiliate_approve_files', 'final_approve_files', 'master_approve_files', 'proof_screenshots'];
        $model = MasterBillingPaymentRequest::findOrFail($modelId);
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

    public function payment_request_approve(string $masterId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->approve($modelId, $payload);
    }

    public function payment_request_master_approve(string $masterId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->master_approve($modelId, $payload);
    }

    public function payment_request_reject(string $masterId, string $modelId): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->reject($modelId);
    }

    public function payment_request_fin_approve(string $masterId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->fin_approve($modelId, $payload);
    }

    public function payment_request_fin_reject(string $masterId, string $modelId): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->fin_reject($modelId);
    }

    public function payment_request_real_income(string $masterId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->real_income($modelId, $payload);
    }

    // public function payment_request_real_income(string $masterId, string $modelId, array $payload): bool
    // {
    //     $payments = new PaymentRequests($masterId);
    //     return $payments->real_income($modelId, $payload);
    // }

    public function payment_request_archive(string $masterId, string $modelId): bool
    {
        $payments = new PaymentRequests($masterId);
        return $payments->archive($modelId);
    }
}
