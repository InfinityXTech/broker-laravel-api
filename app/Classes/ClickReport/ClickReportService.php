<?php

namespace App\Classes\ClickReport;

use App\Models\User;
use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;

ini_set('memory_limit', '4048M');

class ClickReportService extends ClickReportMeta
{

    public $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
        parent::__construct();
    }

    private function get_endpoint_account_managers()
    {
        $where = ['account_manager' => ['$nin' => [null, '']]];
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $find = $mongo->findMany();
        $account_managers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $account_managers[strtolower($id['oid'])] = $supply['account_manager'];
        }
        return $account_managers;
    }

    public function Handler(): array
    {

        // TODO: access
        // if (!permissionsManagement::is_allow('click_reports', ['all', 'view'])) {
        //     return ['success' => false, 'message' => permissionsManagement::get_error_message()];
        // }

        //$mongo = new MongoDBObjects('traffic_analysis',[]);
        //print_r($mongo->findMany());die();

        $time = $this->buildTimestamp();
        $query = $this->buildParameterArray();

        $conditions = array();
        if (isset($this->payload['broker']) && count($this->payload['broker']) > 0) {
            $conditions['brokerId'] = ($conditions['brokerId'] ?? []) + $this->payload['broker'];
        }

        if (isset($this->payload['traffic_endpoint']) && count($this->payload['traffic_endpoint']) > 0) {
            $conditions['TrafficEndpoint'] = ($conditions['TrafficEndpoint'] ?? []) + $this->payload['traffic_endpoint'];
        }

        if (isset($this->payload['campaign']) && count($this->payload['campaign']) > 0) {
            $conditions['CampaignId'] = ($conditions['CampaignId'] ?? []) + $this->payload['campaign'];
        }

        if (isset($this->payload['real_country']) && count($this->payload['real_country']) > 0) {
            $conditions['real_country'] = ($conditions['real_country'] ?? []) + $this->payload['real_country'];
        }

        if (isset($this->payload['account_manager'])) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }
            foreach ($account_managers as $endpoint => $account_manager) {
                if ($account_manager == $this->payload['account_manager']) {
                    // if (in_array($account_manager, $this->payload['account_manager'])) {
                    $conditions['TrafficEndpoint'][] = $endpoint;
                }
            }
        }

        // traffic endpoints is only assing
        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            list($traffic_endpoints, $account_managers) = $this->getTrafficEndpoints();
            $traffic_endpoint_ids = array_keys($traffic_endpoints);
            if (empty($traffic_endpoint_ids)) {
                $traffic_endpoint_ids[] = 'nothing';
            }
            $conditions['TrafficEndpoint'] ??= [];
            foreach ($traffic_endpoint_ids as $traffic_endpoint_id) {
                $conditions['TrafficEndpoint'][] = $traffic_endpoint_id;
            }
        }

        // brokers is only assign
        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $brokers = $this->getBrokers();
            $broker_ids = array_keys($brokers);
            if (empty($broker_ids)) {
                $broker_ids[] = 'nothing';
            }
            $conditions['brokerId'] ??= [];
            foreach ($broker_ids as $broker_id) {
                $conditions['brokerId'][] = $broker_id;
            }
        }

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, 'traffic_analysis', $query['query'], $condition);

        $hooks = [];

        if (in_array('account_manager', ($this->payload['pivot'] ?? []))) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }
            $hooks[] = function (&$cell) use ($account_managers) {
                $cell['account_manager'] = $account_managers[$cell['TrafficEndpoint']];
            };
        }
        if (!in_array('TrafficEndpoint', ($this->payload['pivot'] ?? []))) {
            $hooks[] = function (&$cell) {
                unset($cell['TrafficEndpoint']);
            };
        }

        $args = [];

        if (!empty($hooks)) {
            $args['hook_cell_data'] = function (&$cell) use ($hooks) {
                foreach ($hooks as $hook) {
                    $hook($cell);
                }
            };
        }

        $data = $queryMongo->queryMongo($args);

        if (isset($data['result'])) {
            $data = $data['result'];
        }
        $f = $this->attachFormula($data, $query['formula']);

        return $this->buildView($f);
    }

    private function getTrafficEndpoints(): array
    {
        $cache_key = 'click_report_traffic_endpoints_' . Auth::id();
        $result = Cache::get($cache_key);
        if ($result) {
            return $result;
        }

        $traffic_endpoints = [];
        $account_managers = [];

        $twhere = [];
        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $twhere['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
                ['created_by' => $user_token],
            ];
        }

        $mongo = new MongoDBObjects('TrafficEndpoints', $twhere);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'account_manager' => 1]]);

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $traffic_endpoints[$id['oid']] = ($supply['token'] ?? '');

            $account_manager = $supply['account_manager'] ?? '';
            if (!empty($account_manager)) {
                $account_managers[strtolower($account_manager)] = User::query()->find($account_manager)->name;
            }
        }

        $result = [$traffic_endpoints, $account_managers];
        Cache::set($cache_key, $result, 60);
        return $result;
    }

    private function getBrokers(): array
    {
        $cache_key = 'click_report_brokers_' . Auth::id();
        $result = Cache::get($cache_key);
        if ($result) {
            return $result;
        }

        $partner = [];
        $where = array();
        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        }
        $mongo = new MongoDBObjects('partner', $where);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $partner[$id['oid']] = GeneralHelper::broker_name($supply);
        }

        Cache::set($cache_key, $partner, 60);

        return $partner;
    }

    public function buildView($data): array
    {
        $result = [
            'columns' => [],
            'items' => [],
            'callback' => fn () => null,
        ];

        if (count($data) == 0) {
            return $result;
        }

        $where = array();
        $mongo = new MongoDBObjects('campaigns', $where);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'name' => 1]]);
        $campaigns = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $campaigns[$id['oid']] = $supply['name'];
        }

        // traffic endpoints
        list($traffic_endpoints, $account_managers) = $this->getTrafficEndpoints();

        // brokers
        $partner = $this->getBrokers();

        // integrations
        $brokerIds = array_keys($partner);
        if (empty($brokerIds)) {
            $brokerIds[] = 'nothing';
        }
        $where = ['partnerId' => ['$in' => $brokerIds]];
        $mongo =  new MongoDBObjects('broker_integrations', $where);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'created_by' => 1, 'account_manager' => 1, 'name' => 1]]);

        $integrations = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $integrations[$id['oid']] = GeneralHelper::broker_integration_name($supply); //($supply['name'] ?? '');
        }

        $where = array();
        $mongo = new MongoDBObjects('Masters', $where);
        $find = $mongo->findMany();
        $masters = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $masters[$id['oid']] = ($supply['token'] ?? '');
        }

        $metrics = $this->payload['metrics'];
        $fields = $data[0];

        $except = ['value'];
        if (!in_array('TrafficEndpoint', ($this->payload['pivot'] ?? []))) {
            $except[] = 'TrafficEndpoint';
        }
        $schema = QueryHelper::schemaTitles($this->get_titles(), $data[0], $except);
        $DataSchema = QueryHelper::DataSchema($data[0], $except);

        $data_sources = [
            'schema' => $schema,
            'data' => $data,
            'DataSchema' => $DataSchema,
            'TrafficEndpoints' => $traffic_endpoints,
            'account_managers' => $account_managers,
            'partner' => $partner,
            'masters' => $masters,
            'integrations' => $integrations,
            'campaigns' => $campaigns
        ];

        $download = $this->payload['download'] ?? '';
        switch ($download) {
            case 'csv': {
                    return [
                        'callback' => function () use ($data_sources) {
                            $this->downloadCSV($data_sources);
                        }
                    ];
                    // break;
                }
            default: {
                    // $html = $this->getHTMLTable($data_sources);
                    $result = $this->getDataTable($data_sources);
                }
        }

        return $result;
    }

    private function getDataTable($DataSources)
    {
        $schema = $DataSources['schema'];
        $DataSchema = $DataSources['DataSchema'];
        $data = $DataSources['data'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        $result = [
            'columns' => [],
            'items' => [],
        ];

        $money = ['cost', 'affiliate_cost', 'master_affiliate_payout', 'master_brand_payout', 'rpl', 'cpl', 'deposit_revenue'];
        $total = ['visitors', 'leads', 'ftd', 'from_pre_lander'];
        foreach ($data as $dbs) {
            foreach ($schema as $t) {
                $column = [
                    "key" => $t['name'],
                    "label" => $t['title'],
                ];

                if (in_array($dbs, $money)) {
                    $column['renderCell'] = "simple.Money";
                }
                if (in_array($t['name'], $total)) {
                    $column['totalCalc'] = "simple.Sum";
                }

                $result['columns'][$t['name']] = $column;
            }
        }

        foreach ($data as $ndata => $datas) {

            // $b = true;
            // foreach ($DataSchema as $dbs) {
            //     if ('TrafficEndpoint' == $dbs) {
            //         $ep = strtolower($datas[$dbs]);
            //         if (!array_key_exists($ep, $TrafficEndpoints)) $b = false;
            //         break;
            //     }
            // }
            // if (!$b) continue;

            $row = [];
            foreach ($DataSchema as $dbs) {
                if ('brokerId' == $dbs) {
                    $row[$dbs] = $partner[strtolower($datas[$dbs])];
                } elseif ('TrafficEndpoint' == $dbs) {
                    $row[$dbs] = ($TrafficEndpoints[strtolower($datas[$dbs])] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $row[$dbs] = $account_managers[strtolower($datas[$dbs])];
                } elseif ('master_affiliate' == $dbs) {
                    $row[$dbs] = $masters[strtolower($datas[$dbs])];
                } elseif ('master_brand' == $dbs) {
                    $row[$dbs] = $masters[strtolower($datas[$dbs])];
                } elseif ('integration' == $dbs) {
                    $row[$dbs] = $integrations[strtolower($datas['integrationId'])];
                } elseif ('Timestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[$dbs] = date("d-m-Y H:i:s", $seconds);
                } elseif ('CampaignId' == $dbs) {
                    $row[$dbs] = $campaigns[$datas[$dbs]];
                } elseif ('cr' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } elseif ('pcr' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } elseif ('pm' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } elseif ('cffp' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } elseif ('rcr' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } elseif ('pvp' == $dbs) {
                    $row[$dbs] = $datas[$dbs] . '%';
                } else {
                    $row[$dbs] = $datas[$dbs];
                }
            }

            $result['items'][] = $row;
        }

        return $result;
    }

    private function downloadCSV($DataSources)
    {
        $schema = $DataSources['schema'];
        $DataSchema = $DataSources['DataSchema'];
        $data = $DataSources['data'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        $delimiter = ',';

        if ((bool)($this->payload['adjustment'] ?? false) == false) {
            foreach ($DataSchema as $k => $dbs) {
                if ($dbs == 'adjustment_amount') {
                    unset($DataSchema[$k]);
                }
            }
        }

        // clean output buffer
        ob_end_clean();

        $handle = fopen('php://output', 'w');

        // use keys as column titles
        $header = [];
        foreach ($DataSchema as $dbs) {
            foreach ($schema as $t) {
                if ($t['name'] == $dbs) {
                    $header[] = $t['title'];
                    break;
                }
            }
        }

        // return GeneralHelper::PrintR($DataSchema);
        // die();

        fputcsv($handle, array_values($header), $delimiter);

        $c = 0;

        foreach ($data as $ndata => $datas) {

            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('TrafficEndpoint' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $TrafficEndpoints)) $b = false;
                    break;
                }
            }

            $c = $c + 1;
            $row = [];
            foreach ($DataSchema as $dbs) { //DataSchema

                if ('brokerId' == $dbs) {
                    $row[] = $partner[strtolower($datas[$dbs])];
                } elseif ('TrafficEndpoint' == $dbs) {
                    $row[] = ($TrafficEndpoints[strtolower($datas[$dbs])] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $row[] = $account_managers[strtolower($datas[$dbs])];
                } elseif ('master_affiliate' == $dbs) {
                    $row[] = $masters[strtolower($datas[$dbs])];
                } elseif ('master_brand' == $dbs) {
                    $row[] = $masters[strtolower($datas[$dbs])];
                } elseif ('integration' == $dbs) {
                    $row[] = $integrations[strtolower($datas['integrationId'])];
                } elseif ('Timestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[] = date("d-m-Y H:i:s", $seconds);
                } elseif ('CampaignId' == $dbs) {
                    $row[] = $campaigns[$datas[$dbs]];
                } elseif ('cr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('pcr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('pm' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('cffp' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('rcr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('pvp' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } else {
                    $row[] = $datas[$dbs];
                }
            }

            fputcsv($handle, $row, $delimiter);
        }

        fclose($handle);
    }

    public function attachFormula($data, $formulas)
    {
        $array = array();
        foreach ($data as $d) {
            foreach ($formulas as $name => $segment) {
                $find = array();
                $find[] = '__leads__';
                $find[] = '__from_pre_lander__';
                $find[] = '__visitors__';

                $replace = array();
                $replace[] = (int)($d['leads'] ?? 0);
                $replace[] = (int)($d['from_pre_lander'] ?? 0);
                $replace[] = (int)($d['visitors'] ?? 0);

                $rpx = str_replace($find, $replace, $segment);
                //echo $rpx;

                $f = 0;
                try {
                    $f = eval('return ' . $rpx . ';');
                } catch (\ParseError $err) {
                    echo "Parse formula error: $err";
                    die();
                } catch (\Error $err) {
                    echo "Formula error: $err" . PHP_EOL . 'return ' . $rpx . ';';
                    die();
                }

                $d[$name] = (int)$f;
            }

            $array[] = $d;
        }

        return $array;
    }

    public function buildTimestamp()
    {
        $string = $this->payload['timeframe'];
        $explode = explode(' - ', $string);

        $time_range = array();
        $time_range['start'] = new \MongoDB\BSON\UTCDateTime(strtotime($this->givebackstamp($explode[0]) . " 00:00:00") * 1000);
        $time_range['end'] = new \MongoDB\BSON\UTCDateTime(strtotime($this->givebackstamp($explode[1]) . " 23:59:59") * 1000);
        return $time_range;
    }

    public function givebackstamp($d)
    {
        $array = explode('/', $d);
        if (count($array) > 1) {
            return $array[2] . '-' . $array[0] . '-' . $array[1];
        }
        return $d;
    }

    public function buildParameterArray()
    {

        $formula = array();
        $array = array();

        $post_metrics = $this->payload['metrics'];

        foreach (($this->payload['pivot'] ?? []) as $pivot) {
            $array[] = array($pivot => 'val');

            if ($pivot == 'account_manager') {
                $array['TrafficEndpoint'] = array('TrafficEndpoint' => 'val');
            }
        }

        $leads_count = ['_id' => [
            'type' => 'count',
            'formula' => 'return __(bool)is_lead__ == TRUE;',
            'formula_return' => false
        ]];

        foreach ($post_metrics as $metrics) {

            if ($metrics == 'visitors') {
                if (!isset($array['visitors'])) {
                    $array['visitors'] = array('_id' => 'count');
                }
            } elseif ($metrics == 'from_pre_lander') {
                if (!isset($array['from_pre_lander'])) {
                    $array['from_pre_lander'] = array('is_fromprelander' => array('type' => 'count', 'where' => 'is_fromprelander', 'value' => true));
                }
            } elseif ($metrics == 'leads') {
                if (!isset($array['leads'])) {
                    $array['leads'] = $leads_count;
                    // $array['leads'] = array('_id' => array('type' => 'count', 'where' => 'is_lead', 'value' => true));
                }
            } elseif ($metrics == 'blocked_leads') {
                if (!isset($array['blocked_leads'])) {
                    $array['blocked_leads'] = array('blocked_leads' => [
                        'type' => 'count',
                        'formula' => '
                            if ( __(bool)is_lead__ == TRUE && __(bool)match_with_broker__ == FALSE ) {
                                return true;
                            }
                            return false;
                        ',
                        'formula_return' => false
                    ]);
                }
            } elseif ($metrics == 'ftd') {
                // $array['ftd'] = array('_id' => array('type' => 'count', 'where' => 'is_ftd', 'value' => true));
                $array['ftd'] = array('is_ftd' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)is_ftd__ == TRUE ) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            } elseif ($metrics == 'cffp') {

                if (!isset($array['leads'])) {
                    $array['leads'] = $leads_count;
                    // $array['leads'] = array('is_lead' => array('type' => 'count', 'where' => 'is_lead', 'value' => true));
                }

                if (!isset($array['visitors'])) {
                    $array['visitors'] = array('_id' => 'count');
                }

                $formula['cffp'] = '__visitors__ > 0 ? ((__leads__ / __visitors__) * 100) : 0';
            } elseif ($metrics == 'rcr') {

                if (!isset($array['leads'])) {
                    $array['leads'] = $leads_count;
                    // $array['leads'] = array('is_lead' => array('type' => 'count', 'where' => 'is_lead', 'value' => true));
                }

                if (!isset($array['from_pre_lander'])) {
                    $array['from_pre_lander'] = array('from_pre_lander' => array('type' => 'count', 'where' => 'is_fromprelander', 'value' => true));
                }

                $formula['rcr'] = '__from_pre_lander__ > 0 ? ((__leads__/__from_pre_lander__) * 100) : 0';
            } elseif ($metrics == 'pvp') {

                if (!isset($array['leads'])) {
                    $array['leads'] = $leads_count;
                    // $array['leads'] = array('Leads' => array('type' => 'count', 'where' => 'is_lead', 'value' => true));
                }

                if (!isset($array['from_pre_lander'])) {
                    $array['from_pre_lander'] = array('is_fromprelander' => array('type' => 'count', 'where' => 'is_fromprelander', 'value' => true));
                }

                $formula['pvp'] = '__visitors__ > 0 ? ((__from_pre_lander__ /__visitors__) * 100) : 0';
            }
        }

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }
}
//error_reporting(E_ALL);
//ini_set("display_errors","On");
// $report = new serviceClickReport('', $_POST);
// echo json_encode($report->Handler());
