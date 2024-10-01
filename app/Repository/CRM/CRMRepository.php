<?php

namespace App\Repository\CRM;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Models\Leads;
use App\Models\Broker;
use App\Helpers\CryptHelper;

use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Repository\BaseRepository;
use App\Models\Brokers\BrokerStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Repository\CRM\ICRMRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Classes\DownloadRecalculationChanges;

class CRMRepository extends BaseRepository implements ICRMRepository
{

    public function __construct()
    {
    }

    private function parserTimePost($time)
    {

        $data = explode("-", $time);
        if (count($data) > 1) {
            $start_range = trim($data[0]);
            $end_range = trim($data[1]);

            $explode = explode("/", $start_range);
            if (count($explode) > 1) {
                $start_date = $explode[2] . '-' . $explode[0] . '-' . $explode[1];
            } else {
                $start_date = $start_range;
            }

            $explode2 = explode("/", $end_range);
            if (count($explode2) > 1) {
                $end_date = $explode2[2] . '-' . $explode2[0] . '-' . $explode2[1];
            } else {
                $end_date = $end_range;
            }

            $array = array();
            $array['start'] = $start_date;
            $array['end'] = $end_date;
        }

        return $array;
    }

    private function leads(array $payload, string $type): array
    {
        // TODO: Access
        // if (!permissionsManagement::is_allow('crm', ['all', 'view'])) {
        //     return ['success' => false, 'message' => permissionsManagement::get_error_message()];
        // }

        // TODO: Access
        // $approved_streamData = 0;

        // if ($this->user['account_email'] == 'mike@ppcnation.media') {
        // 	$approved_streamData = 1;
        // }

        $where = [];

        $time = $this->parserTimePost($payload['timeframe']);
        $start = new \MongoDB\BSON\UTCDateTime(strtotime($time['start'] . " 00:00:00") * 1000);
        $end = new \MongoDB\BSON\UTCDateTime(strtotime($time['end'] . " 23:59:59") * 1000);

        $status_names = Leads::status_names();
        $broker_status_names = BrokerStatus::status_names();

        $where['$and'] = [];

        if ($type == 'deposits') {
            // $where['depositTimestamp'] =  array('$gte' => $start, '$lte' => $end);
            // $where['depositor'] = true;
            $where['$or'] = [
                ['depositTimestamp' => ['$gte' => $start, '$lte' => $end]],
                ['endpointDepositTimestamp' =>  ['$gte' => $start, '$lte' => $end]]
            ];
        } else {
            $where['Timestamp'] =  array('$gte' => $start, '$lte' => $end);
        }

        if ($type == 'mismatch') {
            $where['match_with_broker'] = 0;
        } else {
            $where['match_with_broker'] = 1;
        }

        $find_crypt_or_original = function (string $name) use ($payload) {
            return ['$or' => [
                [$name => new \MongoDB\BSON\Regex(preg_quote(CryptHelper::encrypt(strtolower(trim($payload[$name] ?? '')))), "i")],
                [$name => new \MongoDB\BSON\Regex(preg_quote(strtolower(trim($payload[$name] ?? ''))), "i")]
            ]];
        };

        if ($type == 'deposits' && Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
            $where['deposit_disapproved'] =  false;
        }
        // }

        if (!empty($payload['country'])) {
            // $country_array = array();
            // $country_array[] = array('country' => strtoupper($payload['country']));
            // $country_array[] = array('country' => strtolower($payload['country']));

            // $where['$and'][] = ['$or' => $country_array];
            $where['country'] = strtoupper($payload['country']);
        }

        if (!empty($payload['language'])) {
            // $language_array = array();
            // $language_array[] = array('language' => strtoupper($payload['language']));
            // $language_array[] = array('language' => strtolower($payload['language']));

            // $where['$and'][] = ['$or' => $language_array];
            $where['language'] = strtolower($payload['language']);
        }

        if (!empty($payload['traffic_endpoint'])) {
            $where['TrafficEndpoint'] = trim($payload['traffic_endpoint']); //new \MongoDB\BSON\Regex(preg_quote(trim($payload['traffic_endpoint'])), "i");
        }

        if (!empty($payload['campaign'])) {
            $where['CampaignId'] = $payload['campaign'];
        }

        if (!empty($payload['broker'])) {
            $where['brokerId'] = $payload['broker'];
        }

        if (isset($payload['first_name']) and !empty($payload['first_name'])) {
            // $where['first_name'] = new \MongoDB\BSON\Regex(preg_quote(CryptHelper::encrypt(strtolower(trim($payload['first_name'])))), "i");
            $where['$and'][] = $find_crypt_or_original('first_name');
        }

        if (isset($payload['last_name']) and !empty($payload['last_name'])) {
            // $where['last_name'] = new \MongoDB\BSON\Regex(preg_quote(CryptHelper::encrypt(strtolower(trim($payload['last_name'])))), "i");
            $where['$and'][] = $find_crypt_or_original('last_name');
        }

        if (isset($payload['email']) and !empty($payload['email'])) {
            // $where['email'] = new \MongoDB\BSON\Regex(preg_quote(CryptHelper::encrypt(strtolower(trim($payload['email'])))), "i");
            $where['$and'][] = $find_crypt_or_original('email');
        }

        if (isset($payload['phone']) and !empty($payload['phone'])) {
            // $where['phone'] = CryptHelper::encrypt(trim($payload['phone']));
            $where['$and'][] = $find_crypt_or_original('phone');
        }

        if (isset($payload['sub_publisher']) and !empty($payload['sub_publisher'])) {
            $where['sub_publisher'] = new \MongoDB\BSON\Regex(preg_quote(strtolower(trim($payload['sub_publisher']))), "i");
        }

        if (isset($payload['broker_lead_id']) and !empty($payload['broker_lead_id'])) {
            // $broker_lead_id = trim($payload['broker_lead_id']);
            // if (is_numeric($broker_lead_id)) {
            // $where['broker_lead_id'] = new \MongoDB\BSON\Decimal128(trim($payload['broker_lead_id']));
            // } else {
            $where['broker_lead_id'] = trim($payload['broker_lead_id']);
            // }
        }

        if (isset($payload['lead_id']) and !empty($payload['lead_id'])) {
            $where['_id'] = new \MongoDB\BSON\ObjectId($payload['lead_id']);
        }

        if (isset($payload['click_id']) and !empty($payload['click_id'])) {
            $where['publisher_click'] = $payload['click_id'];
        }

        if (isset($payload['publisher_click']) and !empty($payload['publisher_click'])) {
            $where['publisher_click'] = $payload['publisher_click'];
        }

        if (isset($payload['status']) and !empty($payload['status'])) {

            $status = $status_names[$payload['status'] ?? ''] ?? [];

            if (preg_match('/Depositor/i', $status['title'])) {
                $where['depositor'] = true;
            } else {
                $status = '^' . $status['regex'] . '$';
                $status = str_replace(' ', '[\s]+', $status);
                $where['status'] = new \MongoDB\BSON\Regex($status, "i");
            }
        }

        if (isset($payload['broker_status']) and !empty($payload['broker_status'])) {

            $status = $broker_status_names[$payload['broker_status'] ?? ''] ?? '';

            if (preg_match('/Depositor/i', $status)) {
                $where['depositor'] = true;
            } else {
                $status = '^' . $status . '$';
                $status = str_replace(' ', '[\s]+', $status);
                $where['broker_status'] = new \MongoDB\BSON\Regex($status, "i");
            }
        }

        if (isset($payload['crg']) and !empty($payload['crg'])) {
            switch ($payload['crg']) {
                case 'crg': {
                        $where['crg_deal'] = true;
                        break;
                    }
                case 'no_crg': {
                        $where['crg_deal'] = false;
                        $where['crg_percentage_id'] = ['$exists' => false];
                        break;
                    }
                case 'removed_crg': {
                        $where['crg_deal'] = false;
                        $where['$and'][] = ['crg_percentage_id' => ['$ne' => null]];
                        $where['$and'][] = ['crg_percentage_id' => ['$ne' => '']];
                        break;
                    }
            }
        }

        if (isset($payload['broker_crg']) and !empty($payload['broker_crg'])) {
            $where['test_lead'] = 0;
            switch ($payload['broker_crg']) {
                case 'crg': {
                        $where['broker_crg_deal'] = true;
                        break;
                    }
                case 'no_crg': {
                        $where['broker_crg_deal'] = false;
                        $where['broker_crg_percentage_id'] = ['$exists' => false];
                        break;
                    }
                case 'removed_crg': {
                        $where['broker_crg_deal'] = false;
                        $where['$and'][] = ['broker_crg_percentage_id' => ['$ne' => null]];
                        $where['$and'][] = ['broker_crg_percentage_id' => ['$ne' => '']];
                        break;
                    }
            }
        }

        if (count($where['$and']) == 0) {
            unset($where['$and']);
        }

        $broker = [];
        $brokerIds = [];
        $query = Broker::query()->where('partner_type', '=', '1');
        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_brokers = Broker::query()->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id', 'created_by', 'account_manager', 'partner_name', 'token']);
            foreach ($allow_brokers as $b) {
                $in[] = $b->_id;
                $brokerIds[] = $b->_id;
                $broker[$b->_id] = GeneralHelper::broker_name($b);
            }

            if (is_string($where['brokerId'] ?? '') && !empty($where['brokerId'])) {
                $in[] = $where['brokerId'];
            }

            if (empty($in)) {
                $in = ['nothing'];
            }

            $where['brokerId'] = ['$in' => $in];
        } else {
            $allow_brokers = $query->get(['_id', 'created_by', 'account_manager', 'partner_name', 'token']);
            foreach ($allow_brokers as $b) {
                $brokerIds[] = $b->_id;
                $broker[$b->_id] = GeneralHelper::broker_name($b);
            }
        }

