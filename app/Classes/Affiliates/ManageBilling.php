<?php

namespace App\Classes\Affiliates;

use App\Models\User;
use App\Classes\GoogleAuth;

use App\Helpers\QueryHelper;
use App\Helpers\RenderHelper;
use App\Helpers\GeneralHelper;

use App\Helpers\StorageHelper;
use App\Classes\StorageWrapper;
use App\Models\Affiliate;
use App\Classes\Mongo\MongoQuery;

use App\Classes\History\HistoryDB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Brokers\InvoiceDocument;
use App\Classes\History\HistoryDBAction;
use App\Notifications\SlackNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Models\Affiliates\AffiliateBillingEntities;
use App\Models\Affiliates\AffiliateBillingAdjustments;
use App\Models\Affiliates\AffiliateBillingChargebacks;
use App\Models\Affiliates\AffiliateBillingPaymentMethods;
use App\Models\Affiliates\AffiliateBillingPaymentRequests;
use App\Models\MarketingAffiliate;

class ManageBilling
{
    private string $_affiliateId;

    private $request;

    private $collections = [
        'billing_entities' => 'marketing_affiliate_billing_entities',
        'billing_payment_methods' => 'marketing_affiliate_billing_payment_methods',
        'billing_payment_requests' => 'marketing_affiliate_billing_payment_requests',
        'billing_chargebacks' => 'marketing_affiliate_billing_chargebacks',
        'billing_adjustments' => 'marketing_affiliate_billing_adjustments',
    ];

    private $start;
    private $end;
    private $_payload;

    public function __construct(string $affiliateId, array $payload = [])
    {
        $this->_affiliateId = $affiliateId;
        $this->_payload = $payload;

        $this->request = new class($payload)
        {
            private array $payload;
            public function __construct(array $payload)
            {
                $this->payload = $payload;
            }
            public function get($name, $default = null)
            {
                return $this->payload[$name] ?? $default;
            }
            public function post($name, $default = null)
            {
                return $this->payload[$name] ?? $default;
            }
        };
    }

    public static $chargeback_types = [
        // 'prepayment' => 'Prepayment',
        'refund' => 'Refund',
    ];

    private function request_get($name, $default = null)
    {
        return $this->_payload[$name] ?? $default;
    }
    private function request_post($name, $default = null)
    {
        return $this->_payload[$name] ?? $default;
    }

    private function get_affiliate()
    {
        return MarketingAffiliate::query()->find($this->_affiliateId)->toArray();
    }

    private function get_payment_methods()
    {
        $array = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $payment_methods = [];
        foreach ($array as $payment_method) {
            $payment_method_id = $payment_method['_id'];
            $payment_methods[$payment_method_id] = $payment_method;
        }
        return $payment_methods;
    }

    private function buildTimestamp($timeframe)
    {
        $explode = explode('-', $timeframe);

        if (count($explode) == 0) {
            return [
                'start' => null,
                'end' => null
            ];
        }
        $time_range = array();

        $givebackstamp = function ($d) {
            $d = trim($d);
            $array = explode('/', $d);
            if (count($array) > 1) {
                return ($array[2] ?? '') . '-' . ($array[0] ?? '') . '-' . ($array[1] ?? '');
            }
            return $d;
        };

        $this->start = strtotime($givebackstamp($explode[0]) . " 00:00:00");

        if (count($explode) > 1) {
            $this->end = strtotime($givebackstamp($explode[1]) . " 23:59:59");
        } else {
            $this->end = null;
        }

        $start = new \MongoDB\BSON\UTCDateTime($this->start * 1000);
        $end = new \MongoDB\BSON\UTCDateTime($this->end * 1000);

        $time_range['start'] = $start;
        $time_range['end'] = $end;

        $time_range['where'] = [
            'EventType' => ['$ne' => 'CLICK'],
            'EventTimeStamp' => ['$gte' => $start, '$lte' => $end],
        ];

        return $time_range;
    }

    ///// ---- Billing Entities ---- //////

    public function feed_billing_entities()
    {
        $array = AffiliateBillingEntities::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $result = [];

        $countries = GeneralHelper::countries();

        $storage = StorageWrapper::instance('billing_entity');

        foreach ($array as $billing) {

            $files = [];
            if (isset($billing['files']) && count($billing['files']) > 0) {
                $files = $storage->get_files($billing['files']);
            }

            $billing['country'] = [
                'value' => $billing['country_code'] ?? '',
                'title' => $countries[strtolower($billing['country_code'])] ?? ''
            ];
            unset($billing['country_code']);

            $billing['currency'] = [
                'value' => $billing['currency_code'],
                'title' => strtoupper($billing['currency_code'])
            ];

            $billing['files'] = $files;
            $result[] = $billing;
        }

        return $result;
    }

    public function get_billing_entities()
    {
        $data = AffiliateBillingEntities::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        for ($i = 0; $i < count($data); $i++) {
            if (isset($data[$i]['files']) && count($data[$i]['files']) > 0) {
                $storage = StorageWrapper::instance('billing_entity');
                $data[$i]['files'] = $storage->get_files($data[$i]['files']);
            }
            $data[$i]['title'] = $data[$i]['company_legal_name'];
        }
        return $data;
    }

    public function get_billing_entity($id)
    {
        $data = AffiliateBillingEntities::query()->find($id)->toArray();
        if (isset($data['files']) && count($data['files']) > 0) {
            $storage = StorageWrapper::instance('billing_entity');
            $data['files'] = $storage->get_files($data['files']);
        }
        unset($data['_id']);
        return $data;
    }

    public function remove_billing_entity($id)
    {
        $query = AffiliateBillingEntities::query()->find($id);
        $item = $query->toArray();
        if (isset($item['files']) && count($item['files']) > 0) {
            $storage = StorageWrapper::instance('billing_entity');
            $storage->delete_files((array)$item['files']);
        }
        return $query->delete();
    }

