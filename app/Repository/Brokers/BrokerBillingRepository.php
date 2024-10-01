<?php

namespace App\Repository\Brokers;

use App\Models\User;
use App\Models\Broker;
use MongoDB\BSON\ObjectId;
use App\Helpers\ClientHelper;
use MongoDB\BSON\UTCDateTime;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Classes\StorageWrapper;
use App\Models\PlatformAccounts;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Classes\Mongo\MongoDBObjects;

use Illuminate\Database\Eloquent\Model;
use App\Classes\Brokers\GeneralBalances;
use App\Classes\Brokers\PaymentRequests;
use App\Notifications\SlackNotification;
use App\Classes\Brokers\BrokerChangeLogs;
use App\Models\Brokers\BrokerIntegration;
use App\Models\Brokers\BrokerBillingEntity;
use phpDocumentor\Reflection\Types\Boolean;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Models\Billings\BillingPaymentMethod;
use App\Models\Brokers\BrokerBillingAdjustment;
use App\Models\Brokers\BrokerBillingChargeback;
use App\Models\Brokers\BrokerBillingPaymentMethod;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Models\Brokers\BrokerBillingPaymentRequest;
use App\Repository\Brokers\IBrokerBillingRepository;
use Exception;
use Merkeleon\PhpCryptocurrencyAddressValidation\Validation;