        // traffic endpoints
        $endpoints = [];
        $query = TrafficEndpoint::query();
        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_endpoints = $query->orWhere('user_id', '=', $current_user_id)->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id', 'token']);
            foreach ($allow_endpoints as $endpoint) {
                $in[] = $endpoint->_id;
                $endpoints[strtolower($endpoint->_id)] = $endpoint->token ?? '';
            }
            if (is_string($where['TrafficEndpoint'] ?? '') && !empty($where['TrafficEndpoint'])) {
                $in[] = $where['TrafficEndpoint'];
            }
            if (empty($in)) {
                $in = ['nothing'];
            }
            $where['TrafficEndpoint'] = ['$in' => $in];
        } else {
            $allow_endpoints = $query->get(['_id', 'token']);
            foreach ($allow_endpoints as $endpoint) {
                $endpoints[strtolower($endpoint->_id)] = $endpoint->token ?? '';
            }
        }

        // broker_integrations
        $mongo = new MongoDBObjects('broker_integrations', ['partnerId' => ['$in' => $brokerIds]]);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
        $integrations = [];
        foreach ($find as $integration) {
            $integrations[MongoDBObjects::get_id($integration)] = GeneralHelper::broker_integration_name($integration); //$integration['name'] ?? '';
        }

        // campaigns
        $mongocampaign = new MongoDBObjects('campaigns', []);
        $findcampaign = $mongocampaign->findMany(['projection' => ['_id' => 1, 'name' => 1]]);

        $campaigns = array();

        foreach ($findcampaign as $end) {
            $idend = (array)$end['_id'];
            $oidend = $idend['oid'];

            $campaigns[strtolower($oidend)] = $end['name'];
        }

        $mongo = new MongoDBObjects('leads', $where);

        $args = [];
        if ($type == 'deposits') {
            $args = ['sort' => ['depositTimestamp' => -1]];
        } else {
            $args = ['sort' => ['Timestamp' => -1]];
        }
        $find = $mongo->findMany($args);

        $result = [];

        foreach ($find as &$lead) {

            CryptHelper::decrypt_lead_data_array($lead);

            $ep = strtolower($lead['TrafficEndpoint']);

            if (!array_key_exists($ep, $endpoints)) continue;

            $str_to_replace = "*****";
            $input_str = $phone = $lead['phone'];
            if (!Gate::allows('role:admin')) {
                $phone = $str_to_replace . substr($input_str, 5);
            }

            $string = $email = $lead['email'];
            // if (!Gate::allows('role:admin')) {
            // 	$explode = explode('@', $string);
            // 	$email = $str_to_replace . '@' . $explode[1];
            // }

            $status_style = '';
            if (($lead['fakeDepositor'] ?? false) == true) {
                $status_td = 'Fake Depositor';
                $status_style = 'background-color: #0a6183;color:white;';
            } elseif ($lead['depositor'] == true) {
                $status_td = 'Depositor';
                if (!Gate::allows('custom:crm[crm_depositors]') || (Gate::allows('custom[deposit_disapproved]') && $lead['deposit_disapproved'] == true)) {
                    $lead['depositor'] = false;
                    $status_td = 'No Answer'; //'Call back - Personal';
                    $lead['broker_status'] = 'No Answer'; //'Call back - Personal';
                }
            } elseif ($lead['match_with_broker'] !== 1) {
                $status_td = 'Mismatch';
                $status_style = 'background-color: #fff6f6;';
            } else {
                $status_td = $lead['status'] ?? '';
            }

            if (isset($payload['status']) && !empty($payload['status'])) {
                $status = '^' . $status_names[$payload['status']]['regex'] . '$';
                $status = str_replace(' ', '[\s]+', $status);
                if (!preg_match('/' . $status . '/i', $status_td)) {
                    continue;
                }
            }

            if (isset($payload['broker_status']) && !empty($payload['broker_status'])) {
                $status = '^' . $broker_status_names[$payload['broker_status']] . '$';
                $status = str_replace(' ', '[\s]+', $status);
                if (!preg_match('/' . $status . '/i', $status_td)) {
                    continue;
                }
            }

            if (Gate::allows('custom:crm[deposit_disapproved_bg]') && isset($lead['depositor']) && $lead['depositor'] == true  &&  $lead['deposit_disapproved'] == true) {
                $status_style = 'color:#e62864;';
            }

            $enable_reapprove = false;
            if (Gate::allows('custom:crm[deposit_reapprove]') && isset($lead['depositor']) && $lead['depositor'] == true  &&  $lead['deposit_disapproved'] == true) {
                // $status_style = 'color:#e62864;';
                $enable_reapprove = true;
            }

            // $enable_reapprove = false;
            // if (Gate::allows('custom[deposit_reapprove]')) {
            // $enable_reapprove = true;
            // }

            $reSyncFromTrafficEndpoint = null;
            if (!empty($lead['reSyncFromTrafficEndpoint'])) {
                $reSyncFromTrafficEndpoint = TrafficEndpoint::findOrFail($lead['reSyncFromTrafficEndpoint']);
            } else if (!empty($lead['reSyncFrom'])) {
                /*reSync + new endpoint  - But it is the same Endpoint*/
                $reSyncFromTrafficEndpoint = [
                    'token' => $endpoints[$ep]
                ];
            }

            $blockReasons = [];

            $dt_lead = ((array)$lead['Timestamp'])['milliseconds'] / 1000; //date('Y-m-d H:i:s',
            if ($dt_lead <= strtotime('2023-03-19 00:00:00')) {
                if (($lead['real_ip'] ?? '') != ($lead['ip'] ?? '') && ($lead['userCountryMatch'] ?? true) == false) {
                    $blockReasons[] = 'Block Reason: income country and real country is different';
                }
            } else {
                if (isset($lead['blockReasons'])) {
                    $blockReasons = $lead['blockReasons'] ?? [];
                } else {
                    if (($lead['userCountryMatch'] ?? true) == false) {
                        $blockReasons[] = 'Block Reason: income country and real country is different';
                    }
                }
            }

            $item = [
                "_id" => MongoDBObjects::get_id($lead),
                'first_name' => ucfirst($lead['first_name'] ?? ''),
                'last_name' => ucfirst($lead['last_name'] ?? ''),
                'email' => $email,
                "country" => $lead['country'] ?? '',
                "language" => $lead['language'] ?? '',
                "broker" => [
                    "_id" => ($lead['brokerId'] ?? ''),
                    "name" => ($broker[$lead['brokerId'] ?? ''] ?? ''),
                ],
                "broker_lead_id" => ($lead['broker_lead_id'] ?? ''),
                "integration" => [
                    "_id" => ($lead['integrationId'] ?? ''),
                    "name" => ($integrations[$lead['integrationId'] ?? ''] ?? ''),
                ],
                // "broker_status" => ($broker_status_names[$lead['broker_status'] ?? ''] ?? ''),
                "broker_status" => $lead['broker_status'] ?? '',
                "status" => $status_td, //$lead['status'],
                "status_style" => $status_style,
                "enable_reapprove" => $enable_reapprove,
                "depositor" => $lead['depositor'],
                "fakeDepositor" => (bool)($lead['fakeDepositor'] ?? false),
                "test_lead" => $lead['test_lead'] ?? 0,
                "sub_publisher" => $lead['sub_publisher'] ?? '',
                "match_with_broker" => $lead['match_with_broker'],
                "isCPL" => $lead['isCPL'] ?? false,
                "broker_cpl" => $lead['broker_cpl'] ?? false,
                "cost" => $lead['cost'] ?? 0,
                "revenue" => $lead['revenue'] ?? 0,
                "Master_brand_cost" => $lead['Master_brand_cost'] ?? 0,
                "Mastercost" => $lead['Mastercost'] ?? 0,
                // TODO: Access
                // $html .= '<td style="' . $tdback . ' ' . (customUserAccess::is_allow('deposit_disapproved_bg') && isset($lead['depositor']) && $lead['depositor'] == true  &&  $lead['deposit_disapproved'] == true ? 'background-color:#ff002f!important;color:#fff;' : '') . '" class="d-none d-md-table-cell">' . $status_td . '</td>';
                "funnel_lp" => $lead['funnel_lp'],
                "hit_the_redirect" => ($lead['hit_the_redirect'] ?? ''),
                "riskScore" => ($lead['riskScore'] ?? null),
                "riskScale" => ($lead['riskScale'] ?? null),
                "riskFactorScore" => ($lead['riskFactorScore'] ?? null),
                "riskFactorScoreData" => ($lead['riskFactorScoreData'] ?? null),
                "crg_deal" => ($lead['crg_deal'] ?? false),
                "crg_percentage_id" => ($lead['crg_percentage_id'] ?? ''),
                "crg_ignored_by_status" => ($lead['crg_ignored_by_status'] ?? false),
                "broker_crg_deal" => ($lead['broker_crg_deal'] ?? false),
                "broker_crg_percentage_id" => ($lead['broker_crg_percentage_id'] ?? ''),
                "broker_crg_ignored_by_status" => ($lead['broker_crg_ignored_by_status'] ?? false),
                'scrubFrom' => $lead['scrubFrom'] ?? null,
                'scrubSourceId' => $lead['scrubSourceId'] ?? null,
                'scrubSource' => $lead['scrubSource'] ?? null,
                'reSyncFrom' => $lead['reSyncFrom'] ?? null,
                'reSyncFromTrafficEndpoint' => $reSyncFromTrafficEndpoint,
                "endpoint" => [
                    "name" => $endpoints[$ep],
                    "_id" => $ep,
                ],
                "Timestamp" => $lead['Timestamp'],
                "depositTimestamp" => ($lead['depositTimestamp'] ?? ($lead['endpointDepositTimestamp'] ?? '')),
                'ip' => $lead['ip'] ?? null,
                'real_ip' => $lead['real_ip'] ?? null,
                // 'country' => $lead['country'] ?? null,
                'real_country' => $lead['real_country'] ?? null,
                'userIpMatch' => $lead['userIpMatch'] ?? null,
                'userCountryMatch' => $lead['userCountryMatch'] ?? null,
                'blockReasons' => $blockReasons,
            ];

            // if (!Gate::allows('custom:crm[lead_email]')){
            // unset($item['email']);
            // $item['email'] = '****';
            // }

            // if ($type == 'mismatch' && !Gate::allows('custom:crm[mismatch_timestamp]')){
            // unset($item['broker_status']);
            // $item['Timestamp'] = '****';
            // }

            if (!Gate::allows('custom:crm[show_broker_statuses]')) {
                $item['broker_status'] = '****';
            }

            if ($type == 'deposits') {
                unset($item['broker_status']);
            }

            $result[] = $item;
        }

        return $result;
    }

    public function all_leads(array $payload): array
    {
        return $this->leads($payload, 'all');
    }

    public function deposits(array $payload): array
    {
        return $this->leads($payload, 'deposits');
    }

    public function mismatch(array $payload): array
    {
        return $this->leads($payload, 'mismatch');
    }

    public function status_lead_history(string $leadId): array
    {

        $result = [];
        try {
            $id = $leadId;
            $limit = 20;
            $where = [
                'primary_key' => new \MongoDB\BSON\ObjectId($id),
                'action2' => 'LEAD_STATUS'
            ];
            $mongo = new MongoDBObjects('history', $where);
            $history_logs = $mongo->findMany([
                'sort' => ['timestamp' => -1],
                'limit' => $limit
            ]);

            $action = [
                'INSERT' => 'table-success',
                'UPDATE' => 'table-warning',
                'DELETE' => 'table-danger',
            ];

            $show_broker_statuses = Gate::allows('custom:crm[show_broker_statuses]');

            $render_history = function () use ($action, $history_logs, $show_broker_statuses) {
                $result = [];

                foreach ($history_logs as $history) {
                    $item = [
                        'date' => $history['timestamp'],
                        'action' => $history['action'],
                        'status' => $history['data']['status'],
                        // 'broker_status' => $history['data']['broker_status']
                    ];

                    if ($show_broker_statuses) {
                        $item['broker_status'] = $history['data']['broker_status'];
                    }

                    $result[] = $item;
                }
                return $result;
            };

            //   $render_history.length>0 && !$show_broker_statuses &&

            $result = $render_history();
            if (count($result) > 0 && Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
                $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
                $mongo = new MongoDBObjects('leads', $where);
                $lead = $mongo->find();
                if ($lead['deposit_disapproved'] == true && $lead['depositor'] == true) {
                    $dt = date('Y-m-d H:i:s', ((array)$lead['Timestamp'])['milliseconds'] / 1000);
                    $result = [
                        [
                            'date' => $dt,
                            'action' => 'UPDATE',
                            'status' => 'No Answer',
                            'broker_status' => 'No Answer'
                        ]
                    ];
                }
            }

            // TODO: Access
            // if (customUserAccess::is_forbidden('deposit_disapproved')) {
            // if (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {

            // 	$where = [
            // 		'_id' => new \MongoDB\BSON\ObjectId($id)
            // 	];
            // 	$mongo = new MongoDBObjects('leads', $where);
            // 	$lead = $mongo->find();

            // 	if ($lead['deposit_disapproved'] == true && $lead['depositor'] == true) {
            // 		$dt = date('Y-m-d H:i:s', ((array)$lead['Timestamp'])['milliseconds'] / 1000);
            // 		$html .= '<tr class="table-warning" data-id="" data-collection="leads">' .
            // 			'<td>' . $dt . '</td>' .
            // 			'<td>UPDATE</td>' .
            // 			'<td>Call back - Personal</td>' .
            // 			'<td>Call back - Personal</td>' .
            // 			'</tr>';
            // 	} else {
            // 		$result = $render_history();
            // 	}
            // } else {
            // $result = $render_history();
            // }

        } catch (\Exception $ex) {
            $result['error'] = $ex->getMessage();
        }

        return $result; //collect
    }

    public function reject(array $payload): array
    {
        $data = ['sucsees' => true, '_id' => 2, 'action' => 'reject'];
        return ($data);
    }
    public function approve(array $payload): array
    {
        $data = ['sucsees' => true, '_id' => 1, 'action' => 'approve'];
        return ($data);
    }

    private function fetch_integrations()
    {
        $integration = array();
        $where = array();
        $where['status'] = '1';

        $mongo = new MongoDBObjects('broker_integrations', $where);
        $array = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1]]);

        $broker_ids = [];
        foreach ($array as $integration_broker) {
            $broker_ids[] = new \MongoDB\BSON\ObjectId($integration_broker['partnerId']);
        }

        // brokers
        $where = ['_id' => ['$in' => $broker_ids]];
        $mongo = new MongoDBObjects('partner', $where);
        $brokers = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);

        foreach ($array as $integrations) {
            $a = (array)$integrations['_id'];

            $broker_name = '';
            foreach ($brokers as $broker) {
                $id_array = (array)$broker['_id'];
                $id = $id_array['oid'];
                if ($integrations['partnerId'] == $id) {
                    $broker_name = GeneralHelper::broker_name($broker);
                    break;
                }
            }

            $integration[$a['oid']] = $broker_name . ' - ' . GeneralHelper::broker_integration_name($integrations); //$integrations['name'];
        }

        return $integration;
    }

    public function get_resync(array $ids): array
    {
        $integrations = $this->fetch_integrations();

        $in = [];
        foreach ($ids as $id) {
            $in[] = new \MongoDB\BSON\ObjectId($id);
        }
        $where = ['_id' => ['$in' => $in]];

        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('crm[is_only_assigned=1]')) {
            $where['user_id'] = Auth()->id();
        }

        $mongo = new MongoDBObjects('leads', $where);
        $leads = $mongo->findMany(['projection' => ['first_name' => 1, 'last_name' => 1, 'country' => 1]]);
        foreach ($leads as &$lead) {
            CryptHelper::decrypt_lead_data_array($lead);
            $lead['_id'] = (string)$lead['_id'];
        }

        return [
            'success' => true,
            'leads' => $leads,
            'integrations' => $integrations,
        ];
    }

    public function resync(array $payload): array
    {
        $traffic_endpoint = $payload['endpoint'] ?? '';
        $interval = $payload['interval'];
        //$leadid = $this->request->post('leadid');

        $leads = array();
        foreach ($payload['integrations'] as $leadId => $integrationId) {
            if (!empty($leadId) && !empty($integrationId)) {
                $lead = array();
                $lead['id'] = $leadId;
                $lead['integration'] = $integrationId;
                $leads[] = $lead;
            }
        }

        //$mongo = new MongoDBObjects('Tasks', []);
        //$mongo->deleteMany();

        $array = array();

        //$array['task_type'] = - new_lead_assignment
        $array['status'] = 0;
        $array['created_by'] = Auth()->id();

        $var = date("Y-m-d H:i:s");
        $array['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000); //( Timestamp created the task )
        //$array['finish_at'] =  //( timestamp when task)
        //$array['data'] = //( we will have json here with the following structure)

        $array['task'] = $payload['task_lps'] ?? '1'; // can be also 2
        $array['status_new_endpoint'] = 2; // can be also 2
        $array['endpoint'] = '';
        if (!empty($traffic_endpoint)) {
            $array['endpoint'] = $traffic_endpoint; // can be also empty
            $array['status_new_endpoint'] = 1;
        }
        $array['interval_status'] = (empty($interval) ? 2 : 1); // can be also 2
        $array['interval'] = $interval; // value between 1 - 20
        $array['leads'] = $leads;

        $mongo = new MongoDBObjects('Tasks', $array);
        $insert = $mongo->insertWithToken();
        // $insert = 0;

        return ['success' => true, 'id' => $insert];

        /////----- some old code from Matan -----//////
        /*$counter = count($_POST['leadid']) - 1;

        $array = array();
        for ($x = 0; $x <= $counter; $x++) {
            $lead = array();
            $lead['id'] = $_POST['leadid'][$x];
            $lead['integration'] = $_POST['integration'][$x];

            $array[] = $lead;
        }

        foreach ($array as $lead) {
            echo $url = 'http://leadpoint.jimmywho.media/lead_stream?id=' . $lead['id'] . '&bid=' . $lead['integration'];
            file_get_contents($url);
        }*/
        /////----- some old code from Matan -----//////
    }

    public function download_recalculation_changes_log(array $payload): string
    {
        $leadIds = $payload['ids'];
        $d = new DownloadRecalculationChanges($leadIds);
        return $d->makeCsv();
    }
}
