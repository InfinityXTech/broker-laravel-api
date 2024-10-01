<?php

namespace App\Repository\Brokers;

use App\Models\Broker;
use App\Helpers\GeneralHelper;
use App\Classes\History\HistoryDB;
use App\Repository\BaseRepository;

use App\Classes\Brokers\DownloadCRG;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Brokers\DownloadPrice;
use Illuminate\Database\Eloquent\Model;
use App\Classes\Brokers\ConversionRates;
use App\Classes\History\HistoryDBAction;
use App\Helpers\CryptHelper;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Brokers\IBrokerRepository;
use Exception;

class BrokerRepository extends BaseRepository implements IBrokerRepository
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
    public function __construct(Broker $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): array
    {
        $query = $this->model->with($relations)->where('partner_type', '=', '1');
        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q
                    ->orWhere('created_by', '=', $user_token)
                    ->orWhere('account_manager', '=', $user_token);
            });
        }

        $result = $query->get($columns);
        $items = $result ? $result->toArray() : [];

        foreach ($items as &$item) {
            if (in_array('total_cr', $columns) || in_array('*', $columns)) {
                $cr = 0;
                $total_ftd = (int)(isset($item->total_ftd) ? $item->total_ftd : 0);
                $total_leads = (int)(isset($item->total_leads) ? $item->total_leads : 0);
                if ($total_ftd > 0) {
                    $cr = (float)($total_ftd / $total_leads) * 100;
                }
                $item['total_cr'] = $cr;
            }

            if (in_array('today_cr', $columns) || in_array('*', $columns)) {
                $cr = 0;
                $today_ftd = (int)(isset($item->today_ftd) ? $item->today_ftd : 0);
                $today_leads = (int)(isset($item->today_leads) ? $item->today_leads : 0);
                if ($today_leads > 0) {
                    $cr = (float)($today_ftd / $today_leads) * 100;
                }
                $item['today_cr'] = round($cr, 2) . '%';
            }

            $item['partner_name'] = GeneralHelper::broker_name($item);
        }

        return $items;
    }

    private function validateUsername($username)
    {
        $is_broker_exists = $this->model->select(['account_username'])->find(['account_username' => $username])->count();
        if ($is_broker_exists) {
            $new = $username . '_b' . random_int(1, 9);
        } else {
            $new = $username;
        }

        return $new;
    }

    private function get_new_token()
    {
        while (true) {
            $token = $this->generate_token();
            $mongo = new MongoDBObjects('partner', ['token' => $token]);
            if ($mongo->count() == 0) {
                return $token;
            }
        }
    }

    private function generate_token()
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = 'BR' . substr(str_shuffle($charsz), 0, 2);
        return $token . '' . random_int(1, 100);
    }

    private function getUsernameAndPassword($partner_name)
    {
        $search = array(' ', '-', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', ',', '.', '/', '|', '}', '{', ']', '[', '"', "'", '?', '>', '<', '+', '-');
        $replace = array('', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
        $name = str_replace($search, $replace, $partner_name);
        $account_user_name = strtolower($name) . '' . random_int(1, 9);
        $account_api_key = md5($partner_name) . '' . time() . '' . random_int(1, 100000);
        $api_key = str_replace($search, $replace, $account_api_key);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 8);

        $username = $this->validateUsername($account_user_name);

        return [$api_key, $username, md5($password)];
    }

    public function create(array $payload): ?Model
    {
        // list($api_key, $account_username, $account_password) = $this->getUsernameAndPassword($payload['partner_name']);

        $payload['token'] = $this->get_new_token();
        // $payload['api_key'] = $api_key;
        // $payload['account_username'] = $account_username;
        // $payload['account_password'] = $account_password;

        $payload['partner_type'] = '1';
        $payload['status'] = '1';

        $payload['created_by'] = Auth::id();

        $payload['broker_crg_already_paid'] = true;

        $payload['action_on_negative_balance'] = 'stop';

        $payload['partner_name'] = CryptHelper::encrypt($payload['partner_name']);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function integrations_index(array $columns = ['*'], array $relations = []): Collection
    {
        if (!in_array('partnerId', $columns)) {
            $columns[] = 'partnerId';
        }

        $items = BrokerIntegration::where($relations)->with('integration')->get($columns);
        foreach ($items as &$item) {
            if (isset($item['name'])) {
                $item['name'] = GeneralHelper::broker_integration_name($item);
            }
        }
        return $items;
    }

    public function integrations_create(array $payload): ?Model
    {
        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function un_payable_leads(array $payload): array
    {
        $data = ['success' => false];
        try {
            $lead_ids_str = $payload['leadIds'] ?? '';
            $lead_ids_str = str_replace(",", PHP_EOL, $lead_ids_str);

            $lead_ids = preg_split("/(\r\n|\n|\r)/", $lead_ids_str);
            $in = [];

            $errors = '';
            foreach ($lead_ids as $lead_id) {
                if (!empty($lead_id)) {
                    try {
                        $in[] = new \MongoDB\BSON\ObjectId(trim($lead_id));
                    } catch (\Exception $ex) {
                        $errors .= '<br/>' . $lead_id . ' is not valid ID';
                    }
                }
            }

            if (!empty($errors)) {
                throw new \Exception('Operation aborted. Fix leads with errors: ' . $errors);
            }

            // --- broker_crg_deal --- //
            $where = array();
            $where = ['_id' => ['$in' => $in]];
            $mongo = new MongoDBObjects('leads', $where);
            $leads = $mongo->findMany();

            if ($payload['type'] == 'cpl') {
                $where_check = [
                    '_id' => ['$in' => $in],
                    '$or' => [
                        ['broker_cpl' => 0],
                        ['broker_cpl' => false],
                        ['broker_cpl' => ['$exists' => false]]
                    ]
                ];
                $mongo_check = new MongoDBObjects('leads', $where_check);
                if ($mongo_check->count() > 0) {
                    throw new Exception('Some of the leads is not CPL');
                }
                $mongo->updateMulti(['revenue' => 0, 'Master_brand_cost' => 0, 'broker_cpl_unpaid' => true]);

                $fields = ['broker_cpl'];
                $_data = ['broker_cpl' => false];
                $category = 'broker_' . ($payload['type'] ?? '');
                $reasons_change_crg = $payload['broker_reason_change2'] ?? $payload['broker_reason_change'] ?? '';
                foreach ($leads as $lead) {
                    HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$lead['_id'], '', $fields, $category, $reasons_change_crg);
                }
                $data = ['success' => true];
            } else if ($payload['type'] == 'crg') {

                $where_check = [
                    '_id' => ['$in' => $in],
                    '$or' => [
                        ['broker_crg_deal' => false],
                        ['broker_crg_deal' => ['$exists' => false]]
                    ]
                ];
                $mongo_check = new MongoDBObjects('leads', $where_check);
                if ($mongo_check->count() > 0) {
                    throw new Exception('Some of the leads is not CRG');
                }

                $update = [
                    'broker_crg_deal' => false,
                    'broker_crg_ftd_uncount' => false,
                    'broker_crg_ftd_uncount_timestamp' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
                    'broker_crg_ftd_uncount_handler' => 'manual_un_payable'
                ];

                if ($mongo->updateMulti($update)) {
                    $data = ['success' => true];
                }

                // --- broker_crg_percentage_id --- //
                $where = [];
                foreach ($leads as $lead) {
                    if ($lead['broker_crg_deal']) {
                        $start = null;
                        $end = null;
                        $v = $lead['Timestamp'];
                        $mil = ((array)$v)['milliseconds'];
                        $seconds = $mil / 1000;
                        $dt = date("d-m-Y", $seconds);
                        switch ($lead['broker_crg_percentage_period']) {
                            case 1: { // Daily
                                    $start = $dt;
                                    $end = $dt;
                                    break;
                                }
                            case 2: { // Weekly
                                    $day_of_week = date("N", strtotime($dt)) - 1;
                                    $start = date("Y-m-d", strtotime($dt . " -$day_of_week days"));
                                    $end = date("Y-m-d", strtotime($start . " +6 days"));
                                    break;
                                }
                            case 3: { // Monthly
                                    $month = date("m", strtotime($dt));
                                    $start = date('Y-' . $month . '-01');
                                    $date = new \DateTime($start);
                                    $date->setTime(23, 59, 59);
                                    $date->modify('last day of this month');
                                    $end = $date->format('Y-m-d');
                                    break;
                                }
                        }

                        $start = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($start)) . " 00:00:00") * 1000);
                        $end = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($end)) . " 23:59:59") * 1000);
                        $w = [
                            '$and' => [
                                ['broker_crg_deal' => true],
                                ['broker_crg_percentage_id' => $lead['broker_crg_percentage_id']],
                                ['broker_crg_percentage_period' => $lead['broker_crg_percentage_period']],
                                ['Timestamp' => ['$gte' => $start, '$lte' => $end]]
                            ]
                        ];
                        $where['$or'][] = $w;

                        $fields = ['broker_crg_deal'];
                        $_data = ['broker_crg_deal' => false];
                        $category = 'broker_' . ($payload['type'] ?? '');
                        $reasons_change_crg = $payload['broker_reason_change2'] ?? $payload['broker_reason_change'] ?? '';
                        HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$lead['_id'], '', $fields, $category, $reasons_change_crg);
                    }
                }
                $mongo = new MongoDBObjects('leads', $where);
                $leads2 = $mongo->findMany();
                if (!isset($leads2)) $leads2 = [];
                // $leads = array_merge((array)$leads, (array)$leads2);

                // --- broker_crg_finall_calculated --- //
                $in = [];
                foreach ($leads2 as $lead) {
                    $lead_id = (array)$lead['_id'];
                    $lead_id = $lead_id['oid'];
                    $in[] = new \MongoDB\BSON\ObjectId($lead_id);
                }
                $where = array();
                $where = ['_id' => ['$in' => $in]];
                $mongo = new MongoDBObjects('leads', $where);
                $update = ['broker_crg_recalculate' => true];
                $mongo->updateMulti($update);
            }
        } catch (\Exception $ex) {
            $data = ['success' => false, 'error' => $ex->getMessage()];
        }
        return $data;
    }

    public function conversion_rates($modelId): array
    {
        $rates = new ConversionRates($modelId);
        return $rates->collect();
    }

    public function download_price(): string
    {
        $d = new DownloadPrice();
        return $d->makeCsv();
    }

    public function download_crgdeals(string $broker_id): string
    {
        $d = new DownloadCRG();
        return $d->makeCsv();
    }
}