class BrokerBillingRepository extends BaseRepository implements IBrokerBillingRepository
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(BrokerIntegration $model)
    {
        $this->model = $model;
    }

    public function get_general_balance(string $brokerId): array
    {
        $billing = new GeneralBalances($brokerId);
        return $billing->get_general_balance();
    }

    public function get_general_balance_logs(string $brokerId, int $page, int $count_in_page = 20): array
    {
        $billing = new GeneralBalances($brokerId);
        return $billing->get_balances_log($page, $count_in_page);
    }

    public function get_change_logs(string $brokerId, bool $extended, string $collection, int $limit): array
    {
        $logs = new BrokerChangeLogs($brokerId);
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

    public function get_general_recalculate_logs(string $brokerId, array $payload): array
    {

        $page = $payload['page'] ?? 1;
        $count_in_page = $payload['count_in_page'] ?? 20;
        $timeframe = $payload['timeframe'] ?? '';

        $times = $this->parse_timeframe($timeframe);

        $mongo = new MongoDBObjects('history', []);

        $where = [
            'collection' => "leads",
            'category' => ['$in' => ['broker_crg', 'broker_cpl']],
            'timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
            "description" => ['$not' => new \MongoDB\BSON\Regex(('.*CRG No Deals Found.*'), 'i')]
        ];

        $where_lead = [
            "lead.brokerId" => $brokerId,
        ];

        if (!empty($payload['action_by'])) {
            $where['action_by'] = new ObjectId($payload['action_by']);
        }

        $scheme_type = ($payload['scheme_type'] ?? '');
        if ($scheme_type == 'crg_yes') {
            // $where['$or'] = [
            $where['data.broker_crg_deal'] = true;
            // ];
        } else if ($scheme_type == 'crg_no') {
            $where['data.broker_crg_deal'] = false;
            // $where['$or'][] = ['data.broker_crg_deal' => null];
            // $where['$or'][] = ['data.broker_crg_deal' => ['$exists' => false]];
        } else if ($scheme_type == 'cpl_yes') {
            $where['data.broker_cpl'] = true;
            // $where['$or'][] = ['data.broker_cpl' => null];
            // $where['$or'][] = ['data.broker_cpl' => ['$exists' => false]];
        } else if ($scheme_type == 'cpl_no') {
            $where['data.broker_cpl'] = false;
            // $where['$or'][] = ['data.broker_cpl' => null];
            // $where['$or'][] = ['data.broker_cpl' => ['$exists' => false]];
        }

        if (!empty($payload['country_code'])) {
            $where_lead['lead.country'] = strtoupper($payload['country_code']);
        }

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
                        'brokerId' => '$lead.brokerId',
                        'primary_key' => 1,
                        'country' => '$lead.country',
                        'LeadTimestamp' => '$lead.Timestamp',
                        'collection' => 1,
                        'description' => 1,
                        'category' => 1,
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
            $d['crg'] = $d['data']['broker_crg_deal'] ?? false;

            if ($d['category'] == 'broker_cpl') {
                $d['active'] = $d['data']['broker_cpl'] ?? false;
                $d['category'] = 'CPL';
            } else {
                $d['active'] = $d['data']['broker_crg_deal'] ?? false;
                $d['category'] = 'CRG';
            }
        }

        return [
            'count' => $count,
            'items' => $data
        ];
    }

    public function update_general_balance_logs(string $brokerId, string $logId, array $payload): bool
    {
        $logs = new GeneralBalances($brokerId, $payload);
        return $logs->update_general_balance_logs($logId);
    }

    public function set_negative_balance_action(string $brokerId, string $action): bool
    {
        $billing = new GeneralBalances($brokerId);
        return $billing->set_negative_balance_action($action);
    }

    public function set_credit_amount(string $brokerId, int $amount): bool
    {
        $billing = new GeneralBalances($brokerId);
        return $billing->set_credit_amount($amount);
    }

    public function feed_entities(string $brokerId): Collection
    {
        $items = BrokerBillingEntity::where(['broker' => $brokerId])->get();
        StorageHelper::injectFiles('billing_entity', $items, 'files');
        return $items;
    }

    public function get_entity(string $modelId): ?Model
    {
        $item = BrokerBillingEntity::findOrFail($modelId);
        StorageHelper::injectFiles('billing_entity', $item, 'files');
        return $item;
    }

    public function create_entity(array $payload): ?Model
    {
        StorageHelper::syncFiles('billing_entity', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        $model = BrokerBillingEntity::create($payload);
        return $model->fresh();
    }

    public function update_entity(string $modelId, array $payload): bool
    {
        $model = BrokerBillingEntity::findOrFail($modelId);
        StorageHelper::syncFiles('billing_entity', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_entity(string $modelId): bool
    {
        $model = BrokerBillingEntity::findOrFail($modelId);
        StorageHelper::deleteFiles('billing_entity', $model, 'files');
        return $model->delete();
    }

    public function files_chargebacks(string $brokerId, string $id): array
    {
        $fields = ['screenshots', 'final_approve_files', 'proof_screenshots'];
        $model = BrokerBillingChargeback::findOrFail($id);
        $result = [];
        foreach ($fields as $field) {
            StorageHelper::injectFiles('billing_chargebacks', $model, $field);

            $title = preg_replace_callback('#_(\w)#is', fn ($match) => (' ' . strtoupper($match[1])), '_' . $field);
            if ($field == 'screenshots') {
                $title = 'Invoice proof';
            }
            if ($field == 'final_approve_files') {
                $title = 'Financial Approve';
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
    }

    public function feed_chargebacks(string $brokerId): array
    {
        $items = BrokerBillingChargeback::where(['broker' => $brokerId])
            ->with('payment_method_data')
            ->with('payment_method_old_data')
            ->with('payment_request_data')
            ->get()
            ->toArray();

        // StorageHelper::injectFiles('billing_chargebacks', $items, 'screenshots');
        // StorageHelper::injectFiles('billing_chargebacks', $items, 'proof_screenshots');
        // StorageHelper::injectFiles('billing_chargebacks', $items, 'final_approve_files');

        foreach ($items as &$item) {
            $item['amount'] = abs($item['amount'] ?? 0);

            $is_request_transaction_id = false;

            if (!empty($item['payment_method'])) {
                $payment_method = BrokerBillingPaymentMethod::find($item['payment_method']);
                if (!isset($payment_method)) {
                    $payment_method = BillingPaymentMethod::findOrFail($item['payment_method']);
                }
                $is_request_transaction_id = ($payment_method->payment_method == 'crypto' &&
                    ($payment_method->currency_crypto_code == 'usdt' || $payment_method->currency_crypto_code == 'btc' ||
                        $payment_method->currency_code == 'usdt' || $payment_method->currency_code == 'btc'
                    ));
            }

            if ($item['payment_method_data'] == null && isset($item['payment_method_old_data'])) {
                $item['payment_method_data'] = unserialize(serialize($item['payment_method_old_data']));
                // GeneralHelper::PrintR($item['payment_method_data']);die();
            }

            $item['is_request_transaction_id'] = $is_request_transaction_id;
        }
        return $items;
    }

    public function get_chargeback(string $modelId): ?Model
    {
        $item = BrokerBillingChargeback::find($modelId);
        if (!isset($item)) {
            $item = BillingPaymentMethod::findOrFail($modelId);
        }
        $item->amount = abs($item->amount ?? 0);
        StorageHelper::injectFiles('billing_chargebacks', $item, 'screenshots');
        StorageHelper::injectFiles('billing_chargebacks', $item, 'proof_screenshots');
        StorageHelper::injectFiles('billing_chargebacks', $item, 'final_approve_files');
        return $item;
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

    private function send_email_chargeback(string $chargeback_id)
    {
        $model = BrokerBillingChargeback::findOrFail($chargeback_id);
        $broker = Broker::findOrFail($model->broker);

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
                $chargeback_id . ' - ' . ' Chargeback #' . $broker['token'];

            $body = '';

            $amount = number_format($model->amount ?? 0, 0, '.', ' ');

            $body .= '<p>Amount to be paid: $' . ($amount ?? 0) . '</p>';

            $balance = $broker->balance ?? 0;
            $balance_str = str_replace('$-', '-$', '$' . number_format(($balance ?? [])['balance'] ?? 0, 0, '.', ' '));
            $body .= '<p>Current balance: ' . $balance_str . '</p>';

            $balance_str = str_replace('$-', '-$', number_format((($balance ?? [])['balance'] ?? 0) + ($model->amount ?? 0), 0, '.', ' '));
            $body .= '<p>Expected balance after payment: ' . $balance_str . '</p>';

            $attachments = [];
            try {
                if (!empty($model->payment_method)) {
                    $payment_method = BrokerBillingPaymentMethod::findOrFail($model->payment_method);

                    if ($payment_method != null) {
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
                }

                foreach ($_FILES as $field_name => $file) {
                    for ($i = 0; $i < count($_FILES[$field_name]['name']); $i++) {
                        $file_title = $_FILES[$field_name]['name'][$i];
                        if ($field_name == 'proof_screenshots') {
                            $file_title = 'proof_' . $file_title;
                        }
                        if ($field_name == 'final_approve_files') {
                            $file_title = 'invoice_' . $file_title;
                        }
                        $attachments[] = [
                            'file_path' => $_FILES[$field_name]["tmp_name"][$i],
                            'file_name' => $file_title
                        ];
                    }
                }
                // $attachments = array_merge($attachments, $this->prepare_files((array)($model->master_approve_files ?? []), 'proof_'));

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

    public function create_chargeback(array $payload): ?Model
    {
        if (!empty($payload['payment_request'])) {
            $payment_request = BrokerBillingPaymentRequest::query()->find($payload['payment_request']);
            $payment_request->update(['chargeback' => true]);
            $payload['amount'] = (float)$payment_request->total;
        }
        $payload['amount'] = -abs((float)$payload['amount']);

        StorageHelper::syncFiles('billing_chargebacks', null, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        StorageHelper::syncFiles('billing_chargebacks', null, $payload, 'proof_screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        // StorageHelper::syncFiles('billing_chargebacks', null, $payload, 'final_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = BrokerBillingChargeback::create($payload);

        $result = $model->fresh();

        try {
            $this->send_email_chargeback($model->_id);
        } catch (Exception $ex) {
            $model->delete();
            $result = false;
        }

        return $result;
    }

    public function update_chargeback(string $modelId, array $payload): bool
    {
        $model = BrokerBillingChargeback::findOrFail($modelId);

        if (!empty($model->payment_request)) {
            $payment_request = BrokerBillingPaymentRequest::query()->find($model->payment_request);
            $payment_request->update(['chargeback' => false]);
        }

        if (!empty($payload['payment_request'])) {
            $payment_request = BrokerBillingPaymentRequest::query()->find($payload['payment_request']);
            $payment_request->update(['chargeback' => true]);
            $payload['amount'] = (float)$payment_request->total;
        }
        $payload['amount'] = -abs((float)$payload['amount']);

        StorageHelper::syncFiles('billing_chargebacks', $model, $payload, 'screenshots', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function delete_chargeback(string $modelId): bool
    {
        $model = BrokerBillingChargeback::findOrFail($modelId);
        StorageHelper::deleteFiles('billing_chargebacks', $model, 'screenshots');
        return $model->delete();
    }

    public function fin_approve_chargeback(string $brokerId, string $modelId, array $payload): bool
    {
        $date_pay = $payload['date_pay'];

        $model = BrokerBillingChargeback::findOrFail($modelId);

        $payload['final_status_changed_date'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
        $payload['final_status_changed_user_id'] = GeneralHelper::get_current_user_token();
        $payload['final_status_changed_user_ip'] = GeneralHelper::get_user_ip();
        $payload['final_status_changed_user_ua'] = $_SERVER["HTTP_USER_AGENT"];
        $payload['final_status_date_pay'] = GeneralHelper::ToMongoDateTime($date_pay);
        $payload['final_status'] = 1;

        StorageHelper::syncFiles('billing_payment_methods', $model, $payload, 'final_approve_files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $user = Auth::user();
        $broker = Broker::findOrFail($brokerId)->toArray();
        $subject = 'New Chargeback Financial Approve';
        $message = $subject . ' :';
        $message .= ' Broker ' . GeneralHelper::broker_name($broker);
        $message .= ', User ' . ($user->name ?? '');
        $message .= ', Amount $' . ($model->amount ?? 0);

        Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('finance_approved', $message)));

        return $model->update($payload);
    }

    public function fin_reject_chargeback(string $brokerId, string $modelId): bool
    {
        $where = [
            '_id' => new \MongoDB\BSON\ObjectId($modelId),
        ];
        $mongo = new MongoDBObjects('broker_billing_chargebacks', $where);

        return $mongo->update([
            'final_status_changed_date' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
            'final_status_changed_user_id' => GeneralHelper::get_current_user_token(),
            'final_status_changed_user_ip' => GeneralHelper::get_user_ip(),
            'final_status_changed_user_ua' => $_SERVER["HTTP_USER_AGENT"],
            'final_status' => 2
        ]);
    }

    public function feed_adjustments(string $brokerId): Collection
    {
        return BrokerBillingAdjustment::where(['broker' => $brokerId])->get();
    }

    public function get_adjustment(string $modelId): ?Model
    {
        return BrokerBillingAdjustment::findOrFail($modelId);
    }

    public function create_adjustment(array $payload): ?Model
    {
        $model = BrokerBillingAdjustment::create($payload);
        return $model->fresh();
    }

    public function update_adjustment(string $modelId, array $payload): bool
    {
        $model = BrokerBillingAdjustment::findOrFail($modelId);
        return $model->update($payload);
    }

    public function delete_adjustment(string $modelId): bool
    {
        return BrokerBillingAdjustment::findOrFail($modelId)->delete();
    }

    // own

    public function feed_our_payment_methods(string $brokerId): Collection
    {
        $selected = BrokerBillingPaymentMethod::firstWhere(['broker' => $brokerId, 'type' => ['$ne' => 'broker']]);
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

    public function select_our_payment_method(string $brokerId, string $methodId): bool
    {
        // BrokerBillingPaymentMethod::query()->where(['broker' => $brokerId, 'type' => ['$ne' => 'broker']])->update(['status' => 0]);
        $model = BrokerBillingPaymentMethod::firstOrCreate(['broker' => $brokerId, 'type' => ['$ne' => 'broker']], ['status' => 1]);
        return $model->update(['payment_method' => $methodId, 'status' => 1]);
    }

    // broker
    public function feed_payment_methods(string $brokerId): array
    {
        $result = BrokerBillingPaymentMethod::query()->where(['broker' => $brokerId, 'type' => 'broker'])->get()->toArray();

        foreach ($result as &$billing) {
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
        return $result;
    }

    public function create_payment_method(string $brokerId, array $payload): ?Model
    {
        $payload['broker'] = $brokerId;
        $payload['type'] = 'broker';

        StorageHelper::syncFiles('billing_payment_methods', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model = BrokerBillingPaymentMethod::create($payload);
        return $model->fresh();
    }

    public function update_payment_methods(string $paymentMethodId, array $payload): bool
    {
        $payload['type'] = 'broker';
        $model = BrokerBillingPaymentMethod::findOfFail($paymentMethodId);

        StorageHelper::syncFiles('billing_payment_methods', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        return $model->update($payload);
    }

    public function select_payment_method(string $brokerId, string $methodId): bool
    {
        $model = BrokerBillingPaymentMethod::firstOrCreate(['_id' => $methodId, 'type' => 'broker'], ['status' => 1]);
        return $model->update(['status' => 1]);
    }

    public function files_payment_methods(string $brokerId, string $paymentMethodId): array
    {
        $fields = ['files'];
        $model = BrokerBillingPaymentMethod::findOrFail($paymentMethodId);
        $result = [];
        foreach ($fields as $field) {
            StorageHelper::injectFiles('billing_payment_methods', $model, $field);

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

    public function feed_payment_requests(string $brokerId, bool $only_completed): Collection
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->feed($only_completed);
    }

    public function feed_payment_requests_query(string $brokerId, array $payload): array
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->pre_create_query($payload);
    }

    public function create_payment_request(string $brokerId, array $payload): string
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->create_payment_request($payload);
    }

    public function get_payment_request(string $modelId): ?Model
    {
        return BrokerBillingPaymentRequest::findOrFail($modelId);
    }

    public function get_payment_request_calculations(string $brokerId, string $modelId): array
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->view_calculations($modelId);
    }

    public function get_payment_request_invoice(string $brokerId, string $modelId): void
    {
        $payments = new PaymentRequests($brokerId);
        $payments->download_invoice($modelId);
    }

    public function get_payment_request_files(string $modelId): array
    {
        $fields = ['affiliate_approve_files', 'final_approve_files', 'master_approve_files'];
        $model = BrokerBillingPaymentRequest::findOrFail($modelId);
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

    public function payment_request_approve(string $brokerId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->approve($modelId, $payload);
    }

    public function payment_request_change(string $brokerId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->change($modelId, $payload);
    }

    public function payment_request_reject(string $brokerId, string $modelId): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->reject($modelId);
    }

    public function payment_request_fin_approve(string $brokerId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->fin_approve($modelId, $payload);
    }

    public function payment_request_fin_reject(string $brokerId, string $modelId): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->fin_reject($modelId);
    }

    public function payment_request_real_income(string $brokerId, string $modelId, array $payload): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->real_income($modelId, $payload);
    }

    public function payment_request_archive(string $brokerId, string $modelId): bool
    {
        $payments = new PaymentRequests($brokerId);
        return $payments->archive($modelId);
    }

    public function set_manual_status(string $brokerId, string $manual_status): bool
    {
        $model = Broker::findOrFail($brokerId);
        return $model->update(['billing_manual_status' => $manual_status]);
    }
}