    public function update_billing_entity($id)
    {
        $model = AffiliateBillingEntities::findOrFail($id);

        $update = [];
        $update['company_legal_name'] = $this->request_post('company_legal_name');
        $update['country_code'] = $this->request_post('country_code');
        $update['region'] = $this->request_post('region');
        $update['city'] = $this->request_post('city');
        $update['zip_code'] = $this->request_post('zip_code');
        $update['currency_code'] = $this->request_post('currency_code');
        $update['vat_id'] = $this->request_post('vat_id');
        $update['registration_number'] = $this->request_post('registration_number');

        StorageHelper::syncFiles('billing_entity', $model, $update, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model->update($update);

        return true;
    }

    public function add_billing_entity()
    {
        $insert = array();
        $insert['affiliate'] = $this->_affiliateId;

        $insert['company_legal_name'] = $this->request_post('company_legal_name');
        $insert['country_code'] = $this->request_post('country_code');
        $insert['region'] = $this->request_post('region');
        $insert['city'] = $this->request_post('city');
        $insert['zip_code'] = $this->request_post('zip_code');
        $insert['currency_code'] = $this->request_post('currency_code');
        $insert['vat_id'] = $this->request_post('vat_id');
        $insert['registration_number'] = $this->request_post('registration_number');

        StorageHelper::syncFiles('billing_entity', null, $insert, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = new AffiliateBillingEntities();
        $model->fill($insert);
        $model->save();
        $id = $model->id;

        return ['success' => true, 'error' => '', 'id' => $id];
    }

    ///// ---- Billing Payment Methods ---- //////

    public function feed_billing_payment_methods(): array
    {
        $html = '';

        $array = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $icons = [
            'crypto' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 25" height="50" width="50" style="">
                    <g transform="matrix(1.7814285714285716,0,0,1.7814285714285716,0,0)"><g>
                        <g>
                        <path d="M7.88,6.66h0A1.37,1.37,0,0,0,9.27,5.29h0A1.37,1.37,0,0,0,7.9,3.92H5.79a.5.5,0,0,0-.5.5v5a.5.5,0,0,0,.5.5H7.88a1.62,1.62,0,1,0,0-3.24Z" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></path>
                        <line x1="7.9" y1="6.66" x2="5.29" y2="6.66" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="6.24" y1="3.92" x2="6.24" y2="3" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="7.78" y1="3.92" x2="7.78" y2="3" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="6.24" y1="11" x2="6.24" y2="9.9" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="7.78" y1="11" x2="7.78" y2="9.9" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        </g>
                        <circle cx="7" cy="7" r="6.5" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></circle>
                    </g></g></svg>',
            'wire' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" height="50" width="50">
                    <g transform="matrix(3.5714285714285716,0,0,3.5714285714285716,0,0)"><g>
                        <path d="M12.91,5.5H1.09c-.56,0-.8-.61-.36-.9L6.64.73a.71.71,0,0,1,.72,0L13.27,4.6C13.71,4.89,13.47,5.5,12.91,5.5Z" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></path>
                        <rect x="0.5" y="11" width="13" height="2.5" rx="0.5" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></rect>
                        <line x1="2" y1="5.5" x2="2" y2="11" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="4.5" y1="5.5" x2="4.5" y2="11" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="7" y1="5.5" x2="7" y2="11" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="9.5" y1="5.5" x2="9.5" y2="11" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                        <line x1="12" y1="5.5" x2="12" y2="11" style="fill: none;stroke: #000000;stroke-linecap: round;stroke-linejoin: round"></line>
                    </g></g></svg>'
        ];

        $result = [];

        foreach ($array as $billing) {

            $files = [];
            if (isset($billing['files']) && count($billing['files']) > 0) {
                $files = StorageWrapper::get_files($billing['files']);
            }

            $billing['files'] = $files;
            $billing['icon'] = $icons[($billing['payment_method'] ?? '')] ?? '';
            $result[] = $billing;
        }

        return $result;
    }

    public function set_default_active_billing_payment_methods(): bool
    {
        $where = ['affiliate' => $this->_affiliateId, 'status' => ['$in' => ['1', 1]]];
        $mongo = new MongoDBObjects($this->collections['billing_payment_methods'], $where);
        if ($mongo->count() > 0) {
            return true;
        }

        $update = [];
        $update['status'] = 1;

        // $query = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId)->first();
        // $query->update($update);

        $where = ['affiliate' => $this->_affiliateId];
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

    public function active_billing_payment_methods($id)
    {
        $query = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId);
        $query->update(['status' => 0]);

        $query = AffiliateBillingPaymentMethods::query()->find($id);
        $query->update(['status' => 1]);

        return true;
    }

    public function get_billing_payment_methods($id)
    {
        $data = AffiliateBillingPaymentMethods::query()->find($id)->toArray();

        if (isset($data['files']) && count($data['files']) > 0) {
            // $storage = StorageWrapper::instance('billing_payment_methods');
        }
        unset($data['_id']);
        return $data;
    }

    public function get_active_billing_payment_method()
    {
        $query = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId)->whereIn('status', ['1', 1])->first('_id');
        return $query->id;
    }

    public function get_billing_payment_method_array()
    {
        $array = AffiliateBillingPaymentMethods::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $payment_methods = [];
        $payment_method = [
            'wire' => 'WIRE',
            'crypto' => 'CRYPTO'
        ];
        foreach ($array as $ar) {
            $id = $ar['_id'];
            $ar['title'] = $payment_method[$ar['payment_method']] ?? '';
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
            $payment_methods[$id] = $ar;
        }
        return $payment_methods;
    }

    public function remove_billing_payment_methods($id)
    {

        $query = AffiliateBillingPaymentMethods::query()->find($id);
        $item = $query->toArray();

        if (isset($item['files']) && count($item['files']) > 0) {
            $storage = StorageWrapper::instance('billing_payment_methods');
            $storage->delete_files((array)$item['files']);
        }
        $b = $query->deleteOne();

        $this->set_default_active_billing_payment_methods();

        return $b;
    }

    public function update_billing_payment_methods($id)
    {
        $model = AffiliateBillingPaymentMethods::findOrFail($id);

        $update = [];
        $update['payment_method'] = $this->request_post('payment_method');
        $update['currency_code'] = $this->request_post('currency_code');
        $update['currency_crypto_code'] = $this->request_post('currency_crypto_code');
        $update['bank_name'] = $this->request_post('bank_name');
        $update['swift'] = $this->request_post('swift');
        $update['account_name'] = $this->request_post('account_name');
        $update['account_number'] = $this->request_post('account_number');
        $update['notes'] = $this->request_post('notes');
        $update['wallet'] = $this->request_post('wallet');

        StorageHelper::syncFiles('billing_payment_methods', $model, $update, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model->update($update);

        return true;
    }

    public function add_billing_payment_methods()
    {
        $insert = array();
        $insert['affiliate'] = $this->_affiliateId;

        $insert['payment_method'] = $this->request_post('payment_method');
        $insert['currency_code'] = $this->request_post('currency_code');
        $insert['currency_crypto_code'] = $this->request_post('currency_crypto_code');
        $insert['bank_name'] = $this->request_post('bank_name');
        $insert['swift'] = $this->request_post('swift');
        $insert['account_name'] = $this->request_post('account_name');
        $insert['account_number'] = $this->request_post('account_number');
        $insert['notes'] = $this->request_post('notes');
        $insert['wallet'] = $this->request_post('wallet');
        $insert['status'] = 0;

        StorageHelper::syncFiles('billing_payment_methods', null, $insert, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = new AffiliateBillingPaymentMethods();
        $model->fill($insert);
        $model->save();
        $id = $model->id;

        $this->set_default_active_billing_payment_methods();

        return $id;
    }

    ///// ---- Billing Chargebacks ---- //////

    public function feed_billing_chargebacks(): array
    {
        $result = [];

        $payment_methods = $this->get_billing_payment_method_array();

        $array = AffiliateBillingChargebacks::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $storage = StorageWrapper::instance('billing_chargebacks');

        foreach ($array as $billing) {

            $color = '';
            if ($billing['amount'] > 0) {
                $color .= 'green';
            } elseif ($billing['amount'] < 0) {
                $color .= 'red';
            }

            $files = [];
            if (isset($billing['screenshots']) && count($billing['screenshots']) > 0) {
                $files = $storage->get_files($billing['screenshots']);
            }

            $payment_request_title = '';
            if (!empty($billing['payment_request'])) {
                $payment_request = $this->get_billing_payment_request($billing['payment_request']);
                $payment_request_title = ($payment_request['period'] ?? '');
            }

            $payment_method = $payment_methods[$billing['payment_method']];

            $billing['screenshots'] = $files;
            $billing['color'] = $color;
            $billing['payment_method'] = [
                'value' => $billing['payment_method'],
                'title' => $payment_method
            ];
            $billing['payment_request'] = [
                'value' => ($billing['payment_request'] ?? ''),
                'title' => $payment_request_title
            ];
            $billing['format_amount'] = str_replace('$-', '-$', '$' . $billing['amount']);

            $billing['amount'] = abs($billing['amount']);
            $billing['desc'] = strtoupper($payment_method['payment_method']) . ' (' . strtoupper($payment_method['payment_method'] == 'wire' ? $payment_method['currency_code'] : $payment_method['currency_crypto_code']) . ') ' . $payment_method['bank_name'];

            $result[] = $billing;
        }

        return $result;
    }

    public function get_billing_chargebacks_balans()
    {
        $where = [
            'affiliate' => (string)$this->_affiliateId
        ];

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

    public function get_billing_chargebacks($id)
    {
        $data = AffiliateBillingChargebacks::query()->find($id)->toArray();
        $data['amount'] = abs($data['amount'] ?? 0);
        if (isset($data['screenshots']) && count($data['screenshots']) > 0) {
            $data['screenshots'] = StorageWrapper::get_files($data['screenshots']);
        }
        unset($data['_id']);
        return $data;
    }

    public function remove_billing_chargebacks($id)
    {
        $query = AffiliateBillingChargebacks::query()->find($id);
        $item = $query->get()->toArray();

        if (isset($item['screenshots']) && count($item['screenshots']) > 0) {
            $storage = StorageWrapper::instance('billing_chargebacks');
            $storage->delete_files((array)$item['screenshots']);
        }

        $result = $query->delete();

        if (!empty($item['payment_request'])) {
            $query = AffiliateBillingPaymentRequests::query()->find($item['payment_request']);
            $update = ['chargeback' => false];
            $query->update($update);
        }

        return $result;
    }

    public function update_billing_chargebacks($id)
    {
        $model = AffiliateBillingChargebacks::findOrFail($id);

        $update = [];
        // $update['type'] = $this->request_post('type');
        $update['payment_method'] = $this->request_post('payment_method');
        $update['payment_request'] = ($this->request_post('payment_request_active') == '1' ? $this->request_post('payment_request') : '');
        $update['amount'] = abs((float)$this->request_post('amount'));

        if (!empty($update['payment_request'])) {
            $payment_request = $this->get_billing_payment_request($update['payment_request']);
            $update['amount'] = $payment_request['total'];
        }

        // if ($update['type'] == 'refund') {
        $update['amount'] *= -1;
        // }

        StorageHelper::syncFiles('billing_chargebacks', $model, $update, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model->update($update);

        if (!empty($model->payment_request)) {
            $query = AffiliateBillingPaymentRequests::query()->find($model->payment_request);
            $query->update(['chargeback' => false]);
        }

        if (!empty($update['payment_request'])) {
            $query = AffiliateBillingPaymentRequests::query()->find($update['payment_request']);
            $query->update(['chargeback' => true]);
        }

        return true;
    }

    public function add_billing_chargebacks()
    {
        $insert = array();
        $insert['affiliate'] = $this->_affiliateId;

        // $insert['type'] = $this->request_post('type');
        $insert['payment_method'] = $this->request_post('payment_method');
        $insert['payment_request'] = $this->request_post('payment_request');
        $insert['amount'] = abs((float)$this->request_post('amount'));

        if (!empty($insert['payment_request'])) {
            $payment_request = $this->get_billing_payment_request($insert['payment_request']);
            $insert['amount'] = $payment_request['total'];
        }

        // if ($insert['type'] == 'refund') {
        $insert['amount'] *= -1;
        // }

        StorageHelper::syncFiles('billing_chargebacks', null, $insert, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = new AffiliateBillingChargebacks();
        $model->fill($insert);
        $model->save();
        $id = $model->id;

        if (!empty($insert['payment_request'])) {
            $query = AffiliateBillingPaymentRequests::query()->find($insert['payment_request']);
            $query->update(['chargeback' => true]);
        }

        return ['success' => true, 'id' => $id];
    }

    ///// ---- Billing Adjustments ---- //////

    public function feed_billing_adjustments(): array
    {
        $array = AffiliateBillingAdjustments::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $result = [];

        foreach ($array as $billing) {

            $color = '';
            if ($billing['amount'] > 0) {
                $color = 'green';
            } elseif ($billing['amount'] < 0) {
                $color = 'red';
            }

            $billing['color'] = $color;
            $result[] = $billing;
        }

        return $result;
    }

    public function get_billing_adjustments_balans()
    {
        $where = [
            'affiliate' => (string)$this->_affiliateId
        ];

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

    public function get_billing_adjustments($id)
    {
        $data = AffiliateBillingAdjustments::query()->find($id)->toArray();

        $data['amount_sign'] = $data['amount'] < 0 ? -1 : 1;
        $data['amount'] = abs($data['amount']);

        // if (isset($data['bi_timestamp'])) {
        //     $ts = (array)$data['bi_timestamp'];
        //     $mil = $ts['milliseconds'];
        //     $seconds = $mil / 1000;
        //     $dt = date("Y-m-d", $seconds);
        //     $data['bi_timestamp'] = $dt;
        // }

        unset($data['_id']);
        return $data;
    }

    public function remove_billing_adjustments($id)
    {
        $query = AffiliateBillingAdjustments::query()->find($id);
        return $query->delete();
    }

    public function update_billing_adjustments($id)
    {
        $model = AffiliateBillingAdjustments::findOrFail($id);

        $update = [];
        $update['amount'] = (float)$this->request_post('amount_sign', 1) * (float)$this->request_post('amount', 0);
        $update['description'] = (string)$this->request_post('description');
        $update['bi'] = (bool)($this->request_post('bi_timestamp', false));

        $bi_timestamp = $this->request_post('bi_timestamp', false);
        if ($bi_timestamp) {
            $update['bi_timestamp'] = GeneralHelper::ToMongoDateTime($bi_timestamp);
        }

        // $update['bi_timestamp'] = null;
        // if ($update['bi']) {
        // $bi_timestamp = trim($this->request_post('bi_timestamp', ''));
        // if (empty($bi_timestamp)) {
        //     $bi_timestamp = date("Y-m-d 00:00:00");
        // }
        // $var = date("Y-m-d 00:00:00", strtotime($bi_timestamp));
        // $update['bi_timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        // }
        // $mongo->update($update);
        // print_r($update);
        $model->update($update);

        return true;
    }

    public function add_billing_adjustments()
    {
        $partner = $this->_affiliateId;

        $insert = array();
        $insert['affiliate'] = $partner;
        $var = date("Y-m-d H:i:s");
        $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        $insert['created_by'] = Auth::id();

        $insert['amount'] = (float)($this->request_post('amount_sign', 1) ?? 1) * (float)$this->request_post('amount');
        $insert['description'] = (string)$this->request_post('description');
        $insert['bi'] = (bool)($this->request_post('bi_timestamp', false));

        $bi_timestamp = $this->request_post('bi_timestamp', false);
        if ($bi_timestamp) {
            $insert['bi_timestamp'] = GeneralHelper::ToMongoDateTime($bi_timestamp);
        }

        // $insert['bi'] = (bool)($this->request_post('bi'));

        // $bi_timestamp = null;
        // if ($insert['bi']) {
        //     $bi_timestamp = $this->request_post('bi_timestamp');
        //     if (empty($bi_timestamp)) {
        //         $bi_timestamp = date("Y-m-d 00:00:00");
        //     }
        //     $var = date("Y-m-d 00:00:00", strtotime($bi_timestamp));
        //     $insert['bi_timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        // }

        $model = new AffiliateBillingAdjustments();
        $model->fill($insert);
        $model->save();
        $id = $model->id;

        return ['success' => true, 'id' => $id];
    }

    ///// ---- Billing Payment Request ---- //////

    public function billing_payment_requests_group_statuses($time_range)
    {
        $where = [
            '$and' => [
                ['AffiliateId' => (string)$this->_affiliateId],
                ['EventTimeStamp' => ['$gte' => $time_range['start'], '$lte' => $time_range['end']]],
            ],
        ];

        $get_leads = function ($deal_id, $field_status) use ($where) {
            $mongo = new MongoDBObjects('mleads', $where);
            $data = $mongo->aggregate([
                'group' => [
                    '_id' => [
                        '$' . $deal_id,
                        '$' . $field_status
                    ],
                    'count' => [
                        '$sum' => 1
                    ]
                ]
            ], true);

            if (!isset($data)) {
                $data = [$deal_id => '', $field_status => '', 'count' => 0];
            }
        };

        // $broker = $get_leads('last_try_broker_crg_percentage_id', 'broker_crg_status');
        // $endpoint = $get_leads('last_try_crg_percentage_id', 'crg_status');

        return [
            'broker' => 0, //$broker,
            'endpoint' => 0, //$endpoint,
        ];
    }

    ///// ---- Billing Payment Request ---- //////

    public function billing_payment_requests_buildParameterArray()
    {

        $formula = array();
        $array = array();

        $pivots = [
            // 'Affiliate',
            '_id',
            'GeoCountryName',
            'EventTypeSchema',
        ];

        $metrics = [
            'leads',
            'cost',
            'conversions'
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_cost = array('cost' => [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                $cost = 0.0;
                $cost = __AffiliatePayout__;
                return (float)$cost;
            ',
            'formula_return' => false
        ]);

        foreach ($metrics as $metrics) {

            if ($metrics == 'cost') {

                $array['cost'] = $_cost;

                $formula['cost'] = '__cost__';
            }

            if ($metrics == 'leads') {
                $array['Leads'] = array('Leads' => [
                    'type' => 'count',
                    'formula' => '
                        //if ( __EventTimeStamp__ >= ' . $this->start . ' && __EventTimeStamp__ <= ' . $this->end . ' ) {
						if ( strtoupper(__(string)EventType__) == "LEAD" || strtoupper(__(string)EventType__) == "POSTBACK" ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            }

            if ($metrics == 'conversions') {
                $array['Conversions'] = array('conversions' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)Approved__ == TRUE ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    private function _get_feed_billing_payment_request($timeframe)
    {
        // -- query
        $time_range = $this->buildTimestamp($timeframe);
        $query = $this->billing_payment_requests_buildParameterArray();

        $conditions = [
            'AffiliateId' => [$this->_affiliateId],
            // 'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time_range, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachMarketingFormula($query_data, $query['formula']);

        return $data;
    }

    private function _get_feed_group_by_billing_payment_request($data)
    {
        $except = [];
        if (count($data) == 0) {
            return [];
        }

        $DataSchema = QueryHelper::DataSchema($data[0], $except);

        $find = MarketingAffiliate::all()->toArray();
        $Affiliates = array();

        foreach ($find as $supply) {
            $id = $supply['_id'];
            $Affiliates[$id] = ($supply['token'] ?? '');
        }

        $groupBy = [];
        foreach ($data as $ndata => $datas) {

            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('Affiliate' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $Affiliates)) $b = false;
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
                    $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['cost'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += 1; // $subGroupBy[$name]['Leads'] +  $datas['Leads'];
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                    }
                }
                // CPA
                else if (strpos($groupKey, 'EventSchema[cpa]') !== false) {
                    $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['cost'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += $datas['Leads'] ?? 0;
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                    }
                } else {
                    $name = $groupKey . '///cost[' . $datas['cost'] . ']';
                    if (!isset($subGroupBy[$name])) {
                        $datas['total'] = $datas['cost'];
                        $datas['Leads'] = 1;
                        $datas['Conversions'] = $datas['Conversions'] ?? 0;
                        $subGroupBy[$name] = $datas;
                    } else {
                        $subGroupBy[$name]['Leads'] += $datas['Leads'] ?? 0;
                        $subGroupBy[$name]['Conversions'] += $datas['Conversions'] ?? 0;
                        $subGroupBy[$name]['total'] = $subGroupBy[$name]['total'] +  $datas['cost'];
                    }
                }

                if (!empty($datas['_id']) && $datas['Conversions'] > 0) {
                    $subGroupBy[$name]['depo_ids'][] = $datas['_id'];
                }
            }
        }

        // recalc deposit when cost is zero
        foreach ($subGroupBy as $key0 => $data0) {
            if (($data0['cost'] ?? 0) != 0) continue;

            $key0 = preg_replace('#\Wcost\[\d+\]#', '', $key0);

            // becouse of cost zero put deposit to another row
            foreach ($subGroupBy as $key1 => &$data1) {
                if (($data1['cost'] ?? 0) == 0) continue;

                $key1 = preg_replace('#\Wcost\[\d+\]#', '', $key1);

                if ($key0 == $key1) {
                    $data1['Leads'] += $data0['Leads'] ?? 0;
                    $data1['Conversions'] += $data0['Conversions'] ?? 0;
                }
            }
        }

        return $subGroupBy;
    }

    public function get_billing_payment_request($id)
    {
        if (empty($id)) {
            return [];
        }
        $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $data = $mongo->find();
        // $leads = json_decode($data['json_leads'], true);

        $from = '';
        if (isset($data['from']) && !empty($data['from'])) {
            $ts = (array)$data['from'];
            $mil = $ts['milliseconds'];
            if ($mil > 0) {
                $seconds = $mil / 1000;
                $from = date("Y-m-d", $seconds);
            }
        }

        $to = '';
        if (isset($data['to']) && !empty($data['to'])) {
            $ts = (array)$data['to'];
            $mil = $ts['milliseconds'];
            if ($mil > 0) {
                $seconds = $mil / 1000;
                $to = date("Y-m-d", $seconds);
            }
        }

        $data['period'] = $from . ' - ' . $to . ' $' . ($data['total'] ?? 0);

        return $data;
    }

    public function get_billing_payment_request_feed($id)
    {
        $data = AffiliateBillingPaymentRequests::query()->find($id)->toArray();
        $leads = json_decode($data['json_leads'], true);
        return $this->feed_billing_payment_request($data, $leads);
    }

    public function get_billing_payment_requests($where = [])
    {
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $list = $mongo->findMany();

        foreach ($list as &$data) {
            $from = '';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $from = date("Y-m-d", $seconds);
                }
            }

            $to = '';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $to = date("Y-m-d", $seconds);
                }
            }

            $data['id'] = MongoDBObjects::get_id($data);
            $data['period'] = $from . ' - ' . $to . ' $' . $data['total'];
        }

        return $list;
    }

    public function feed_billing_payment_request($payment_request = [], $data = null)
    {

        if ($data == null) {
            $timeframe = $this->request_post('timeframe');
            $payment_request = [
                'adjustment_amount' => $this->request_post('adjustment_amount'),
                'adjustment_description' => $this->request_post('adjustment_description'),
            ];
            $data = $this->_get_feed_billing_payment_request($timeframe);
            //die(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            $from = ((array)$payment_request['from'])['milliseconds'] / 1000;
            $to = ((array)$payment_request['to'])['milliseconds'] / 1000;
            $timeframe = date("m/d/Y", $from) . ' - ' . date("m/d/Y", $to);
        }
        $subGroupBy = $this->_get_feed_group_by_billing_payment_request($data);

        $countries = GeneralHelper::countries();

        // -- build

        $result = [
            'timeframe' => $timeframe,
            'columns' => [],
            'items' => []
        ];

        $c = 0;

        $leads = 0;
        $costs = 0;
        $total = 0;

        foreach ($subGroupBy as $groupKey => $datas) {

            if ($datas['cost'] == 0) {
                continue;
            }

            $datas['cr'] = round($datas['Leads'] > 0 ? (($datas['Conversions'] / $datas['Leads']) * 100) : 0, 2);

            // if CPL
            if (strpos($groupKey, 'EventSchema[cpl]') !== false) {
                //120 Leads Germany CPL 25 = $3000
                $result['items'][] = [
                    'data' => $datas,
                    'leads' => number_format($datas['Leads'], 0, '.', ' ') . ' Leads',
                    'count' => number_format($datas['Leads'], 0, '.', ' ') . ' Leads',
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                    'nomination' => '<strong>CPL. </strong>' . $countries[strtolower($datas['GeoCountryName'])] . ' cr ' . $datas['cr'] . '%'
                ];
            } else if (strpos($groupKey, 'EventSchema[cpa]') !== false) { // if CPA
                //3 FTD IT CPA 985 = $2955
                $result['items'][] = [
                    'data' => $datas,
                    'depositors' => number_format($datas['Conversions'], 0, '.', ' ') . ' Conversions',
                    'count' => number_format($datas['Conversions'], 0, '.', ' ') . ' Conversions',
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                    'nomination' => '<strong>CPA. </strong> ' . $countries[strtolower($datas['GeoCountryName'])] . ' cr ' . $datas['cr'] . '%' .
                        '<div style="color:gray;display:none">' . print_r($datas, true) . '</div>'

                ];
            } else {
                //3 FTD IT CPA 985 = $2955
                $result['items'][] = [
                    'data' => $datas,
                    'depositors' => number_format($datas['Conversions'], 0, '.', ' ') . ' Conversions',
                    'count' => number_format($datas['Conversions'], 0, '.', ' ') . ' Conversions',
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                    'nomination' => '<strong>Unrecognized Schema</strong> ' . $countries[strtolower($datas['GeoCountryName'])] . ' cr ' . $datas['cr'] . '%' .
                        '<div style="color:gray;display:none">' . print_r($datas, true) . '</div>'

                ];
            }

            $leads += $datas['Leads'];
            $costs += $datas['cost'];
            $total += $datas['total'];
        }

        $adjustment_amount = $payment_request['adjustment_amount'] ?? 0;
        $total = $payment_request['total'] ?? ($total + $adjustment_amount);
        if ($adjustment_amount != 0) {
            $result['footer'] = [
                'adjustment_amount' => [
                    'title' => 'Adjustment Amount',
                    'origin' => number_format($adjustment_amount, 0, '.', ' '),
                    'value' => (isset($payment_request['adjustment_description']) && !empty($payment_request['adjustment_description']) ? ' (' . $payment_request['adjustment_description'] . ')' : '')
                ]
            ];

            $result['items'][] = [
                'nomination' => 'Adjustment Amount' . (isset($payment_request['adjustment_description']) && !empty($payment_request['adjustment_description']) ? ' (' . $payment_request['adjustment_description'] . ')' : ''),
                'count' => '',
                'revenue' => '',
                'total' => $adjustment_amount
            ];
        }

        return $result;
    }

    public function feed_billing_payment_requests()
    {
        $is_financial = Gate::allows('role:financial');

        $where = [
            'affiliate' => $this->_affiliateId,
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

        $array = AffiliateBillingPaymentRequests::query()->where('affiliate', '=', $this->_affiliateId)->get()->toArray();

        $result = [];
        foreach ($array as $data) {

            $id = $data['_id'];

            $attrs = [
                'data-id' => $id,
                'data-payment-type' => $data['type'],
                'data-sub-status' => ($data['sub_status'] ?? '')
            ];
            if (!empty($data['sub_status'] ?? '')) {
                $attrs['style'] = 'background-color:#feb';
                $attrs['title'] = 'Payment request is archived';
            }

            $ts = (array)$data['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $from = '-';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $from = date("Y-m-d", $seconds);
                }
            }

            $to = '-';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $to = date("Y-m-d", $seconds);
                }
            }

            $leads = isset($data['leads']) ? $data['leads'] : 0;
            $costs = isset($data['cost']) ? $data['cost'] : 0;
            $total = isset($data['total']) ? $data['total'] : 0;

            // if ($data['type'] == 'prepayment') {
            //     $leads = 'Prepayment';
            // }

            if ($total == 0) { // it must be deleted
                $json_leads = json_decode($data['json_leads'], true);
                $subGroupBy = $this->_get_feed_group_by_billing_payment_request($json_leads);
                foreach ($subGroupBy as $groupKey => $datas) {
                    if ($datas['cost'] == 0) {
                        continue;
                    }
                    $leads += $datas['Leads'];
                    $costs += $datas['cost'];
                    $total += $datas['total'];
                }
            }

            $total_adjust = $adjusted_totals[$id] ?? 0;

            $popover = function ($field_name) use ($data) {
                $changed = (int)$data[$field_name];
                $result_popover = [];
                if ($changed == 1 || $changed == 2) {
                    $changed_date = (array)$data[$field_name . '_changed_date'];
                    $changed_user_id = $data[$field_name . '_changed_user_id'];
                    $changed_user_ip = $data[$field_name . '_changed_user_ip'];
                    $changed_user_ua = $data[$field_name . '_changed_user_ua'];

                    $mil = $changed_date['milliseconds'];
                    $seconds = $mil / 1000;
                    $dt = date("Y-m-d H:i:s", $seconds);

                    $result_popover = [
                        'dt' => $dt,
                        'changed_user' => (!empty($changed_user_id) ? User::query()->find($changed_user_id)->name : ''),
                        'changed_user_ip' => $changed_user_ip,
                        'changed_user_ua' => $changed_user_ua
                    ];
                }
                return $result_popover;
            };

            // status
            if ((int)$data['status'] == 1) {
                $stprotp = ['status' => 'active', 'popover' => $popover('status'), 'title' => 'Approved'];
            } elseif ((int)$data['status'] == 2) {
                $stprotp = ['status' => 'inactive', 'popover' => $popover('status'), 'title' => 'Rejected'];
            } else {
                $stprotp = ['status' => 'processing', 'popover' => '', 'title' => 'Approval: waiting for approval'];
            }

            // affiliate status
            if ((int)($data['affiliate_status'] ?? 0) == 1) {
                $affiliate_stprotp = ['status' => 'active', 'popover' => $popover('affiliate_status'), 'title' => 'Affiliate: Approved'];
            } elseif ((int)($data['affiliate_status'] ?? 0) == 2) {
                $affiliate_stprotp = ['status' => 'inactive', 'popover' => $popover('affiliate_status'), 'title' => 'Affiliate: Rejected'];
            } else {
                $affiliate_stprotp = '';
                if ((int)($data['status'] ?? 0) == 1 && (int)($data['master_status'] ?? 0) !== 1 && (int)($data['final_status'] ?? 0) != 1) {
                    $affiliate_stprotp = ['status' => 'processing', 'popover' => '', 'title' => 'Affiliate: waiting for approval'];
                }
            }

            // master status
            if ((int)($data['master_status'] ?? 0) == 1) {
                $master_stprotp = ['status' => 'active', 'popover' => $popover('master_status'), 'title' => 'Master: Approved'];
            } elseif ((int)($data['master_status'] ?? 0) == 2) {
                $master_stprotp = ['status' => 'inactive', 'popover' => $popover('master_status'), 'title' => 'Master: Rejected'];
            } else {
                $master_stprotp = [];
                // $affiliate_stprotp = '<div class="status processing_highlight">Approval processing</div>';
            }

            // final status
            if ((int)($data['final_status'] ?? 0) == 1) {
                $final_stprotp = ['status' => 'active', 'popover' => $popover('final_status'), 'title' => 'Financial: Approved'];
            } elseif ((int)($data['final_status'] ?? 0) == 2) {
                $final_stprotp = ['status' => 'inactive', 'popover' => $popover('final_status'), 'title' => 'Financial: Rejected'];
            } else {
                $final_stprotp = '';

                if ((int)($data['status'] ?? 0) == 1 && ((int)($data['master_status'] ?? 0) == 1 || (int)($data['affiliate_status'] ?? 0) == 1)) {
                    $final_stprotp = ['status' => 'processing', 'popover' => '', 'title' => 'Financial: waiting for approval'];
                }
            }

            $chargeback = '';
            if (($data['chargeback'] ?? false) == true) {
                $chargeback = 'Chargeback';
            }

            // files
            $fields_files = ['affiliate_invoices', 'master_approve_files', 'final_approve_files'];
            $is_files = false;
            foreach ($fields_files as $field_name) {
                if (isset($data[$field_name]) && count($data[$field_name]) > 0) {
                    $is_files = true;
                }
            }

            $actions = [];
            if ((int)($data['final_status'] ?? 0) == 0) {
                if ((int)$data['status'] == 0) {
                    $actions[] = ['name' => 'approve', 'title' => 'Approve'];
                    $actions[] = ['name' => 'reject', 'title' => 'Reject'];
                }
                if ($is_financial && (int)($data['affiliate_status'] ?? 0) == 0 && (int)($data['master_status'] ?? 0) == 0 && (int)($data['status'] ?? 0) != 2) {
                    $actions[] = ['name' => 'master_approve', 'title' => 'Master Approve'];
                }
                if ($is_financial && ((int)($data['affiliate_status'] ?? 0) == 1 || (int)($data['master_status'] ?? 0) == 1) && (int)($data['status'] ?? 0) == 1) {
                    $actions[] = ['name' => 'final_approve', 'title' => 'Financial Approve'];
                    $actions[] = ['name' => 'final_reject', 'title' => 'Financial Reject'];
                }
            }
            if ((int)($data['final_status'] ?? 0) == 1) {
                if (($data['chargeback'] ?? false) == false) {
                    $actions[] = ['name' => 'real_income', 'title' => 'Real Income'];
                }
            }
            if (!isset($data['sub_status'])) {
                if ((int)($data['status'] ?? 0) == 2 || (int)($data['final_status'] ?? 0) == 2) {
                    $actions[] = ['name' => 'archive_rejected', 'title' => 'Archive'];
                }
            }

            $item = [
                '_id' => $id,
                'attrs' => $attrs,
                'timestamp' => $timestamp,
                'final_status_date_pay' => $data['final_status_date_pay'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? '',
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'data' => $data,
                'is_files' => $is_files,
                'sub_status' => $data['sub_status'] ?? '',
                'stat' => [
                    // 'leads' => isset($data['leads']) ? $data['leads'] : 0, //$leads,
                    'leads' => $leads,
                    'type' => ($data['type'] == 'prepayment' ? 'Prepayment' : 'Payment'),
                    'format_leads' => (is_string($leads) ? $leads : number_format($leads, 0, '.', ' ')),
                    'costs' => $costs,
                    'total' => $total,
                    'format_total' => RenderHelper::format_money($total),
                    'real_income' => ($total_adjust != 0 ? ($total + $total_adjust) : $total),
                    'real_total' => ($total + $total_adjust),
                    'format_real_total' => number_format($total + $total_adjust, 2, '.', ' '),
                    'total_adjust' => $total_adjust,
                ],
                'statuses' => [
                    'status' => $stprotp,
                    'affiliate_status' => $affiliate_stprotp,
                    'master_status' => $master_stprotp,
                    'final_status' => $final_stprotp,
                    'chargeback' => $chargeback
                ],
                'actions' => $actions
            ];

            foreach ($data as $k => $v) {
                if (strpos($k, '_changed_') !== false || strpos($k, 'status') !== false || $k == 'chargeback') {
                    $item[$k] = $v;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    private function is_billing_payment_requests_leads_changed($id)
    {
        // $query = AffiliateBillingPaymentRequests::query()->find($id);
        // $payment_request = $query->first()->toArray();

        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($id),
        ];
        $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        $payment_request = $mongo->find();

        $ts = (array)($payment_request['from'] ?? []);
        $from = '-';
        if (isset($ts['milliseconds'])) {
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $from = date("m/d/Y", $seconds);
        }

        $ts = (array)($payment_request['to'] ?? []);
        $to = '-';
        if (isset($ts['milliseconds'])) {
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $to = date("m/d/Y", $seconds);
        }

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

    public function billing_payment_requests_approve($id): bool
    {
        $model = AffiliateBillingPaymentRequests::query()->find($id);

        if ((($model->type ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $update = [
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'status_changed_user_id' => Auth::id(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 1
        ];

        return $model->update($update);
    }

    public function billing_payment_requests_master_approve($id): bool
    {

        if (!Gate::allows('role:financial')) {
            throw new \Exception('Access Denied');
        }

        $model = AffiliateBillingPaymentRequests::findOrFail($id);

        if ((($model->type ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $update = [
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'status_changed_user_id' => Auth::id(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 1,
            'master_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'master_status_changed_user_id' => Auth::id(),
            'master_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'master_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'master_status' => 1,
            'master_approve_note' => $this->request_post('note'),
            // 'master_approve' => true,
        ];

        StorageHelper::syncFiles('billing_payment_requests', $model, $update, 'master_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        return $model->update($update);
    }

    public function billing_payment_requests_reject($id): bool
    {
        $query = AffiliateBillingPaymentRequests::query()->find($id);
        return $query->update([
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'status_changed_user_id' => Auth::id(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 2
        ]);
    }

    public function billing_payment_requests_final_approve($id): bool
    {

        if (!Gate::allows('role:financial')) {
            throw new \Exception('Access Denied');
        }

        $date_pay = $this->request_post('date_pay');
        /*if (!empty($date_pay))
            $date_pay = GeneralHelper::ToMongoDateTime($date_pay);*/

        $model = AffiliateBillingPaymentRequests::findOrFail($id);

        if ((($model->type ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $payload = [
            'final_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'final_status_changed_user_id' => Auth::id(),
            'final_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'final_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'final_status_date_pay' => GeneralHelper::ToMongoDateTime($date_pay), //new \MongoDB\BSON\UTCDateTime(strtotime($date_pay) * 1000),
            'final_status' => 1,
            // 'type' => $this->request_post('payment_type'),
            'payment_method' => $this->request_post('payment_method'),
            'transaction_id' => $this->request_post('transaction_id'),
        ];

        StorageHelper::syncFiles('billing_payment_requests', $model, $payload, 'final_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $user = Auth::user();
        $affiliate = $this->get_affiliate();
        $subject = 'New Payment Request Financial Approv';
        $message = $subject . ' :';
        $message .= ' Affiliate ' . ($affiliate['token'] ?? '');
        $message .= ', User ' . ($user->name ?? '');
        $message .= ', Total $' . ($model->total ?? 0);

        Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('finance_approved', $message)));

        return $model->update($payload);
    }

    public function billing_payment_requests_final_reject($id): bool
    {
        if (!Gate::allows('role:financial')) {
            throw new \Exception('Access Denied');
        }

        $query = AffiliateBillingPaymentRequests::query()->find($id);

        return $query->update([
            'final_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'final_status_changed_user_id' => Auth::id(),
            'final_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'final_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'final_status' => 2
        ]);
    }

    public function archive_billing_payment_requests(string $id): bool
    {
        $query = AffiliateBillingPaymentRequests::query()->find($id);
        $payment_request = $query->toArray();

        if ($payment_request['status'] != 2 && $payment_request['final_status'] != 2) {
            throw new \Exception('You cannot archive payment request that in not rejected');
        }

        return $query->update([
            'sub_status' => 'archive'
        ]);
    }

    public function billing_payment_requests_real_income($id): bool
    {

        if (!Gate::allows('role:financial')) {
            throw new \Exception('Access Denied');
        }

        $real_income = (float)$this->request_post('real_income'); //income

        $payment_request = AffiliateBillingPaymentRequests::query()->find($id)->toArray();

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
            $insert['affiliate'] = $this->_affiliateId;
            $insert['payment_request'] = $id;
            $insert['amount'] = $delta;
            $insert['description'] = $description;

            $model = new AffiliateBillingAdjustments();
            $model->fill($insert);
            $model->save();
        }
        return true;
    }

    public function save_billing_payment_requests()
    {
        $payment_request_type = $this->request_post('payment_request_type');

        $leads = 0;
        $cost = 0;
        $total = 0;
        $data = [];

        $time_range = ['start' => '', 'end' => ''];

        if (empty($payment_request_type) || $payment_request_type == 'payment') {
            $timeframe = $this->request_post('timeframe');
            $time_range = $this->buildTimestamp($timeframe);

            $id = false;

            $get_data_info = function ($data) {
                $id = (array)$data['_id'];
                $id = $id['oid'];

                $from = 'None';
                if (isset($data['from']) && !empty($data['from'])) {
                    $ts = (array)$data['from'];
                    $mil = $ts['milliseconds'];
                    if ($mil > 0) {
                        $seconds = $mil / 1000;
                        $from = date("Y-m-d", $seconds);
                    }
                }

                $to = 'None';
                if (isset($data['to']) && !empty($data['to'])) {
                    $ts = (array)$data['to'];
                    $mil = $ts['milliseconds'];
                    if ($mil > 0) {
                        $seconds = $mil / 1000;
                        $to = date("Y-m-d", $seconds);
                    }
                }

                $ts = (array)$data['timestamp'];
                $mil = $ts['milliseconds'];
                $seconds = $mil / 1000;
                $timestamp = date("Y-m-d  h:i:s", $seconds);

                $user = User::query()->find($data['created_by'])->name;

                return [$id, $from, $to, $timestamp, $user];
            };

            // the same period
            $where = [
                'affiliate' => $this->_affiliateId,
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
                        throw new \Exception('Payment request for such time [' . $from . ' - ' . $to . '] frame is already exist. Created by ' . ($user['name'] ?? '') . ' at ' . $timestamp);
                    }
                }
            }

            // cross period
            $where = [
                'affiliate' => $this->_affiliateId,
                'status' => ['$ne' => 2],
                'affiliate_status' => ['$ne' => 2],
                'final_status' => ['$ne' => 2],
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
                throw new \Exception('The period overlaps with the existing approved one [' . $from . ' - ' . $to . ']. Created by ' . ($user['name'] ?? '') . ' at ' . $timestamp);
            }

            $data = $this->_get_feed_billing_payment_request($timeframe);

            $subGroupBy = $this->_get_feed_group_by_billing_payment_request($data);

            foreach ($subGroupBy as $groupKey => $datas) {
                $leads += $datas['Leads'];
                $cost += $datas['cost'];
                $total += $datas['total'];
            }
        } else {
            $total = $cost = $this->request_post('amount', 0);
        }


        // if ($id != false) {
        //     $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
        //     $update = [
        //         'count' => count($data),
        //         'json_leads' => json_encode($data)
        //     ];
        //     $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
        //     $mongo->update($update);
        // } else
        {

            $adjustment_amount = (int)$this->request_post('adjustment_amount', 0);
            $adjustment_description = $this->request_post('adjustment_description', '');
            $total += $adjustment_amount;

            $insert = [
                'affiliate' => $this->_affiliateId,
                'created_by' => Auth::id(),
                'type' => $payment_request_type,
                'from' => $time_range['start'],
                'to' => $time_range['end'],
                'status' => 0,
                // 'count' => count($data),
                'leads' => $leads,
                'cost' => $cost,
                'adjustment_amount' => $adjustment_amount,
                'adjustment_description' => $adjustment_description,
                'total' => $total,
                'json_leads' => json_encode($data)
            ];

            $var = date("Y-m-d H:i:s");
            $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

            $model = new AffiliateBillingPaymentRequests();
            $model->fill($insert);
            $model->save();
            // $id = $model->id;
        }
        return true;
    }

    public function billing_payment_requests_is_approve()
    {
        $timeframe = $this->request_post('timeframe');
        $time_range = $this->buildTimestamp($timeframe);

        $query = AffiliateBillingPaymentRequests::query()
            ->where('affiliate', '=', $this->_affiliateId)
            ->where('from', '=', $time_range['start'])
            ->where('to', '=', $time_range['end']);

        $query_data = $query->first()->toArray();

        return isset($query_data) && isset($query_data['_id']);
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
            $billing_entity = $this->get_billing_entity($billing_entity_id);
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

    public function billing_payment_requests_files($id): array
    {
        if (!Gate::allows('role:financial')) {
            throw new \Exception('Access Denied');
        }

        $data = AffiliateBillingPaymentRequests::query()->find($id);
        if ($data == null) {
            return [];
        }
        $billing = $data->toArray();

        $collection_files = [
            'affiliate_invoices' => 'Affiliate Invoices',
            'master_approve_files' => 'Master Approve Files',
            'final_approve_files' => 'Financial Approve Files'
        ];

        $storage = StorageWrapper::instance();

        $result = [];
        foreach ($collection_files as $field_name => $title) {

            $files = [];
            if (isset($billing[$field_name]) && count($billing[$field_name]) > 0) {
                $files = $storage->get_files($billing[$field_name]);
            }

            if (count($files) > 0) {
                $result[] = $files;
            }
        }

        return $result;
    }

    ///// ---- Completed Transactions ---- //////

    public function feed_billing_completed_transactions(): array
    {
        $where = [
            'affiliate' => $this->_affiliateId,
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

        $array = AffiliateBillingPaymentRequests::query()
            ->where('affiliate', '=', $this->_affiliateId)
            ->where('final_status', '=', 1)
            ->get()->toArray();

        $result = [];
        foreach ($array as $data) {

            $id = $data['_id'];

            $ts = (array)$data['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $from = '-';
            if (isset($data['from']) && !empty($data['from'])) {
                $ts = (array)$data['from'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $from = date("Y-m-d", $seconds);
                }
            }

            $to = '-';
            if (isset($data['to']) && !empty($data['to'])) {
                $ts = (array)$data['to'];
                $mil = $ts['milliseconds'];
                if ($mil > 0) {
                    $seconds = $mil / 1000;
                    $to = date("Y-m-d", $seconds);
                }
            }

            $leads = isset($data['leads']) ? $data['leads'] : 0;
            $costs = isset($data['cost']) ? $data['cost'] : 0;
            $total = isset($data['total']) ? $data['total'] : 0;
            $adjustment_amount = isset($data['adjustment_amount']) ? $data['adjustment_amount'] : 0;

            if ($data['type'] == 'prepayment') {
                $leads = 'Prepayment';
            }

            if ($total == 0) { // it must be deleted
                $json_leads = json_decode($data['json_leads'], true);
                $subGroupBy = $this->_get_feed_group_by_billing_payment_request($json_leads);
                foreach ($subGroupBy as $groupKey => $datas) {
                    if ($datas['cost'] == 0) {
                        continue;
                    }
                    $leads += $datas['Leads'];
                    $costs += $datas['cost'];
                    $total += $datas['total'];
                }
            }

            $total_adjust = $adjusted_totals[$id] ?? 0;

            $popover = function ($field_name) use ($data) {
                $changed = (int)$data[$field_name];
                $result_popover = [];
                if ($changed == 1 || $changed == 2) {
                    $changed_date = (array)$data[$field_name . '_changed_date'];
                    $changed_user_id = $data[$field_name . '_changed_user_id'];
                    $changed_user_ip = $data[$field_name . '_changed_user_ip'];
                    $changed_user_ua = $data[$field_name . '_changed_user_ua'];

                    $mil = $changed_date['milliseconds'];
                    $seconds = $mil / 1000;
                    $dt = date("Y-m-d H:i:s", $seconds);

                    $result_popover = [
                        'dt' => $dt,
                        'changed_user' => (!empty($changed_user_id) ? User::query()->find($changed_user_id)->name : ''),
                        'changed_user_ip' => $changed_user_ip,
                        'changed_user_ua' => $changed_user_ua
                    ];
                }
                return $result_popover;
            };

            // status
            $status = [];
            if ((int)$data['status'] == 1) {
                $status = ['status' => 'active', 'popover' => $popover('status'), 'title' => 'Approved'];
            } elseif ((int)$data['status'] == 2) {
                $status = ['status' => 'inactive', 'popover' => $popover('status'), 'title' => 'Rejected'];
            } else {
                $status = ['status' => 'processing', 'popover' => $popover('status'), 'title' => 'Approval: waiting for approval'];
            }

            // affiliate status
            $affiliate_status = [];
            if ((int)($data['affiliate_status'] ?? 0) == 1) {
                $affiliate_status = ['status' => 'active', 'popover' => $popover('affiliate_status'), 'title' => 'Affiliate: Approved'];
            } elseif ((int)($data['affiliate_status'] ?? 0) == 2) {
                $affiliate_status = ['status' => 'inactive', 'popover' => $popover('affiliate_status'), 'title' => 'Affiliate: Rejected'];
            } else {
                if ((int)($data['status'] ?? 0) == 1 && (int)($data['master_status'] ?? 0) != 1 && (int)($data['final_status'] ?? 0) != 1) {
                    $affiliate_status = ['status' => 'processing', 'title' => 'Affiliate: waiting for approval'];
                }
            }

            // master status
            $master_status = [];
            if ((int)($data['master_status'] ?? 0) == 1) {
                $master_status = ['status' => 'active', 'popover' => $popover('master_status'), 'title' => 'Master: Approved'];
            } elseif ((int)($data['master_status'] ?? 0) == 2) {
                $master_status = ['status' => 'inactive', 'popover' => $popover('master_status'), 'title' => 'Master: Rejected'];
            } else {
                $master_status = [];
                // $affiliate_stprotp = '<div class="status processing_highlight">Approval processing</div>';
            }

            // final status
            if ((int)($data['final_status'] ?? 0) == 1) {
                $final_status = ['status' => 'active', 'popover' => $popover('final_status'), 'title' => 'Financial: Approved'];
            } elseif ((int)($data['final_status'] ?? 0) == 2) {
                $final_status = ['status' => 'inactive', 'popover' => $popover('final_status'), 'title' => 'Financial: Rejected'];
            } else {
                $final_status = [];

                if ((int)($data['status'] ?? 0) == 1 && ((int)($data['master_status'] ?? 0) == 1 || (int)($data['affiliate_status'] ?? 0) == 1)) {
                    $final_status = ['status' => 'processing', 'title' => 'Financial: waiting for approval'];
                }
            }

            $chargeback_status = [];
            if (($data['chargeback'] ?? false) == true) {
                $chargeback_status = ['status' => 'inactive', 'title' => 'Chargeback'];
            }

            $real_income = 0;
            if ($total_adjust != 0) {
                $real_income = $total + $total_adjust;
            }

            // files
            $fields_files = ['affiliate_invoices', 'master_approve_files', 'final_approve_files'];
            $is_files = false;
            foreach ($fields_files as $field_name) {
                if (isset($data[$field_name]) && count($data[$field_name]) > 0) {
                    $is_files = true;
                }
            }

            $result[] = [
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'timestamp' => $timestamp,
                'final_status_date_pay' => $data['final_status_date_pay'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? '',
                'data' => $data,
                'is_files' => $is_files,
                'statuses' => [
                    'status' => $status,
                    'affiliate_status' => $affiliate_status,
                    'master_status' => $master_status,
                    'final_status' => $final_status,
                    'chargeback_status' => $chargeback_status,
                ],
                'stat' => [
                    'count' => count($data),
                    'leads' => (is_string($leads) ? $leads : number_format($leads, 0, '.', ' ')),
                    'type' => ($data['type'] == 'prepayment' ? 'Prepayment' : 'Payment'),
                    'adjustment_amount' => [
                        'value' => $adjustment_amount, //str_replace('$-', '-$', '$' . number_format($adjustment_amount, 0, '.', ' ')),
                        'format_value'  => RenderHelper::format_money($adjustment_amount)
                    ],
                    'total_adjust' => [
                        'value' => $total_adjust,
                        'format_value' => RenderHelper::format_money($total_adjust)
                    ],
                    'total' => [
                        'value' => $total,
                        'format_value' => RenderHelper::format_money($total)
                    ],
                    'real_income' => [
                        'value' => $real_income,
                        'format_value' => RenderHelper::format_money($real_income)
                    ]
                ]
            ];
        }


        return $result;
    }

    ///// ---- General Balance ---- //////

    public function billing_general_balances_buildParameterArray()
    {

        $formula = array();
        $array = array();

        $pivots = [
            // 'Affiliate',
            '_id',
            /*'GeoCountryName',
            'isCPL',*/
        ];

        $metrics = [
            //'leads',
            'cost',
            /*'revenue',
            'cr'*/
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $_cost = array('cost' => [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                // $cost = 0.0;
                $cost = __AffiliatePayout__;
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

    private function _get_data_general_balances($timeframe)
    {
        // -- query
        $time = $this->buildTimestamp($timeframe);
        $query = $this->billing_general_balances_buildParameterArray();

        $conditions = [
            'AffiliateId' => [$this->_affiliateId],
            // 'test_lead' => 0
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachMarketingFormula($query_data, $query['formula']);

        return $data;
    }

    public function feed_billing_balances_log(int $page, int $count_in_page = 60)
    {
        $where = ['affiliate' => $this->_affiliateId];
        $mongo = new MongoDBObjects('marketing_billings_log', $where);
        $count = $mongo->count();
        $list = $mongo->findMany([
            'sort' => ['timestamp' => -1],
            'skip' => (($page - 1) * $count_in_page),
            'limit' => $count_in_page
        ]);

        $result = [];

        foreach ($list as $data) {
            $date = RenderHelper::format_datetime($data['timestamp'], 'Y-m-d');
            $time = RenderHelper::format_datetime($data['timestamp'], 'Y-m-d H:i:s');
            $result[] = [
                '_id' => $data['_id'],
                'dt_time' => $time,
                'dt' => $date,
                'balance' => $data['balance'],
                'real_balance' => $data['real_balance'] ?? ''
            ];
        }

        return ['count' => $count, 'items' => $result];
    }

    public function update_billing_balances_log(string $logId): bool
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($logId)];
        $mongo = new MongoDBObjects('marketing_billings_log', $where);
        $log = $mongo->find();
        if ($mongo->update($this->_payload)) {
            $this->_payload['affiliate'] = $this->_affiliateId;
            $dt = date('Y-m-d', ((array)$log['timestamp'])['milliseconds'] / 1000);
            $description = 'Changed real balance by date ' . $dt . ': $' . $this->_payload['real_balance'];
            HistoryDB::add(HistoryDBAction::Update, 'billings_log', $this->_payload, $logId, 'affiliate', [], null, $description);
            return true;
        }
        return false;
    }

    public function feed_billing_general_balances(): array
    {
        $timeframe = date('m/d/Y', -2147483648) . ' - ' .  date('m/d/Y');

        $is_financial = Gate::allows('role:financial');

        $leads = $this->_get_data_general_balances($timeframe);

        $cost = 0;
        foreach ($leads as $lead) {
            $cost += $lead['cost'];
        }
        // $cost *= -1;
        $chargebacks_balans = $this->get_billing_chargebacks_balans();
        $chargebacks_balans = $chargebacks_balans['total'];

        $endpoint = $this->get_affiliate();

        // $credit_total = isset($endpoint['financial_limit']) && (int)$endpoint['financial_limit'] > 0 ? (int)$endpoint['financial_limit'] : 0;
        // $action_on_negative_balance = isset($endpoint['action_on_negative_balance']) && !empty($endpoint['action_on_negative_balance']) ? $endpoint['action_on_negative_balance'] : '';
        $action_on_negative_balance = 0;
        $credit_total = 0;

        // payment request
        $list = AffiliateBillingPaymentRequests::query()->where('affiliate', '=', $this->_affiliateId)->where('final_status', '=', 1)->get()->toArray();

        $payment_request_total = ['undefined' => 0, 'payment' => 0, 'prepayment' => 0];

        $adjustment_amount = 0;
        $adjustments_balans = $this->get_billing_adjustments_balans();
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
                    $adjustment_amount += isset($datas['adjustment_amount']) ? $datas['adjustment_amount'] : 0;
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

        $color = '';
        if ($result > 0) {
            $color = 'green';
        } elseif ($result < 0) {
            $color = 'red';
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

        $manual_status = false;
        if ($result + $credit_total > 0) {
            $manual_status = $endpoint['billing_manual_status'] ?? 'active';
        }

        $manual_statuses = [
            'active' => 'Active',
            'collection' => 'Collection',
        ];

        $response = [
            'color' => $color,
            'balance' => round($result, 0),
            'format_balance' => RenderHelper::format_money($result),
            'status' => $status,
            'manual_status' => $manual_status,
            'manual_statuses' => $manual_statuses,
            'desc' => $desc,
            'history' => $this->get_array_history_billing_general_balances()
        ];

        // if ($is_financial) {
        //     $html .= $this->_render_settings_billing_general_balances($action_on_negative_balance);
        // }

        // if ($is_financial && $action_on_negative_balance == 'stop' && $result < 0) { // || $credit_total > 0)
        //     $html .= $this->_render_creadit_billing_general_balances($credit_total);
        // }

        return $response;
    }

    public function get_history_billing_general_balances($collection = '', $limit = 20)
    {
        $token = $this->_affiliateId;

        $collections = [
            'marketing_affiliate_billing_entities',
            'marketing_affiliate_billing_chargebacks',
            'marketing_affiliate_billing_adjustments',
            'marketing_affiliate_billing_payment_methods',
            'marketing_affiliate_billing_payment_requests',
            'marketing_billings_log'
        ];

        $where = [
            '$or' => [
                ['main_foreign_key' => new \MongoDB\BSON\ObjectId($token)],
                ['primary_key' => new \MongoDB\BSON\ObjectId($token)],
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
                            ['collection' => 'marketing_affiliate_billing_payment_methods'],
                            ['diff.status' => 1]
                        ]
                    ]
                ]
            ];
        } elseif ($collection == 'marketing_affiliate_billing_payment_methods') {
            $where['$and'] = array_merge($where['$and'], [
                ['collection' => $collection],
                ['diff.status' => 1]
            ]);
        } elseif (in_array($collection, $collections)) {
            $where['$and'][] = ['collection' => $collection];
        } else {
            $where['$and'][] = ['collection' => ['$in' => $collections]];
        }

        $mongo = new MongoDBObjects('history', $where);
        return $mongo->findMany([
            'sort' => ['timestamp' => -1],
            'limit' => $limit
        ]);
    }

    public function get_array_history_billing_general_balances(): array
    {
        $collection = $this->request_post('collection', '');
        $limit = $this->request_post('limit', 20);
        // $see_more = ($this->request_post('see_more', 'true') == 'true');

        $limit = min((int)$limit, 1000);

        $history_logs = $this->get_history_billing_general_balances($collection, $limit);

        $collections = [
            'marketing_affiliate_billing_entities' => 'Billing Entities',
            'marketing_affiliate_billing_payment_methods' => 'Payment Methods',
            'marketing_affiliate_billing_payment_requests' => 'Payment Requests',
            'marketing_affiliate_billing_adjustments' => 'Adjustments',
            'marketing_affiliate_billing_chargebacks' => 'Chargebacks',
            'marketing_billings_log' => 'Billings Log'
        ];

        $rcollections = array_map(function ($key, $item) {
            return ['value' => $key, 'label' => $item];
        }, array_keys($collections), $collections);
        $response = [
            'collections' => $rcollections,
            'items' => []
        ];

        if (isset($history_logs) && count($history_logs) > 0) {

            $action = [
                'INSERT' => 'table-success',
                'UPDATE' => 'table-warning',
                'DELETE' => 'table-danger',
            ];

            $payment_methods = $this->get_payment_methods();

            foreach ($payment_methods as &$pm) {
                if ($pm['payment_method'] == 'wire') {
                    $pm['title'] = strtoupper($pm['payment_method'] . ' - ' . $pm['currency_code']) . ' - ' . $pm['bank_name'];
                } else {
                    $pm['title'] = strtoupper($pm['payment_method'] . ' - ' . $pm['currency_crypto_code']);
                }
            }

            foreach ($history_logs as $history) {

                $dt = date('Y-m-d H:i:s', ((array)$history['timestamp'])['milliseconds'] / 1000);

                $changed_by = (array)$history['action_by'];
                $changed_by = $changed_by['oid'];

                $changed_by = User::query()->find($changed_by)->name;

                $data = '';
                $diff = $history['action'] == 'INSERT' || $history['action'] == 'DELETE' ? $history['data'] : $history['diff'];
                switch ($history['collection']) {
                    case 'marketing_billings_log': {
                            if (isset($diff['real_balance'])) {
                                $data .= $history['description'] ?? 'Changed Real Balance: $' . $diff['real_balance'];
                            }
                            break;
                        }
                    case 'marketing_affiliate_billing_entities': {
                            if (isset($diff['company_legal_name'])) {
                                $data .= 'Company Legal Name: ' . $diff['company_legal_name'];
                            }
                            if (isset($diff['country_code'])) {
                                $countries = GeneralHelper::countries();
                                $data .= (empty($data) ? '' : ', ') . 'Company Country: ' . ($countries[strtolower($diff['country_code'])] ?? '');
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
                    case 'marketing_affiliate_billing_chargebacks': {
                            // if (isset($diff['type'])) {
                            //     $data .= 'Type: ' . $diff['type'];
                            // }
                            if (isset($diff['payment_method'])) {
                                $pm = $payment_methods[$diff['payment_method']];
                                $data .= (empty($data) ? '' : ', ') . 'Payment Method: ' . $pm['title'];
                            }
                            if (isset($diff['amount'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Sum: ' . RenderHelper::format_money($diff['amount']);
                            }
                            if (isset($diff['screenshots'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Screenshots';
                            }
                            break;
                        }
                    case 'marketing_affiliate_billing_adjustments': {
                            if (isset($diff['amount'])) {
                                $data .= 'Amount: ' . RenderHelper::format_money($diff['amount']);
                            }
                            if (isset($diff['description'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Description: ' . $diff['description'];
                            }
                            break;
                        }
                    case 'marketing_affiliate_billing_payment_methods': {
                            if (isset($diff['status']) && (int)$diff['status'] == 1) {
                                $pk = (array)$history['primary_key'];
                                $pk = $pk['oid'];
                                $pm = $payment_methods[$pk];
                                $data = 'Changed Payment Method on ' . $pm['title'];
                            }
                            break;
                        }
                    case 'marketing_affiliate_billing_payment_requests': {
                            $status = [0 => 'Approval processing', 1 => 'Approved', 2 => 'Rejected'];
                            if (isset($diff['from'])) {
                                $from = ((array)$diff['from']);
                                if (isset($from['milliseconds'])) {
                                    $_dt = date('Y-m-d', $from['milliseconds'] / 1000);
                                    $data .= (empty($data) ? '' : ', ') . 'From: ' . $_dt;
                                }
                            }
                            if (isset($diff['to'])) {
                                $to = ((array)$diff['to']);
                                if (isset($to['milliseconds'])) {
                                    $_dt = date('Y-m-d', ((array)$diff['to'])['milliseconds'] / 1000);
                                    $data .= (empty($data) ? '' : ', ') . 'To: ' . $_dt;
                                }
                            }
                            if (isset($diff['status'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Status: ' . $status[$diff['status']];
                            }
                            if (isset($diff['master_status'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Master status: ' . $status[$diff['master_status']];
                            }
                            if (isset($diff['final_status'])) {
                                $data .= (empty($data) ? '' : ', ') . 'Finance status: ' . $status[$diff['final_status']];
                            }
                            // $data = json_encode($diff);
                            break;
                        }
                }

                if (!empty($data)) {
                    $response['items'][] = [
                        'action' => [
                            'value' => $history['action'],
                            'title' => $action[$history['action']],
                        ],
                        'id' => $history['_id'],
                        // 'collection' => $history['collection'],
                        'type' => [
                            'value' => $history['collection'],
                            'title' => $collections[$history['collection']]
                        ],
                        'dt' => $dt,
                        'collections_name' => $collections[$history['collection']],
                        'changed_by' => $changed_by,
                        'diff' => $history['diff'],
                        'data' => $data
                    ];
                }
            }
        }
        return $response;
    }

    public function crg_deal_details_buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            // 'Affiliate',
            '_id',
            'GeoCountryName',
            'UserLanguage',

            'status',
            // 'hit_the_redirect',
        ];

        $metrics = [
            'leads',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        foreach ($metrics as $metrics) {

            if ($metrics == 'leads') {
                $array['Leads'] = array('Leads' => [
                    'type' => 'count',
                    'formula' => '
                       //if ( __EventTimeStamp__ >= ' . $this->start . ' && __EventTimeStamp__ <= ' . $this->end . ' ) {
						if ( strtoupper(__(string)EventType__) == "LEAD" || strtoupper(__(string)EventType__) == "POSTBACK" ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }

    private function _get_feed_crg_deal_details($timeframe)
    {
        // -- query
        $time = $this->buildTimestamp($timeframe);
        $query = $this->crg_deal_details_buildParameterArray();

        $conditions = [
            'AffiliateId' => [$this->_affiliateId],
            // 'test_lead' => 0,
        ];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'mleads', $query['query'], $condition);
        $query_data = $queryMongo->queryMongo();

        $data = QueryHelper::attachMarketingFormula($query_data, $query['formula']);

        return $data;
    }

    function _feed_crg_deal_details($percentage_id, $timeframe)
    {
        return [];
    }

    function _export_crg_deduction_report($percentage_id, $timeframe)
    {
    }

    function _export_crg_full_deduction_report($timeframe)
    {
    }

    private function _export_csv($filename, $header, $data)
    {
        $delimiter = ',';

        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        // clean output buffer
        ob_end_clean();

        $handle = fopen('php://output', 'w');

        fputcsv($handle, array_values($header), $delimiter);

        foreach ($data as $datas) {
            $row = [];
            foreach ($header as $key) {
                $row[$key] = $datas[$key];
            }
            fputcsv($handle, $row, $delimiter);
        }

        fclose($handle);

        // flush buffer
        ob_flush();

        // use exit to get rid of unexpected output afterward
        exit();
    }
}
