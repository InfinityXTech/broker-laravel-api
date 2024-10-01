<?php

namespace App\Classes\Masters;

use App\Models\User;
use App\Models\Master;
use App\Helpers\QueryHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Classes\StorageWrapper;
use App\Models\PlatformAccounts;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\SlackNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Models\Masters\MasterBillingAdjustment;
use App\Models\Masters\MasterBillingPaymentMethod;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Models\Masters\MasterBillingPaymentRequest;
use App\Models\TrafficEndpoints\TrafficEndpointBillingAdjustments;

class PaymentRequests
{
    private string $masterId;
    private $master;

    private $_payload = [];

    private $request;

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

    public function __construct(string $masterId, array $payload = [])
    {
        $this->masterId = $masterId;
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

    private function request_get($name, $default = null)
    {
        return $this->_payload[$name] ?? $default;
    }
    private function request_post($name, $default = null)
    {
        return $this->_payload[$name] ?? $default;
    }

    private function get_master()
    {
        if (isset($this->master)) {
            return $this->master;
        }

        $where = ['_id' => new \MongoDB\BSON\ObjectId($this->masterId)];

        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('masters');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('masters[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        }
        $mongo = new MongoDBObjects('Masters', $where);
        $this->master = $mongo->find();
        return $this->master;
    }

    public function feed(bool $only_completed): Collection
    {
        $where = [
            'master' => $this->masterId,
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

        $where = ['master' => $this->masterId];
        if ($only_completed) {
            $where['final_status'] = 1;
        }
        // $collection = MasterBillingPaymentRequest::where($where)->get();
        $collection = MasterBillingPaymentRequest::query()
            ->where($where)
            ->get()
            ->sortByDesc(function ($item, $key) {
                $a = (array)($item->timestamp ?? []);
                return intval($a['milliseconds'] ?? 0);
            })
            ->values();
        // ->toArray();

        $is_financial = Gate::allows('role:financial'); //permissionsManagement::is_current_user_role('financial');

        foreach ($collection as &$item) {
            // $item->type = ($item['type'] == 'prepayment' ? 'Prepayment' : 'Payment');
            $item->total_fee = InvoiceDocument::get_fee_value($item->total, $item->payment_fee);
            $item->total_adjust = $adjusted_totals[$item->_id] ?? 0;

            $actions = [];
            if ((int)($item->final_status ?? 0) == 0) {
                if ((int)($item->status ?? 0) == 0) {
                    $actions[] = ['name' => 'approve', 'title' => 'Approve'];
                    $actions[] = ['name' => 'reject', 'title' => 'Reject'];
                }
                if ($is_financial && (int)($item->affiliate_status ?? 0) == 0 && (int)($item->master_status ?? 0) == 0 && (int)($item->status ?? 0) != 2) {
                    $actions[] = ['name' => 'master_approve', 'title' => 'Master Approve'];
                }
                if ($is_financial && ((int)($item->affiliate_status ?? 0) == 1 || (int)($item->master_status ?? 0) == 1) && (int)($item->status ?? 0) == 1) {
                    $actions[] = ['name' => 'final_approve', 'title' => 'Financial Approve'];
                    $actions[] = ['name' => 'final_reject', 'title' => 'Financial Reject'];
                }
            }
            if ((int)($item->final_status ?? 0) == 1) {
                if (($item->chargeback ?? false) == false) {
                    $actions[] = ['name' => 'real_income', 'title' => 'Real Income'];
                }
            }
            if (!isset($item->sub_status)) {
                if ((int)($item->status ?? 0) == 2 || (int)($item->final_status ?? 0) == 2) {
                    $actions[] = ['name' => 'archive_rejected', 'title' => 'Archive'];
                }
            }

            $item->actions = $actions;
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
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 1
        ];

        return $mongo->update($update);
    }

    public function master_approve(string $id, array $payload)
    {

        $model = MasterBillingPaymentRequest::findOrFail($id);

        if ($this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('Youed');
        }

        $payload = [
            'status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'status' => 1,
            'master_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'master_status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'master_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'master_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'master_status' => 1,
            'master_approve_note' => $payload['description'] ?? '',
            // 'master_approve' => true,
        ];

        StorageHelper::syncFiles('billing_payment_requests', $model, $payload, 'master_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
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

    public function fin_approve(string $id, array $payload): bool
    {
        $date_pay = $payload['final_status_date_pay'];

        $model = MasterBillingPaymentRequest::findOrFail($id);

        if ((($model->type ?? '') != 'prepayment') && $this->is_billing_payment_requests_leads_changed($id)) {
            throw new \Exception('You cannot approve this period, Leads have been changed');
        }

        $payload['final_status_changed_date'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
        $payload['final_status_changed_user_id'] = GeneralHelper::get_current_user_token();
        $payload['final_status_changed_user_ip'] = GeneralHelper::get_user_ip();
        $payload['final_status_changed_user_ua'] = $_SERVER["HTTP_USER_AGENT"];
        $payload['final_status_date_pay'] = GeneralHelper::ToMongoDateTime($date_pay);
        $payload['final_status'] = 1;
        $payload['payment_method'] = $payload['payment_method'];

        StorageHelper::syncFiles('billing_payment_requests', $model, $payload, 'final_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $user = Auth::user();
        $master = $this->get_master();
        $subject = 'New Payment Request Financial Approv';
        $message = $subject . ' :';
        $message .= ' Master ' . ($master['token'] ?? '');
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

    public function real_income(string $id, array $payload): bool
    {
        $real_income = (float)($payload['real_income'] ?? 0);

        $payment_request = MasterBillingPaymentRequest::query()->find($id)->toArray();

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
            $insert['master'] = $this->masterId;
            $insert['payment_request'] = $id;
            $insert['amount'] = $delta;
            $insert['description'] = $description;

            $model = new MasterBillingAdjustment();
            $model->fill($insert);
            return $model->save();
        }
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

    // public function real_income(string $id, array $payload): bool
    // {
    //     $real_income = (float)$payload['income'];

    //     $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
    //     $mongo = new MongoDBObjects($this->collections['billing_payment_requests'], $where);
    //     $payment_request = $mongo->find();

    //     $where = ['payment_request' => $id];
    //     $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $where);
    //     $adjusted = $mongo->aggregate([
    //         'group' => [
    //             'total' => ['$sum' => '$amount']
    //         ]
    //     ], true);
    //     $adjusted_income = $adjusted['total'] ?? 0;
    //     $expected_income = $payment_request['total'];

    //     $delta = round($real_income - $expected_income - $adjusted_income, 2);

    //     if ($delta != 0) {
    //         $ts = (array)$payment_request['timestamp'];
    //         $mil = $ts['milliseconds'];
    //         $seconds = $mil / 1000;
    //         $timestamp = date("Y-m-d H:i:s", $seconds);

    //         $description = 'Real income for payment request ' . $timestamp . ' has been adjusted.';
    //         if (isset($adjusted) && isset($adjusted['total'])) {
    //             $description .= ' Last Real Income: ' . ($expected_income + $adjusted_income) . '$ Current Real Income: ' . $real_income . '$';
    //         } else {
    //             $description .= ' Expected Income: ' . $expected_income . '$ Current Real Income: ' . $real_income . '$';
    //         }

    //         $insert = array();
    //         $insert['master'] = $this->masterId;
    //         $insert['payment_request'] = $id;
    //         $insert['amount'] = $delta;
    //         $insert['description'] = $description;

    //         $mongo = new MongoDBObjects($this->collections['billing_adjustments'], $insert);
    //         $mongo->insert();
    //     }
    //     return true;
    // }

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

    private function prepare_files(array $files, string $pre): array
    {
        $attachments = [];
        foreach ($files as $fileId) {
            try {
                $info = StorageWrapper::get_file($fileId);
                if ($info === false) {
                    throw new \Exception('File ' . $fileId . ' is not found');
                }
                // $tmpfname = tempnam("/tmp", $fileId);
                // if (file_exists($tmpfname)) {
                //     unlink($tmpfname);
                // }
                // StorageWrapper::download_to_file_path($fileId, $tmpfname);

                $content = StorageWrapper::get_content($fileId);
                if ($content === false) {
                    throw new \Exception('File ' . $fileId . ' is not found');
                }

                $tmpfname = tempnam("/tmp", $fileId);
                if (file_exists($tmpfname)) {
                    unlink($tmpfname);
                }

                $handle = fopen($tmpfname, "w");
                try {
                    fwrite($handle, $content);
                } finally {
                    fclose($handle);
                }
                // Log::info("file [" . $fileId . "]: [" . print_r($info, true) . "], " . $tmpfname . ", \n " . $content);
                $attachments[] = ['file_path' => $tmpfname, 'file_name' => $pre . $info['original_file_name']];
            } catch (\Exception $ex) {
                if (file_exists($tmpfname)) {
                    unlink($tmpfname);
                }
                Log::error("file [" . $fileId . "]: [" . print_r($info, true) . "], " . $tmpfname . ", \n " . $ex->getMessage());
            }
        }
        // GeneralHelper::PrintR($attachments);
        // die();
        return $attachments;
    }

    private function get_settings_model(): ?Model
    {
        $model = PlatformAccounts::query()->first();
        if (!$model) {
            $model = new PlatformAccounts();
            $insert = [
                'clientId' => ClientHelper::clientId(),
                'cdn_url' => '',
                'marketing_suite_domain_url' => '',
                'marketing_suite_tracking_url' => '',
                'subscribers' => []
            ];
            $model->fill($insert);
            $model->save();
        }
        return $model;
    }

    public function send_email_billing_payment_requests(string $payment_request_id, array $leads)
    {
        $model = MasterBillingPaymentRequest::findOrFail($payment_request_id);
        $master = Master::findOrFail($model->master);

        $settings_model = $this->get_settings_model();
        $subscribers = ($settings_model->subscribers ?? [])['finance_email_subscribers'] ?? [];

        $client = ClientHelper::clientConfig();

        if (!empty($subscribers)) {

            // $users = User::all(['account_email'])->whereIn('_id', array_map(fn ($item) => new ObjectId($item), $subscribers));
            // if ($users) {
            //     $users = $users->toArray();
            // }

            $emails = array_unique($subscribers);
            // $emails = ['girman.evg@gmail.com'];

            // $emails = array_unique(array_map(fn ($item) => $item['account_email'], $users ?? []));

            $title = $client['nickname'] . ' - ' .
                $payment_request_id . ' - ' .
                ($model->type == 'payment' ? 'Postpayment' : 'Prepayment') . ' - ' .
                ' Master #' . $master['token'];

            $body = '';
            $_amount = 0;
            // GeneralHelper::PrintR($model->toArray());
            if ($model->type == 'payment' && count($leads) > 0) {
                $style = 'style="border: 1px solid black; border-collapse: collapse; padding: 7px"';
                $body = '<table cellspacing="0" cellpadding="0">';
                $body .= '<tr>';
                $body .= '<th ' . $style . '>Nomination</th><th ' . $style . '>Count</th><th ' . $style . '>Cost</th><th ' . $style . '>Total</th>';
                $body .= '</tr>';
                $total = array_reduce($leads, fn ($sum, $item) => $sum += $item['total'] ?? 0);
                foreach ($leads as $lead) {
                    $nomination = preg_replace('#<div(.*?)</div>#s', '', $lead['nomination'] ?? ''); //strip_tags
                    $nomination = preg_replace('#<br[^>]+>#s', '', $nomination);
                    $nomination = trim($nomination);
                    $body .= '<tr>';
                    $body .= '<td ' . $style . '>' . $nomination . '</td><td ' . $style . '>' . ($lead['count'] ?? 0) . '</td><td ' . $style . '>$' . number_format($lead['cost'] ?? 0, 0, '.', ' ') . '</td><td ' . $style . '>$' . number_format($lead['total'] ?? 0, 0, '.', ' ') . '</td>';
                    $body .= '</tr>';
                }
                $body .= '<tr>';
                $body .= '<td ' . $style . ' colspan="3">Total</td>';
                $body .= '<td ' . $style . '>$' . number_format($total, 0, '.', ' ') . '</td>';
                $body .= '</tr>';
                $body .= '</table>';

                $amount = number_format($total, 0, '.', ' ');
                $_amount = $total ?? 0;
            }

            if ($model->type == 'prepayment') {
                $amount = number_format($this->request_post('amount', 0) ?? 0, 0, '.', ' ');
                $_amount = $this->request_post('amount', 0) ?? 0;
            }
            $body .= '<p>Amount to be paid: $' . ($amount ?? 0) . '</p>';

            $_balance = new GeneralBalances($model->master);
            $balance = $_balance->get_general_balance();
            $balance_str = str_replace('$-', '-$', '$' . number_format(($balance ?? [])['balance'] ?? 0, 0, '.', ' '));
            $body .= '<p>Current balance: ' . $balance_str . '</p>';

            $balance_str = str_replace('$-', '-$', number_format((($balance ?? [])['balance'] ?? 0) + ($_amount ?? 0), 0, '.', ' '));
            $body .= '<p>Expected balance after payment: ' . $balance_str . '</p>';

            // $adjustment_amount = (int)$this->request_post('adjustment_amount', 0);
            // if (!empty($adjustment_amount) && $adjustment_amount > 0) {
            //     $body .= '<p>Adjustment Amount: ' . $adjustment_amount . '</p>';
            //     $adjustment_description = $this->request_post('adjustment_description', '');
            //     if (!empty($adjustment_description)) {
            //         $body .= '<p>Adjustment Description: ' . $adjustment_description . '</p>';
            //     }
            // }

            $attachments = [];
            try {
                if (!empty($model->payment_method)) {
                    // GeneralHelper::PrintR($model->payment_method);die();
                    $payment_method = MasterBillingPaymentMethod::findOrFail($model->payment_method);

                    if (!empty($payment_method->payment_method)) {
                        $body .= '<p>Payment Method: ' . $payment_method->payment_method . '</p>';
                    }
                    if ($payment_method->payment_method == 'crypto') {
                        if (!empty($payment_method->currency_crypto_code)) {
                            $body .= '<p>Currency: ' . strtoupper($payment_method->currency_crypto_code) . '</p>';
                        }

                        $wallet = '';
                        if (
                            $payment_method->payment_method == 'crypto' &&
                            $payment_method->currency_crypto_code == 'usdt' &&
                            !empty($payment_method->currency_crypto_wallet_type ?? '')
                        ) {
                            $wallet = 'Wallet ' . strtoupper(str_replace('_', ' ', $payment_method->currency_crypto_wallet_type)) . ': ' . $payment_method->wallet;
                        }

                        if (!empty($wallet)) {
                            $body .= '<p>' . $wallet . '</p>';
                        } else if (!empty($payment_method->wallet)) {
                            $body .= '<p>Wallet: ' . $payment_method->wallet . '</p>';
                        } else
                        if (!empty($payment_method->wallet2)) {
                            $body .= '<p>Wallet: ' . $payment_method->wallet2 . '</p>';
                        }
                    } else {
                        if (!empty($payment_method->currency_code)) {
                            $body .= '<p>Currency: ' . strtoupper($payment_method->currency_code) . '</p>';
                        }
                        if (!empty($payment_method->bank_name)) {
                            $body .= '<p>Bank Name: ' . $payment_method->bank_name . '</p>';
                        }
                        if (!empty($payment_method->account_name)) {
                            $body .= '<p>Account Name: ' . $payment_method->account_name . '</p>';
                        }
                        if (!empty($payment_method->account_number)) {
                            $body .= '<p>Account Number: ' . $payment_method->account_number . '</p>';
                        }
                        if (!empty($payment_method->swift)) {
                            $body .= '<p>Swift: ' . $payment_method->swift . '</p>';
                        }
                    }

                    $attachments = array_merge($attachments, $this->prepare_files((array)($payment_method->files ?? []), 'walet_'));
                }

                // if (!empty($model->master_approve_files)) {
                foreach ($_FILES as $field_name => $file) {
                    for ($i = 0; $i < count($_FILES[$field_name]['name']); $i++) {
                        $file_title = $_FILES[$field_name]['name'][$i];
                        if ($field_name == 'proof_screenshots') {
                            $file_title = 'proof_' . $file_title;
                        }
                        if ($field_name == 'master_approve_files') {
                            $file_title = 'invoice_' . $file_title;
                        }
                        $attachments[] = [
                            'file_path' => $_FILES[$field_name]["tmp_name"][$i],
                            'file_name' => $file_title
                        ];
                    }
                }
                // $attachments = array_merge($attachments, $this->prepare_files((array)($model->master_approve_files ?? []), 'proof_'));
                // }

                if (!empty($model->notes)) {
                    $body .= '<p>Payment Method Description: ' . $model->notes . '</p>';
                }
                if (!empty($model->proof_description)) {
                    $body .= '<p>Prof File Description: ' . $model->proof_description . '</p>';
                }

                $user = Auth::user();
                $body .= '<p>Created by: ' . $user->name . ' (' . $user->account_email . ')' . '</p>';

                $email_config = ClientHelper::setEmailConfig();
                // Mail::send
                Mail::mailer($email_config['mailer_name'])
                    ->send(
                        ['html' => 'emails.simple'],
                        ['title' => $title, 'body' => $body],
                        function ($message) use ($emails, $attachments, $title, $email_config) {

                            $message->to($emails[0])->subject($title);
                            for ($i = 1; $i < count($emails); $i++) {
                                $message->cc($emails[$i]);
                            }
                            foreach ($attachments as $attachment) {
                                if (file_exists($attachment['file_path'])) {
                                    $message->attach($attachment['file_path'], ['as' => $attachment['file_name']]); //, 'mime' => $mime
                                }
                            }

                            if (!empty($email_config['from'])) {
                                $message->from($email_config['from']['address'], $email_config['from']['name']);
                            }
                        }
                    );
            } catch (\Exception $ex) {
                Log::error($ex->getMessage());
                throw $ex;
            } finally {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['file_path'])) {
                        unlink($attachment['file_path']);
                    }
                }
            }
        }

        // GeneralHelper::PrintR([$body, count($leads)]);
        // die();
        // StorageHelper::syncFiles('billing_payment_requests', $model, $insert, 'proof_screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
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
                'master' => $this->masterId,
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
                'master' => $this->masterId,
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

            $payment_method = $payload['payment_method'] ?? '';
            $adjustment_amount = (int)$payload['adjustment_amount'];
            $adjustment_description = $payload['adjustment_description'] ?? '';
            $total += $adjustment_amount;

            $insert = [
                'master' => $this->masterId,
                'created_by' => GeneralHelper::get_current_user_token(),
                'type' => $payment_request_type,
                'from' => $time_range['start'] ?? null,
                'to' => $time_range['end'] ?? null,
                'status' => 0,
                // 'count' => count($data),
                'leads' => $leads,
                'cost' => $cost,
                'payment_method' => $payment_method,
                'adjustment_amount' => $adjustment_amount,
                'adjustment_description' => $adjustment_description,
                'total' => $total,

                'proof_screenshots' => $this->request_post('proof_screenshots', []),
                'proof_description' => $this->request_post('proof_description', ''),
                'json_leads' => json_encode($data)

            ];

            $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);

            StorageHelper::syncFiles('billing_payment_requests', null, $insert, 'proof_screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

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

        return [
            'timeframe' => $timeframe,
            'items' => $this->_feed_billing_payment_request($payment_request, $data)
        ];
    }

    private function _feed_billing_payment_request($payment_request = [], $data = null)
    {
        $subGroupBy = $this->_get_feed_group_by_billing_payment_request($data);
        $countries = GeneralHelper::countries();

        // -- build

        $response = [];

        $leads = 0;
        $costs = 0;
        $total = 0;

        $master = $this->get_master();

        foreach ($subGroupBy as $groupKey => $datas) {

            // if ($datas['cost'] == 0 && strpos($groupKey, 'crg_deal[1]') === false) {
            //     continue;
            // }
            if ($datas['cost'] == 0) {
                continue;
            }

            $response_row = [];

            if (strpos($groupKey, 'crg_deal[1]') !== false) {
                //150 leads Poland with (min CRG 8%) cr 9% = $11475 

                // ['1' => 'Master Affiliate', '2' => 'Master Brand']
                $pre = '';
                if ((int)$master['type'] == 2) {
                    $pre = 'broker_';
                }

                $master_crg_percentage_id = (!empty($datas['master_changed_crg_percentage_id'] ?? '') ? $datas['master_changed_crg_percentage_id'] : $datas['master_crg_percentage_id']);

                $response_row = [
                    '_id' => $master_crg_percentage_id,
                    'nomination' =>
                    '<strong>CRG. </strong>' . /*$datas['crg_leads'] . ' leads ' .*/ $countries[strtolower($datas['country'])] .
                        (
                            (Gate::has('custom[show_crg_deal_id]') && Gate::allows('custom[show_crg_deal_id]'))
                            ?
                            '<small style="display:block;color:gray;font-size:10px;">CRG ID: ' . $master_crg_percentage_id  . '</small>'
                            : ''
                        ) .
                        '<div style="display:none"><br/>(calculated by $' . $datas[$pre . 'crg_master_payout'] . ' payout, $' . $datas[$pre . 'crg_master_revenue'] . ' crg revenue)</div>',
                    'count' => $datas['Leads'] . ($datas[$pre . 'crg_master_revenue'] > 0 ? ' Leads' : ' FTD\'s'),
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                ];

                // if CRG deal is fail
            } else if (strpos($groupKey, 'isCPL[1]') !== false) {
                //120 Leads Germany CPL 25 = $3000
                // $html .= $datas['Leads'] . ' leads ' . $countries[strtolower($datas['country'])] . ' CPL ' . $datas['cost'] . ' = $' . $datas['cost'];

                $response_row = [
                    'nomination' =>
                    '<strong>CPL. </strong>' . $countries[strtolower($datas['country'])],
                    'count' => $datas['Leads'] . ' Leads',
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                ];
            } else {
                //3 FTD IT CPA 985 = $2955
                // $html .= $datas['Leads'] . ' FTD ' . $countries[$datas['country']] . ' CPA ' . $datas['cost'] . ' = $' . $datas['cost'];

                $response_row = [
                    'nomination' =>
                    '<strong>CPA.</strong> ' . $countries[strtolower($datas['country'])],
                    'count' => $datas['Leads'] . ' FTD\'s',
                    'cost' => $datas['cost'],
                    'total' => $datas['total'],
                ];
            }

            $response[] = $response_row;

            $leads += $datas['Leads'];
            $costs += $datas['cost'];
            $total += $datas['total'];
        }

        $adjustment_amount = $payment_request['adjustment_amount'] ?? 0;
        $total = $payment_request['total'] ?? ($total + $adjustment_amount);
        if ($adjustment_amount != 0) {
            $response[] = [
                'nomination' => 'Adjustment Amount' . (isset($payment_request['adjustment_description']) && !empty($payment_request['adjustment_description']) ? ' (' . $payment_request['adjustment_description'] . ')' : ''),
                'count' => '',
                'cost' => '',
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
        $master = $this->get_master();

        $query_fields = [
            '1' => 'MasterAffiliate',
            '2' => 'master_brand'
        ];
        $query_field = $query_fields[$master['type']];

        // -- query 
        $time = $this->buildTimestamp($timeframe);

        $query = $this->billing_payment_requests_buildParameterArray();

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

    public function billing_payment_requests_buildParameterArray()
    {
        // ['1' => 'Master Affiliate', '2' => 'Master Brand']
        $master = $this->get_master();
        if ((int)$master['type'] == 1) {
            return $this->billing_payment_requests_buildParameterArray_master_affiliate();
        } else {
            return $this->billing_payment_requests_buildParameterArray_master_brand();
        }
    }

    // Master Affiliate
    public function billing_payment_requests_buildParameterArray_master_affiliate()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
            'country',
            'crg_deal',
            'isCPL',
            'crg_master_payout',
            'crg_master_revenue',
        ];

        $metrics = [
            'leads',
            'cost',
            'crg_master_revenue',
            'crg_leads',
            'depositor'
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

            if ($metrics == 'crg_leads') {
                $array['crg_leads'] = array('crg_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)crg_deal__ == TRUE && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ') {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            }

            if ($metrics == 'depositor') {
                $array['Depositors'] = array('depositor' => [
                    'type' => 'count',
                    'formula' => '
                        if (
                            ((__(bool)depositor__ == TRUE && __(bool)deposit_disapproved__ == FALSE) || __(bool)fakeDepositor__ == TRUE )
                            && __endpointDepositTimestamp__ >= ' . $this->start . ' && __endpointDepositTimestamp__ <= ' . $this->end . ' ) {
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

    // Master Brand
    public function billing_payment_requests_buildParameterArray_master_brand()
    {

        $formula = array();
        $array = array();

        $pivots = [
            '_id',
            'country',
            'broker_crg_deal',
            'broker_cpl',
            'broker_crg_master_payout',
            'broker_crg_master_revenue',
        ];

        $metrics = [
            'leads',
            'cost',
            'broker_crg_master_revenue',
            'broker_crg_leads',
            'depositor'
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

            if ($metrics == 'broker_crg_leads') {
                $array['broker_crg_leads'] = array('broker_crg_leads' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)broker_crg_deal__ == TRUE && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ') {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            }

            if ($metrics == 'depositor') {
                $array['Depositors'] = array('depositor' => [
                    'type' => 'count',
                    'formula' => '
                            if ( __(bool)depositor__ == TRUE && __depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
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
                            $subGroupBy[$name]['total'] = ($subGroupBy[$name]['total'] ?? 0) + ($datas['cost'] ?? 0);
                            $subGroupBy[$name]['crg_master_revenue'] = (int)($subGroupBy[$name]['crg_master_revenue'] ?? 0) +  (int)($datas['crg_master_revenue'] ?? 0);
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
                        $subGroupBy[$name]['broker_crg_leads'] = (float)($subGroupBy[$name]['broker_crg_leads'] ?? 0) + (float)($datas['broker_crg_leads'] ?? 0);
                        $subGroupBy[$name]['total'] = (float)($subGroupBy[$name]['total'] ?? 0) + (float)($datas['cost'] ?? 0);
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
                            $subGroupBy[$name]['total'] = (float)($subGroupBy[$name]['total'] ?? 0) + (float)($datas['cost'] ?? 0);
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
                            $subGroupBy[$name]['total'] = (float)($subGroupBy[$name]['total'] ?? 0) + (float)($datas['cost'] ?? 0);
                            $subGroupBy[$name]['broker_crg_master_revenue'] = (float)($subGroupBy[$name]['broker_crg_master_revenue']) + (float)($datas['broker_crg_master_revenue'] ?? 0);
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
