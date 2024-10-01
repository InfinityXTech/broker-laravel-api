<?php

namespace App\Repository\TrafficEndpoints;

use Exception;
use App\Models\User;
use App\Models\Offer;

use App\Scopes\ClientScope;
use App\Helpers\CryptHelper;

use App\Models\LeadRequests;
use App\Helpers\ClientHelper;

use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Classes\History\HistoryDB;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Classes\History\HistoryDBAction;
use App\Classes\TrafficEndpoints\ManageFeed;
use Illuminate\Database\Eloquent\Collection;
use App\Classes\TrafficEndpoints\AnalizeLead;
use App\Classes\TrafficEndpoints\DownloadCRG;
use App\Classes\TrafficEndpoints\DownloadPrice;
use App\Classes\TrafficEndpoints\ManageSimulator;
use App\Repository\TrafficEndpoints\ITrafficEndpointRepository;

class TrafficEndpointRepository extends BaseRepository implements ITrafficEndpointRepository
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
    public function __construct(TrafficEndpoint $model = null)
    {
        $this->model = $model;
    }

    public function stat_under_review(): array
    {
        $all = TrafficEndpoint::all(['UnderReview'])->whereIn("UnderReview", ['0', '1', '2']);
        $result = ['all' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($all as $rec) {
            $result['all'] += 1;
            if ((int)$rec->UnderReview == 0) {
                $result['under_review'] += 1;
            }
            if ((int)$rec->UnderReview == 1) {
                $result['approved'] += 1;
            }
            if ((int)$rec->UnderReview == 2) {
                $result['rejected'] += 1;
            }
        }
        return $result;
    }

    public function index(array $columns = ['*'], array $relations = [], array $payload = []): Collection
    {
        $query = $this->model->with($relations);

        foreach ($payload as $where_key => $where_value) {
            if (is_array($where_value)) {
                $query = $query->whereIn($where_key, $where_value);
            } else {
                $query = $query->where($where_key, '=', $where_value);
            }
        }

        if (!isset($payload['UnderReview'])) {
            if (Gate::allows('role:admin')) {
                $query = $query->where(function ($q) {
                    $q->whereIn('UnderReview', [1, 2])->orWhere('UnderReview', '=', null);
                });
            } else {
                $query = $query->where(function ($q) {
                    $q->where('UnderReview', '=', 1)->orWhere('UnderReview', '=', null);
                });
            }
        }

        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $query = $query->where(function ($q) {
                $user_token = Auth::id();
                $q
                    ->orWhere('created_by', '=', $user_token)
                    ->orWhere('user_id', '=', $user_token)
                    ->orWhere('account_manager', '=', $user_token);
            });
        }

        if (!ClientHelper::is_public_features('GA26')) {
            $query = $query->where(function ($q) {
                $q->where('in_house', '!=', true)->orWhereNull('in_house');
            });
        }

        $result = $query->get($columns);

        foreach ($result as &$item) {
            if (($item->UnderReview ?? -1) >= 0) {
                $item->status = $item->UnderReview == 1 ? 1 : 0;
            }
            $item->today_cr = round($item['today_leads'] > 0 ? 100 * $item['today_deposits'] / $item['today_leads'] : 0, 2) . '%';
            $item->total_cr = round($item['total_leads'] > 0 ? 100 * $item['total_deposits'] / $item['total_leads'] : 0, 2) . '%';
        }

        return $result;
    }

    private function getSecretData()
    {
        $account_api_key = md5(random_int(1, 100000000)) . '' . time() . '' . random_int(1, 100000);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 8);


        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = substr(str_shuffle($charsz), 0, 2);

        $partnerToken = $token . '' . random_int(1, 100);

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();
        $clientConfig = ClientHelper::clientConfig();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl(($clientConfig['nickname'] ?? '') .  ' (' . $partnerToken . ') ', $secret);

        return [
            'api_key' => $account_api_key,
            'token' => $partnerToken,
            'account_password' => md5($password),
            'login_qr' => $qrCodeUrl,
            'qr_secret' => $secret
        ];
    }

    public function create(array $payload): ?Model
    {
        $data = $this->getSecretData();
        foreach ($data as $key => $value) {
            $payload[$key] = $value;
        }

        $payload['created_by'] = Auth::id();
        $payload['user_id'] = Auth::id();
        $payload['status'] = 1;
        $payload['statusMatching'] = 0;
        $payload['statusReporting'] = 1;
        $payload['statusDeposit'] = 0;
        $payload['redirect_24_7'] = 0;
        $payload['endpoint_status'] = '1';
        $payload['probation'] = 0;

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        // print_r($payload);
        $result = false;
        try {

            // if (isset($payload['send_mismatch_again'])) {
            //     if (!Gate::allows('role:admin')) {
            //         unset($payload['send_mismatch_again']);
            //     } else {
            //         $payload['send_mismatch_again'] = $payload['send_mismatch_again'] ? '1' : '0';
            //     }
            // }

            $payload['statusReporting'] = $payload['statusReporting'] ? '1' : '0';
            // $payload['redirect_24_7'] = $payload['redirect_24_7'] ? '1' : '0';

            // $model = TrafficEndpoint::query()->find($modelId)
            $model = $this->findById($modelId);

            // if (isset($payload['_probation'])) {
            //     $payload['probation'] = (int)($payload['_probation'] ?? 0) == 1 ? 2 : 1;
            //     unset($payload['_probation']);
            // }

            $result = $model->update($payload);
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }

        return $result;
    }

    public function offers_access_get(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection
    {

        $traffic_endpoint = (array)$this->findById($trafficEndpointId, ['marketing_suite_offers'])->toArray();
        $marketing_suite_offers = $traffic_endpoint['marketing_suite_offers'] ?? [];

        $offers = Offer::withoutGlobalScope(new ClientScope)->whereIn('status', ['1', 1])->get($columns);
        foreach ($offers as &$offer) {
            $id = (string)$offer['_id'];
            $offer['enabled'] = in_array($id, $marketing_suite_offers);
        }
        return $offers;
    }

    public function offers_access_update(string $trafficEndpointId, array $payload): bool
    {
        $marketing_suite_offers = [];
        foreach ($payload as $item) {
            $marketing_suite_offers[] = $item['key'];
        }
        return $this->update($trafficEndpointId, [
            'marketing_suite_offers' => $marketing_suite_offers
        ]);
    }

    public function feed_visualization_group_by_fields(string $trafficEndpointId = ''): array
    {
        $result = [];

        $manageFeed = new ManageFeed();
        $result = $manageFeed->feed_traffic_endpoint_visualization_group_by_fields($trafficEndpointId);

        return $result;
    }

    public function feed_visualization_get(string $trafficEndpointId, array $payload): array
    {
        $manageFeed = new ManageFeed($payload);
        return $manageFeed->feed_traffic_endpoint_visualization($trafficEndpointId);
    }

    public function reset_password(string $trafficEndpointId): array
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 15);

        $model = TrafficEndpoint::query()->find($trafficEndpointId);

        $model->aff_dashboard_pass = md5($password);
        $model->save();

        return ['success' => true, 'password' => $password];
    }

    public function postbackFTD($lead)
    {

        if (!isset($lead)) return;

        $traffic_endpoint_id = new \MongoDB\BSON\ObjectId($lead['TrafficEndpoint']);

        $mongoendpoint = new MongoDBObjects('TrafficEndpoints', ['_id' => $traffic_endpoint_id]);
        $endpoint = $mongoendpoint->find();

        if (!isset($endpoint)) return;

        if (isset($endpoint['postback']) && !empty($endpoint['postback'])) {

            $find = array();
            $find[] = '{lead_id}';
            $find[] = '{payout}';
            $find[] = '{bp_privategr}';
            $find[] = '{clickid}';

            $id = (array)$lead['_id'];
            $lead_id = $id['oid'];

            $replace = array();
            $replace[] = $lead_id;

            if ($lead['isCPL'] == true) {
                $replace[] = 0;
                $replace[] = 0;
            } else {
                $replace[] = $lead['cost'];
                $replace[] = $lead['deposit_revenue'];
            }
            $replace[] = $lead['publisher_click'];
            $url = str_ireplace($find, $replace, $endpoint['postback']);

            if (isset($url) && !empty($url)) {
                file_get_contents($url);
            }
        }
    }

    function metricFTDs($leads)
    {

        $funnel_tokens = [];
        foreach ($leads as $lead) {
            $funnel_lp = (isset($lead['funnel_lp']) ? $lead['funnel_lp'] : '');
            if (preg_match("@^https?://.*?/([^/$]+)@", $funnel_lp, $matches)) {
                $funnel_token = $matches[1];
                $click_id = '';
                if (isset($lead['marketing_suite_click_id']) && !empty($lead['marketing_suite_click_id'])) {
                    $click_id = $lead['marketing_suite_click_id'];
                }
                $funnel_tokens[] = ['click_id' => $click_id, 'funnel_token' => $funnel_token];
            }
        }

        foreach ($funnel_tokens as $funnel) {

            // ---------- offers ---------- //
            try {
                $where = ['token' => new \MongoDB\BSON\Regex(preg_quote($funnel['funnel_token']), 'i')];

                $update = [
                    'FTDs' => 1
                ];

                $mongo = new MongoDBObjects('offers', $where);
                $mongo->findOneAndUpdate($update);
            } catch (\Exception $ex) {
            }

            // ---------- traffic_analysis ---------- //
            try {
                if (!empty($funnel['click_id'])) {
                    $where = [
                        '_id' => new \MongoDB\BSON\ObjectId($funnel['click_id'])
                    ];

                    $update = [
                        'is_ftd' => true
                    ];

                    $mongo = new MongoDBObjects('traffic_analysis', $where);
                    $mongo->update($update);
                }
            } catch (\Exception $ex) {
            }
        }

        foreach ($leads as $lead) {

            // ---------- broker ---------- //
            if (isset($lead['brokerId'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['brokerId'])];
                    $update = [
                        'today_revenue' => (int)$lead['deposit_revenue'],
                        'total_revenue' => (int)$lead['deposit_revenue'],
                        'today_ftd' => 1,
                        'total_ftd' => 1
                    ];
                    $mongo = new MongoDBObjects('partner', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- broker_integrations ---------- //
            if (isset($lead['integrationId'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['integrationId'])];
                    $update = [
                        'today_revenue' => (int)$lead['deposit_revenue'],
                        'total_revenue' => (int)$lead['deposit_revenue'],
                        'today_ftd' => 1,
                        'total_ftd' => 1
                    ];
                    $mongo = new MongoDBObjects('broker_integrations', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- TrafficEndpoints ---------- //
            if (isset($lead['TrafficEndpoint'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['TrafficEndpoint'])];
                    $update = [
                        'today_revenue' => (int)$lead['deposit_revenue'],
                        'total_revenue' => (int)$lead['deposit_revenue'],
                        'today_deposits' => 1,
                        'total_deposits' => 1,
                        'today_cost' => (int)$lead['cost'],
                        'total_cost' => (int)$lead['cost'],
                    ];
                    $mongo = new MongoDBObjects('TrafficEndpoints', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- campaigns ---------- //
            if (isset($lead['CampaignId'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['CampaignId'])];
                    $update = [
                        'today_revenue' => (int)$lead['deposit_revenue'],
                        'total_revenue' => (int)$lead['deposit_revenue'],
                        'today_deposits' => 1,
                        'total_deposits' => 1,
                        'today_cost' => (int)$lead['cost'],
                        'total_cost' => (int)$lead['cost'],
                    ];
                    $mongo = new MongoDBObjects('campaigns', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- CampaignRules ---------- //
            if (isset($lead['rule_id'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['rule_id'])];
                    $update = [
                        'today_revenue' => (int)$lead['deposit_revenue'],
                        'total_revenue' => (int)$lead['deposit_revenue'],
                        'today_deposits' => 1,
                        'total_deposits' => 1,
                        'today_cost' => $lead['cost'],
                        'total_cost' => $lead['cost'],
                    ];
                    $mongo = new MongoDBObjects('CampaignRules', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- Masters ---------- //
            if (isset($lead['MasterAffiliate'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['MasterAffiliate'])];
                    $update = [
                        'total_cost' => (int)(isset($lead['master_affiliate_payout']) ? $lead['master_affiliate_payout'] : 0),
                        'today_cost' => (int)(isset($lead['master_affiliate_payout']) ? $lead['master_affiliate_payout'] : 0),
                        'today_ftd' => 1,
                        'total_ftd' => 1
                    ];
                    $mongo = new MongoDBObjects('Masters', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }

            // ---------- Masters ---------- //
            if (isset($lead['master_brand'])) {
                try {
                    $where = ['_id' => new \MongoDB\BSON\ObjectId($lead['master_brand'])];
                    $update = [
                        'total_cost' => (int)(isset($lead['master_brand_payout']) ? $lead['master_brand_payout'] : 0),
                        'today_cost' => (int)(isset($lead['master_brand_payout']) ? $lead['master_brand_payout'] : 0),
                        'today_ftd' => 1,
                        'total_ftd' => 1
                    ];
                    $mongo = new MongoDBObjects('Masters', $where);
                    $mongo->findOneAndUpdate($update);
                } catch (\Exception $ex) {
                }
            }
        }
    }

    public function reject(string $leadId): array
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($leadId)];
        $mongo = new MongoDBObjects('leads', $where);
        $lead = $mongo->find();

        $clientId = $lead['clientId'] ?? '';
        $gravity_type = $lead['depositTypeGravity'] ?? '';
        Cache::forget('gravity_' . $gravity_type . '_' . $clientId);

        $update = array();
        $update['deposit_reject'] = true;

        if (!isset($lead['depositTimestamp']) || empty($lead['depositTimestamp'])) {
            $var = date("Y-m-d H:i:s");
            $update['depositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        }

        if ($mongo->update($update)) {
            $data = array();
            $data['success'] = true;
        } else {
            $data = array();
            $data['success'] = false;
        }

        return $data;
    }

    public function approve(string $leadId): array
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($leadId)];
        $mongo = new MongoDBObjects('leads', $where);
        $lead = $mongo->find();

        $gravity_type = $lead['depositTypeGravity'] ?? '';
        $clientId = $lead['clientId'] ?? '';

        Cache::forget('gravity_' . $gravity_type . '_' . $clientId);

        $update = array();

        $update['deposit_disapproved'] = false;
        $update['status'] = 'Depositor';
        $update['deposit_reject'] = false;

        $var = date("Y-m-d H:i:s");
        if (!isset($lead['depositTimestamp']) || empty($lead['depositTimestamp'])) {
            $update['depositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        }

        // this for fake deposit.
        if (!isset($lead['endpointDepositTimestamp'])) {
            $update['endpointDepositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        }

        // if (isset($lead) && isset($lead['deposit_by_pixel']) && $lead['deposit_by_pixel']) {
        //     $var = date("Y-m-d H:i:s");
        //     //ASK
        //     //$update['depositTimestamp'] = new MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        //     $update['endpoint_timestamp'] = new MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        // }

        if ($mongo->update($update)) {

            if (!isset($lead['endpointDepositTimestamp'])) {
                $this->postbackFTD($lead);
            }

            $this->metricFTDs([$lead]);

            $data = array();
            $data['success'] = true;
        } else {
            $data = array();
            $data['success'] = false;
        }
        return $data;
    }

    public function lead_analisis(array $payload): array
    {
        $d = new AnalizeLead();
        return $d->checkLead($payload['leadId']);
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
                    } catch (Exception $ex) {
                        $errors .= '<br/>' . $lead_id . ' is not valid ID';
                    }
                }
            }

            if (!empty($errors)) {
                throw new Exception('Operation aborted. Fix leads with errors: ' . $errors);
            }

            // --- crg_deal --- //
            $where = array();
            $where = ['_id' => ['$in' => $in]];
            $mongo = new MongoDBObjects('leads', $where);
            $leads = $mongo->findMany();

            if ($payload['type'] == 'cpl') {
                $where_check = [
                    '_id' => ['$in' => $in],
                    '$or' => [
                        ['isCPL' => 0],
                        ['isCPL' => false],
                        ['isCPL' => ['$exists' => false]]
                    ]
                ];
                $mongo_check = new MongoDBObjects('leads', $where_check);
                if ($mongo_check->count() > 0) {
                    throw new Exception('Some of the leads is not CPL');
                }
                $mongo->updateMulti(['cost' => 0, 'Mastercost' => 0, 'cpl_unpaid' => true]);

                $fields = ['isCPL'];
                $_data = ['isCPL' => false];
                $category = $payload['type'] ?? 'unknown'; //'crg';
                $reasons_change_crg = $payload['reason_change2'] ?? $payload['reason_change'] ?? '';
                foreach ($leads as $lead) {
                    HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$lead['_id'], '', $fields, $category, $reasons_change_crg);
                }
                $data = ['success' => true];
            } else if ($payload['type'] == 'crg') {

                $where_check = [
                    '_id' => ['$in' => $in],
                    '$or' => [
                        ['crg_deal' => false],
                        ['crg_deal' => ['$exists' => false]]
                    ]
                ];
                $mongo_check = new MongoDBObjects('leads', $where_check);
                if ($mongo_check->count() > 0) {
                    throw new Exception('Some of the leads is not CRG');
                }

                $update = [
                    'crg_deal' => false,
                    'crg_ftd_uncount' => false,
                    'crg_ftd_uncount_timestamp' => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000),
                    'crg_ftd_uncount_handler' => 'manual_un_payable'
                ];

                if ($mongo->updateMulti($update)) {
                    $data = ['success' => true];
                }

                // --- crg_percentage_id --- //
                $where = [];
                foreach ($leads as $lead) {
                    if ($lead['crg_deal']) {
                        $start = null;
                        $end = null;
                        $v = $lead['Timestamp'];
                        $mil = ((array)$v)['milliseconds'];
                        $seconds = $mil / 1000;
                        $dt = date("d-m-Y", $seconds);
                        switch ($lead['crg_percentage_period']) {
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
                                ['crg_deal' => true],
                                ['crg_percentage_id' => $lead['crg_percentage_id']],
                                ['crg_percentage_period' => $lead['crg_percentage_period']],
                                ['Timestamp' => ['$gte' => $start, '$lte' => $end]]
                            ]
                        ];
                        $where['$or'][] = $w;

                        $fields = ['crg_deal'];
                        $_data = ['crg_deal' => false];
                        $category = $payload['type'] ?? 'unknown'; //'crg';
                        $reasons_change_crg = $payload['reason_change2'] ?? $payload['reason_change'] ?? '';
                        HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$lead['_id'], '', $fields, $category, $reasons_change_crg);
                    }
                }
                $mongo = new MongoDBObjects('leads', $where);
                $leads2 = $mongo->findMany();
                if (!isset($leads2)) $leads2 = [];
                // $leads = array_merge((array)$leads, (array)$leads2);

                // --- crg_finall_calculated --- //
                $in = [];
                foreach ($leads2 as $lead) {
                    $lead_id = (array)$lead['_id'];
                    $lead_id = $lead_id['oid'];
                    $in[] = new \MongoDB\BSON\ObjectId($lead_id);
                }
                $where = array();
                $where = ['_id' => ['$in' => $in]];
                $mongo = new MongoDBObjects('leads', $where);
                $update = ['crg_recalculate' => true];
                $mongo->updateMulti($update);
            }
        } catch (Exception $ex) {
            $data = ['success' => false, 'error' => $ex->getMessage()];
        }
        return $data;
    }

    public function broker_simulator(array $payload): array
    {
        $simulator = new ManageSimulator();

        $endpoint_id = $payload['traffic_endpoint'] ?? '';
        $country_code = $payload['country'] ?? '';
        $language_code = $payload['language'] ?? '';

        $group_by_fields = '';
        if (!empty($payload['sub_publisher'] ?? '')) {
            $group_by_fields = $payload['sub_publisher'] . '|||';
        }
        if (!empty($payload['funnel'] ?? '')) {
            $group_by_fields .= (empty($group_by_fields) ? '|||' : '') . $payload['funnel'];
        }

        $data = [
            'success' => true,
            'feed' => $simulator->simulation_graph($endpoint_id, $country_code, $language_code, $group_by_fields)
        ];
        return $data;
    }

    public function download_price(): string
    {
        $d = new DownloadPrice();
        return $d->makeCsv();
    }

    public function download_crgdeals(string $traffic_endpoint_id): string
    {
        $d = new DownloadCRG();
        return $d->makeCsv();
    }

    public function response_tools(array $payload): array
    {
        $query = LeadRequests::query()->where('traffic_endpoint_id', '=', $payload['endpoint']);
        if ($payload['type'] == 'emails') {
            $query = $query->where('email', '=', CryptHelper::encrypt(strtolower(trim($payload['data']))));
        }
        if ($payload['type'] == 'token') {
            $query = $query->where('token', '=', $payload['data']);
        }
        $data = $query->get()->toArray();
        foreach ($data as &$d) {
            CryptHelper::decrypt_lead_data_array($d);
        }
        return $data;
    }

    public function log(string $modelId, $limit = 20): array
    {
        $where = [
            'primary_key' => new \MongoDB\BSON\ObjectId($modelId),
            'collection' => 'TrafficEndpoints',
            'data.today_revenue' => ['$exists' => false]
        ];
        $mongo = new MongoDBObjects('history', $where);
        $history_logs = $mongo->findMany([
            'limit' => $limit,
            'sort' => ['timestamp' => -1]
        ]);

        $diff = new HistoryDiff;
        $response = [];

        for ($i = 0; $i < count($history_logs); $i++) {
            $history = $history_logs[$i];

            $diff->init($history, $history_logs[$i - 1] ?? null, false);

            $diff_data = array_filter([
                $diff->value('aff_dashboard_pass', 'Affiliate Password', false),
                $diff->value('billing_manual_status', 'Billing Manual Status'),
                $diff->value('blacklist', 'Black List'),
                $diff->value('whitelist', 'White List'),
                $diff->value('company_type', 'Endpoint Type'),
                $diff->value('country', 'Country'),
                // $diff->value('dashboard_permissions', 'Enabled'),
                $diff->value('endpoint_status', 'Endpoint Status'),
                $diff->value('endpoint_type', 'Endpoint Type'),
                $diff->value('lead_postback', 'Postback - Lead'),
                $diff->value('postback', 'Endpoint Postbacks'),
                // $diff->value('marketing_suite_offers', 'Enabled'),
                // $diff->value('marketing_suite_traffic_endpoinds', 'Enabled'),
                $diff->value('master_partner', 'Assigned to Master Partner'),
                $diff->value('redirect_24_7', 'Redirect Logic'),
                $diff->value('send_mismatch_again', 'Send Mismatch Again'),
                $diff->value('status', 'Status'),
                $diff->value('statusDeposit', 'Deposits'),
                $diff->value('statusMatching', 'Approved leads'),
                $diff->value('statusReporting', 'Reporting'),

                $diff->array('traffic_sources', 'Traffic Sources'),
                $diff->array('replace_funnel_list', 'Replace Funnel List'),
                $diff->array('blocked_funnels', 'Funnel Restrictions'),
                $diff->array('permissions', 'Permissions'),
                $diff->brokers('restricted_brokers', 'Restricted Brokers'),
                $diff->restricted_brokers_by_country('restricted_brokers_by_country', 'Restricted Brokers By Country'),
                // $diff->value('probation', 'Probation'),
                $diff->value('account_manager', 'Account Manager'),
            ]);

            // GeneralHelper::Dump($diff_data);die();

            $item = [
                'timestamp' => $history['timestamp'],
                'action' => $history['action'],
                'changed_by' => User::query()->find((string)$history['action_by'])->name,
                'data' => implode(', ', $diff_data)
            ];

            // if (!empty($item['data'])) {
            $response[] = $item;
            // }
        }

        return $response;
    }
}
