<?php

namespace App\Classes\Report;

use App\Models\User;
use App\Helpers\CryptHelper;
use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\Report\ReportMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use App\Classes\Mongo\MongoQueryCache;

class ReportService extends ReportMeta
{

    public $post;

    private $start;
    private $end;

    private $result;

    private $payload;

    private $result_adjustments;

    private $not_fount_result_adjustments;

    protected $formulas;

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
            $account_managers[($id['oid'])] = $supply['account_manager'];
        }
        // print_r($account_managers);
        return $account_managers;
    }

    public function Handler()
    {
        // TODO: Access
        // if (!permissionsManagement::is_allow('reports', ['all', 'view'])) {
        //     return ['success' => false, 'message' => permissionsManagement::get_error_message()];
        // }

        $time = $this->buildTimestamp();

        $this->formulas = new ReportFormulas($this->start, $this->end);

        $query = $this->buildParameterArray();

        $conditions = array();

        if (!empty($this->payload['clientId'])) {
            $conditions['clientId'] = $this->payload['clientId'];
        }

        $condition['test_lead'] = 0;

        if (isset($this->payload['broker']) && count($this->payload['broker']) > 0) {
            $conditions['brokerId'] = ($conditions['brokerId'] ?? []) + $this->payload['broker'];
        }

        if (isset($this->payload['traffic_endpoint']) && count($this->payload['traffic_endpoint']) > 0) {
            $conditions['TrafficEndpoint'] = ($conditions['TrafficEndpoint'] ?? []) + $this->payload['traffic_endpoint'];
        }

        if (isset($this->payload['master_affiliate']) && count($this->payload['master_affiliate']) > 0) {
            $conditions['MasterAffiliate'] = ($conditions['MasterAffiliate'] ?? []) + $this->payload['master_affiliate'];
        }

        if (isset($this->payload['master_brand']) && count($this->payload['master_brand']) > 0) {
            $conditions['master_brand'] = ($conditions['master_brand'] ?? []) + $this->payload['master_brand'];
        }

        if (isset($this->payload['campaign']) && count($this->payload['campaign']) > 0) {
            $conditions['CampaignId'] = ($conditions['CampaignId'] ?? []) + $this->payload['campaign'];
        }

        if (isset($this->payload['country']) && count($this->payload['country']) > 0) {
            $conditions['country'] = ($conditions['country'] ?? []) + $this->payload['country'];
        }

        if (isset($this->payload['language']) && count($this->payload['language']) > 0) {
            $conditions['language'] = ($conditions['language'] ?? []) + $this->payload['language'];
        }

        if (isset($this->payload['account_manager']) && count($this->payload['account_manager']) > 0) {

            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }

            $filter_traffic_endpoints = [];
            if (isset($this->payload['traffic_endpoint']) && count($this->payload['traffic_endpoint']) > 0) {
                $filter_traffic_endpoints = $this->payload['traffic_endpoint'];
            }

            $added = false;
            foreach ($account_managers as $endpoint => $account_manager) {

                $pre_filter_allow = true;
                if (count($filter_traffic_endpoints) > 0 && !in_array($endpoint, $filter_traffic_endpoints)) {
                    $pre_filter_allow = false;
                }

                if ($pre_filter_allow && is_string($this->payload['account_manager']) && $account_manager == $this->payload['account_manager']) {
                    $conditions['TrafficEndpoint'][] = $endpoint;
                    $added = true;
                } else
                if ($pre_filter_allow && is_array($this->payload['account_manager']) && in_array($account_manager, $this->payload['account_manager'])) {
                    $conditions['TrafficEndpoint'][] = $endpoint;
                    $added = true;
                }
            }

            if (!$added) {
                $conditions['TrafficEndpoint'] = ['false'];
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
            // if (in_array('false', $conditions['TrafficEndpoint'])) {
            //     $conditions['TrafficEndpoint'] == [];
            // }
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

        if (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
            $condition[] = [
                'strict' => [
                    '$or' => [
                        [
                            'Timestamp' => array('$gte' => $time['start'], '$lte' => $time['end']),
                        ],
                        [
                            'endpointDepositTimestamp' => array('$gte' => $time['start'], '$lte' => $time['end']),
                            'depositor' => true,
                            'deposit_disapproved' => false
                        ]
                    ]
                ]
            ];
        }

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
        // $queryMongo = new MongoQueryCache($time, 'leads', $query['query'], $condition);

        $hooks = [];

        // TODO: Access
        // if (customUserAccess::is_forbidden('deposit_disapproved')) {
        if (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
            $hooks[] = function (&$cell) {
                if (($cell['deposit_disapproved'] ?? false) && ($cell['depositor'] ?? false)) {
                    $status = 'No Answer'; //'Call back - Personal';
                    //if ($cell['status'] ?? false)
                    $cell['status'] = $status;
                    //if ($cell['broker_status'] ?? false)
                    $cell['broker_status'] = $status;
                }
            };
        }

        if (in_array('account_manager', $this->payload['pivot'])) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }
            $hooks[] = function (&$cell) use ($account_managers) {
                $cell['account_manager'] = $account_managers[$cell['TrafficEndpoint'] ?? ''] ?? '';
            };
        }
        if (!in_array('TrafficEndpoint', $this->payload['pivot'])) {
            $hooks[] = function (&$cell) {
                unset($cell['TrafficEndpoint']);
            };
        }

        $args = [
            'data' => 1
        ];

        // if (!empty($hooks)) {
        //     $args['hook_cell_data'] = function (&$cell) use ($hooks) {
        //         foreach ($hooks as $hook) {
        //             $hook($cell);
        //         }
        //     };
        // }

        $args = [
            'hook_data' => function (&$data) use ($query, $hooks) {
                if ((bool)($this->payload['adjustment'] ?? false) == true) {
                    $this->queryAdjustments($data, $query);
                } else {
                    foreach ($data as &$v) {
                        $v['adjustment_amount'] = 0;
                    }
                }
                foreach ($hooks as $hook) {
                    foreach ($data as &$v) {
                        $hook($v);
                    }
                }
                // print_r($data);
            }
        ];

        $this->result = $queryMongo->queryMongo($args);

        $data = $this->result['result'];

        if ((bool)($this->payload['adjustment'] ?? false) == true) {
            foreach ($this->not_fount_result_adjustments as $trafficEndpoint => $adjustment_amount) {
                // $schema = $this->get_titles();
                $lead = [];
                foreach ($query['query'] as $q => $f) {
                    // foreach ($schema as $f => $l) {
                    // if ($q == $f) {
                    $lead[$q] = '';
                    // }
                    // }
                }
                $lead['TrafficEndpoint'] = $trafficEndpoint;
                $lead['cost'] = $adjustment_amount;
                $lead['adjustment_amount'] = $adjustment_amount;

                $data[] = $lead;
            }
        }

        $ext_replace = [
            '__all_leads__' => fn ($k, $d) => (int)($d['all_leads'] ?? 0),
            '__mismatch_leads__' => fn ($k, $d) => (int)($d['mismatch'] ?? 0),
            '__hit_the_redirect__' => fn ($k, $d) => (int)($d['redirect'] ?? 0),
            '__high_risk__' => fn ($k, $d) => (int)($d['fraudHighRisk'] ?? 0),
            '__medium_risk__' => fn ($k, $d) => (int)($d['fraudMediumRisk'] ?? 0),
            '__low_risk__' => fn ($k, $d) => (int)($d['fraudLowRisk'] ?? 0),
            '__valid_leads__' => fn ($k, $d) => (int)($d['valid_leads'] ?? 0),
        ];

        $f = QueryHelper::attachFormula($data, $query['formula'], $ext_replace);

        // $this->result_adjustments = $this->queryAdjustments();

        foreach ($f as &$d) {
            CryptHelper::decrypt_lead_data_array($d);
        }

        $result = $this->buildView($f, $query);

        return $result;
    }

    private function queryAdjustments(&$data)
    {
        $result_adjustments = [];

        $in = [];

        $string = $this->payload['timeframe'];
        $explode = explode(' - ', $string);

        $start = strtotime($this->givebackstamp($explode[0]) . " 00:00:00");
        $end = strtotime($this->givebackstamp($explode[1]) . " 23:59:59");

        $start = new \MongoDB\BSON\UTCDateTime($start * 1000);
        $end = new \MongoDB\BSON\UTCDateTime($end * 1000);

        $where = [
            // 'endpoint' => ['$in' => $in],
            'bi' => true,
            'bi_timestamp' => ['$gte' => $start, '$lte' => $end],
        ];

        // traffic endpoints
        list($traffic_endpoints, $account_managers) = $this->getTrafficEndpoints();

        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $in = array_keys($traffic_endpoints);
        }

        if (isset($this->payload['endpoint'])) {
            foreach ($this->payload['endpoint'] as $endpoint) {
                if (!isset($where['$or'])) {
                    $where['$or'] = [];
                }
                $where['$or'][] = ['endpoint' => $endpoint];
            }
        }

        if (isset($this->payload['account_managers'])) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }
            foreach ($account_managers as $endpoint => $account_manager) {
                if (in_array($account_manager, $this->payload['account_managers'])) {
                    if (!isset($where['$or'])) {
                        $where['$or'] = [];
                    }
                    $where['$or'][] = ['endpoint' => $endpoint];
                }
            }
        }

        if (count($in) > 0) {
            $where['endpoint'] = ['$in' => $in];
        }

        $mongo = new MongoDBObjects('endpoint_billing_adjustments', $where);
        $find = $mongo->findMany([
            'projection' => [
                'endpoint' => 1,
                'bi_timestamp' => 1,
                'amount' => 1
            ]
        ]);

        //group by endpoint
        foreach ($find as $f) {
            $result_adjustments[$f['endpoint']] = ($result_adjustments[$f['endpoint']] ?? 0) + $f['amount'];
        }

        foreach ($data as &$v) {
            if (isset($result_adjustments[$v['TrafficEndpoint'] ?? ''])) {
                $v['adjustment_amount'] = $result_adjustments[$v['TrafficEndpoint']];
                unset($result_adjustments[$v['TrafficEndpoint']]);
            } else {
                $v['adjustment_amount'] = 0;
            }
        }

        $this->not_fount_result_adjustments = $result_adjustments;
    }

    private function getTrafficEndpoints(): array
    {
        $cache_key = 'report_traffic_endpoints_' . Auth::id();
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
        $cache_key = 'report_brokers_' . Auth::id();
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

    public function buildView($data, $query)
    {

        // TODO: Access
        // if (!permissionsManagement::is_allow('reports', ['all', 'view'])) {
        //     echo permissionsManagement::get_error_message();
        //     return;
        // }

        if (count($data) == 0) {
            return [
                'columns' => [],
                'items' => []
            ];
        }

        $where = array();
        $mongo = new MongoDBObjects('campaigns', $where);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'name' => 1]]);
        $campaigns = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $campaigns[$id['oid']] = ($supply['name'] ?? '');
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
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1]]);
        $masters = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $masters[strtolower($id['oid'])] = ($supply['token'] ?? '');
        }

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();

        // TODO: later
        $download = $this->payload['download'] ?? '';

        $metrics = $this->payload['metrics'];
        $fields = $data[0];

        $except = ['integrationId', 'revenue'];
        if (!in_array('TrafficEndpoint', $this->payload['pivot'])) {
            $except[] = 'TrafficEndpoint';
        }
        $schema = QueryHelper::schemaTitles($this->get_titles(), $data[0], $except);
        $DataSchema = QueryHelper::DataSchema($data[0], $except);

        $pivot = isset($this->payload['pivot']) && is_array($this->payload['pivot']) ? $this->payload['pivot'] : [];
        $ordered_metrics = $metrics;
        $metrics = array_unique(array_merge($pivot, $ordered_metrics)); //metrics

        for ($i = 0; $i < count($metrics); $i++) {
            $metric = $metrics[$i];
            if (isset(ReportMeta::$pivot_metrics[$metric]) && isset(ReportMeta::$pivot_metrics[$metric]['name'])) {
                $metrics[$i] = ReportMeta::$pivot_metrics[$metric]['name'];
            }
        }

        $sortedDataSchema = [];
        foreach ($metrics as $metric) {
            if (in_array($metric, $DataSchema)) {
                $sortedDataSchema[] = $metric;
            }
        }

        $result = '';
        $data_sources = [
            'sortedDataSchema' => $sortedDataSchema,
            'schema' => $schema,
            'data' => $data,
            'query' => $query,
            'DataSchema' => $DataSchema,
            'TrafficEndpoints' => $traffic_endpoints,
            'account_managers' => $account_managers,
            'partner' => $partner,
            'masters' => $masters,
            'countries' => $countries,
            'languages' => $languages,
            'integrations' => $integrations,
            'campaigns' => $campaigns
        ];

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

        $sortedDataSchema = $DataSources['sortedDataSchema'];
        $schema = $DataSources['schema'];
        $data = $DataSources['data'];
        $query = $DataSources['query'];
        $DataSchema = $DataSources['DataSchema'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $countries = $DataSources['countries'];
        $languages = $DataSources['languages'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        $result = [
            'columns' => [],
            'items' => []
        ];

        if ((bool)($this->payload['adjustment'] ?? false) == false) {
            foreach ($sortedDataSchema as $k => $dbs) {
                if ($dbs == 'adjustment_amount') {
                    unset($sortedDataSchema[$k]);
                }
            }
        }

        $money = ['cost', 'adjustment_amount', 'deposit_revenue', 'profit', 'cpl', 'rpl', 'affiliate_cost', 'master_brand_payout'];
        $percent = ['cr', 'p_cr', 'pm', 'mismatch', 'redirect', 'fraudHighRisk', 'fraudMediumRisk', 'fraudLowRisk'];
        foreach ($sortedDataSchema as $dbs) {
            // if ($dbs == 'adjustment_amount') {
            //     $html .= '<th style="color: #fff;font-size: 13px !important;font-weight: 500;" data-name="adjustment_amount">Adjustment</th>';
            // } else {

            foreach ($schema as $t) {
                if ($t['name'] == $dbs) {
                    $column = [
                        "key" => $t['name'],
                        "label" => $t['title'],
                    ];

                    if (in_array($dbs, $money)) {
                        $column['renderCell'] = "simple.MoneyColor";
                    } elseif (in_array($dbs, $percent)) {
                        $column['renderCell'] = "simple.Percent";
                    } elseif ('brokerId' == $dbs) {
                        $column['renderCell'] = "custom.BrokerName";
                    } elseif ('TrafficEndpoint' == $dbs) {
                        $column['renderCell'] = "custom.EndpointName";
                    }

                    $result['columns'][$t['name']] = $column;

                    break;
                }
            }
            // }
        }

        $totals = [];

        $render = function ($dbs, &$datas, $total = false) use (
            $partner,
            $TrafficEndpoints,
            $account_managers,
            $masters,
            $countries,
            $languages,
            $integrations,
            $campaigns
        ) {
            $result = '';

            if (isset($datas[$dbs])) {

                if ($dbs == 'integration' && $datas[$dbs] === '') {
                    $datas[$dbs] = ($datas['integrationId'] ?? '');
                }

                if ('brokerId' == $dbs) {
                    $result = ($partner[strtolower($datas[$dbs]) ?? ''] ?? ($total ? '' : 'No available broker'));
                } elseif ($datas[$dbs] === '') {
                    $result = '';
                } elseif ('TrafficEndpoint' == $dbs) {
                    $result = ($TrafficEndpoints[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $result = ($account_managers[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('MasterAffiliate' == $dbs) {
                    $result = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('master_brand' == $dbs) {
                    $result = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('country' == $dbs) {
                    $result = ($countries[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('language' == $dbs) {
                    $result = ($languages[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('integration' == $dbs || 'integrationId' == $dbs) {
                    $result = ($integrations[strtolower($datas['integrationId']) ?? ''] ?? '');
                } elseif ('Timestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $result .= date("d-m-Y H:i:s", $seconds);
                } elseif ('depositTimestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $result .= date("d-m-Y H:i:s", $seconds);
                } elseif ('endpointDepositTimestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $result .= date("d-m-Y H:i:s", $seconds);
                } elseif ('CampaignId' == $dbs) {
                    $result = ($campaigns[$datas[$dbs] ?? ''] ?? '');
                } else {
                    $result = $datas[$dbs];
                }
            }
            return $result;
        };

        $c = 0;

        $columns = $result['columns'] ?? [];

        foreach ($data as $ndata => $datas) {

            $c = $c + 1;
            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('TrafficEndpoint' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $TrafficEndpoints)) $b = false;
                    break;
                }
            }

            if (!$b && !isset($columns[$dbs])) {
                continue;
            }

            $raw = [];
            foreach ($sortedDataSchema as $dbs) { //DataSchema

                $row_schema = null;
                if (isset(self::$pivot_metrics[$dbs])) {
                    $row_schema = self::$pivot_metrics[$dbs];
                } else if (isset(self::$pivot_titles[$dbs])) {
                    $row_schema = self::$pivot_titles[$dbs];
                }
                // else if ($dbs == 'adjustment_amount') {
                //     $row_schema['total'] = 'sum';
                // }

                $is_total = isset($row_schema) && isset($row_schema['total']);
                if ($is_total) {
                    $totals[$dbs] = (isset($totals[$dbs]) ? $totals[$dbs] : 0) + (float)($datas[$dbs] ?? 0);
                } else {
                    $totals[$dbs] = '';
                }

                $raw[$dbs] = $render($dbs, $datas);

                if ('brokerId' == $dbs) {
                    $raw['broker_id'] = $datas[$dbs] ?? '';
                } elseif ('TrafficEndpoint' == $dbs) {
                    $raw['traffic_endpoint_id'] = $datas[$dbs] ?? '';
                }
            }
            $result['items'][] = $raw;
        }

        // total
        // $html .= '<tfoot><tr class="total" style="background-color: #292f4c;color: #fff !important;">';
        $raw = [];
        foreach ($sortedDataSchema as $dbs) {
            $row_schema = null;
            if (isset(self::$pivot_metrics[$dbs])) {
                $row_schema = self::$pivot_metrics[$dbs];
            } else if (isset(self::$pivot_titles[$dbs])) {
                $row_schema = self::$pivot_titles[$dbs];
            }
            // else if ($dbs == 'adjustment_amount') {
            //     $row_schema['total'] = 'sum';
            // }
            if (isset($row_schema['total'])) {
                if (isset($row_schema['aggregate']) && $row_schema['aggregate'] == 'avg') {
                    $totals[$dbs] = round($totals[$dbs] / $c, 2);
                }
                if (isset($row_schema['total_formula'])) {
                    if ($row_schema['total_formula'] == true) {
                        if ($query['formula'][$dbs]) {
                            $formula = $query['formula'][$dbs];
                            if (is_string($formula) && !empty($formula)) {
                                $r = QueryHelper::attachFormula([$totals], [
                                    $dbs => $formula
                                ]);
                                $totals[$dbs] = $r[0][$dbs];
                            }
                        }
                    }
                }
            }
            $raw[$dbs] = $render($dbs, $totals, true);
        }

        foreach (array_keys($raw) as $dbs) {
            $row_schema = null;
            if (isset(self::$pivot_metrics[$dbs])) {
                $row_schema = self::$pivot_metrics[$dbs];
            } else if (isset(self::$pivot_titles[$dbs])) {
                $row_schema = self::$pivot_titles[$dbs];
            }
            if (isset($row_schema['total']) && ($row_schema['aggregate'] ?? '') == 'formula' && !empty($row_schema['post_formula'])) {
                preg_match_all('|__(.*?)__|', $row_schema['post_formula'], $matches);
                if (isset($matches) && count($matches) == 2) {
                    $formula = $row_schema['post_formula'];
                    for ($i = 0; $i < count($matches[0]); $i++) {
                        $formula = str_replace($matches[0][$i], $totals[$matches[1][$i]] ?? 0, $formula);
                    }
                    try {
                        $raw[$dbs] = eval('return ' . $formula . ';');
                    } catch (\Exception $ex) {
                    }
                }
            }
        }

        $result['totals'] = $raw;

        // $html .= $this->getHTMLAdjustmentTable($TrafficEndpoints);

        return $result;
    }

    private function getHTMLTable($DataSources)
    {

        $sortedDataSchema = $DataSources['sortedDataSchema'];
        $schema = $DataSources['schema'];
        $data = $DataSources['data'];
        $query = $DataSources['query'];
        $DataSchema = $DataSources['DataSchema'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $countries = $DataSources['countries'];
        $languages = $DataSources['languages'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        $did = "'bi_table'";
        $html = '<div style=" right: 0; width: 25%; float: right; ">
                <input class="form-control" id="campaign_search" style=" float: left; width: 80%; padding: 0px !important; height: 27px; margin-bottom: 8px; margin-right: 8px; ">
                <!--<svg xmlns="http://www.w3.org/2000/svg" onclick="download_table_as_csv_srv(' . $did . ')" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs" viewBox="0 0 140 140" width="20" height="20"><g transform="matrix(5.833333333333333,0,0,5.833333333333333,0,0)"><path d="M5.251,23.254h-3a1.5,1.5,0,0,1-1.5-1.5V2.254a1.5,1.5,0,0,1,1.5-1.5H12.88a1.5,1.5,0,0,1,1.06.439l5.872,5.871a1.5,1.5,0,0,1,.439,1.061v4.629" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M20.251,8.254h-6a1.5,1.5,0,0,1-1.5-1.5v-6" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M11.251,15.754a3,3,0,0,0-3,3v1.5a3,3,0,0,0,3,3" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M17.251,15.754h-1.5a1.5,1.5,0,0,0-1.5,1.5c0,2.25,3,2.25,3,4.5a1.5,1.5,0,0,1-1.5,1.5h-1.5" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M20.251,15.754V18.3a8.935,8.935,0,0,0,1.5,4.955,8.935,8.935,0,0,0,1.5-4.955V15.754" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></g></svg>-->
                <div class="bi_export_btn" onclick="download_table_as_csv_srv(' . $did . ')"><svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs" viewBox="0 0 140 140" width="20" height="20"><g transform="matrix(5.833333333333333,0,0,5.833333333333333,0,0)"><path d="M5.251,23.254h-3a1.5,1.5,0,0,1-1.5-1.5V2.254a1.5,1.5,0,0,1,1.5-1.5H12.88a1.5,1.5,0,0,1,1.06.439l5.872,5.871a1.5,1.5,0,0,1,.439,1.061v4.629" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M20.251,8.254h-6a1.5,1.5,0,0,1-1.5-1.5v-6" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M11.251,15.754a3,3,0,0,0-3,3v1.5a3,3,0,0,0,3,3" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M17.251,15.754h-1.5a1.5,1.5,0,0,0-1.5,1.5c0,2.25,3,2.25,3,4.5a1.5,1.5,0,0,1-1.5,1.5h-1.5" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M20.251,15.754V18.3a8.935,8.935,0,0,0,1.5,4.955,8.935,8.935,0,0,0,1.5-4.955V15.754" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></g></svg> Export</div>
                </div>';
        $html .= '<table id="bi_table" class="table" style=" table-layout: fixed; word-wrap: break-word !important; "><thead><tr style=" background-color: #292f4c; color: #fff !important; ">';
        // $html .= '<th style="color: #fff;font-size: 13px !important;font-weight: 500;">#</th>';

        // adjustment_amount
        // if (isset($sortedDataSchema['cost'])) {
        //     $cost_index = array_search('cost', $sortedDataSchema);
        //     $t = array_slice($sortedDataSchema, $cost_index + 1, count($sortedDataSchema) - 1, true);
        //     $sortedDataSchema = array_slice($sortedDataSchema, 0, $cost_index + 1, true);
        //     $sortedDataSchema[] = 'adjustment_amount';
        //     $sortedDataSchema = array_merge($sortedDataSchema, $t);
        // }
        // ----

        if ((bool)($this->payload['adjustment'] ?? false) == false) {
            foreach ($sortedDataSchema as $k => $dbs) {
                if ($dbs == 'adjustment_amount') {
                    unset($sortedDataSchema[$k]);
                }
            }
        }

        foreach ($sortedDataSchema as $dbs) {
            // if ($dbs == 'adjustment_amount') {
            //     $html .= '<th style="color: #fff;font-size: 13px !important;font-weight: 500;" data-name="adjustment_amount">Adjustment</th>';
            // } else {
            foreach ($schema as $t) {
                if ($t['name'] == $dbs) {
                    $html .= '<th style="color: #fff;font-size: 13px !important;font-weight: 500;" data-name="' . $t['name'] . '">' . $t['title'] . '</th>';
                    break;
                }
            }
            // }
        }

        $html .= '</tr></thead><tbody>';

        $c = 0;

        $totals = [];

        $render = function ($dbs, &$datas) use (
            $partner,
            $TrafficEndpoints,
            $account_managers,
            $masters,
            $countries,
            $languages,
            $integrations,
            $campaigns
        ) {
            $html = '';

            $render_money = function ($val, $symbol, $minus_color = false) {
                $v = str_replace($symbol . '-', '-' . $symbol, $symbol . strval($val));
                if ($minus_color !== false && (float)$val < 0) {
                    $v = '<span style="color:' . $minus_color . '">' . $v . '</span>';
                }
                return $v;
            };

            if (isset($datas[$dbs])) {

                if ($dbs == 'integration' && $datas[$dbs] === '') {
                    $datas[$dbs] = ($datas['integrationId'] ?? '');
                }

                if ($datas[$dbs] === '') {
                    $html .= '<td style=" font-size: 13px; "></td>';
                } else
                if ('brokerId' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $partner[strtolower($datas[$dbs])] . '</td>';
                } elseif ('TrafficEndpoint' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $TrafficEndpoints[strtolower($datas[$dbs])] . '</td>';
                } elseif ('account_manager' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $account_managers[strtolower($datas[$dbs])] . '</td>';
                } elseif ('MasterAffiliate' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $masters[strtolower($datas[$dbs])] . '</td>';
                } elseif ('master_brand' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $masters[strtolower($datas[$dbs])] . '</td>';
                } elseif ('country' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . ($countries[$datas[$dbs]] ?? '') . '</td>';
                } elseif ('language' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . ($languages[$datas[$dbs]] ?? '') . '</td>';
                } elseif ('integration' == $dbs || 'integrationId' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $integrations[strtolower($datas['integrationId'])] . '</td>';
                } elseif ('Timestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $html .= '<td style=" font-size: 13px; ">' . date("d-m-Y H:i:s", $seconds) . '</td>';
                } elseif ('CampaignId' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $campaigns[$datas[$dbs]] . '</td>';
                } elseif ('cost' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('adjustment_amount' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('deposit_revenue' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('cpl' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('rpl' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('cr' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $datas[$dbs] . '%</td>';
                } elseif ('p_cr' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $datas[$dbs] . '%</td>';
                } elseif ('pm' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $datas[$dbs] . '%</td>';
                } elseif ('master_brand_payout' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('master_affiliate_payout' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('affiliate_cost' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                    /*} elseif ('broker_crg_revenue' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('crg_revenue' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';
                } elseif ('no_crg_cpl_cost' == $dbs || 'broker_no_crg_cpl_revenue' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$') . '</td>';*/
                } elseif ('profit' == $dbs) {
                    $html .= '<td style=" font-size: 13px; ">' . $render_money($datas[$dbs], '$', 'red') . '</td>';
                } else {
                    $html .= '<td style=" font-size: 13px; ">' . $datas[$dbs] . '</td>';
                }
            }
            return $html;
        };

        foreach ($data as $ndata => $datas) {

            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('TrafficEndpoint' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $TrafficEndpoints)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            $c = $c + 1;
            $html .= '<tr>';
            // $html .= '<td style=" font-size: 13px; ">' . $c . '</td>';

            foreach ($sortedDataSchema as $dbs) { //DataSchema

                $row_schema = null;
                if (isset(self::$pivot_metrics[$dbs])) {
                    $row_schema = self::$pivot_metrics[$dbs];
                } else if (isset(self::$pivot_titles[$dbs])) {
                    $row_schema = self::$pivot_titles[$dbs];
                }
                // else if ($dbs == 'adjustment_amount') {
                //     $row_schema['total'] = 'sum';
                // }

                $is_total = isset($row_schema) && isset($row_schema['total']);
                if ($is_total) {
                    $totals[$dbs] = (isset($totals[$dbs]) ? $totals[$dbs] : 0) + (float)($datas[$dbs] ?? 0);
                } else {
                    $totals[$dbs] = '';
                }

                $html .= $render($dbs, $datas);
            }
            $html .= '</tr>';
            $html = str_replace('</tr></tr>', '', $html);
        }
        $html .= '</tbody>';

        $html .= '<tfoot><tr class="total" style="background-color: #292f4c;color: #fff !important;">';

        foreach ($sortedDataSchema as $dbs) {
            $row_schema = null;
            if (isset(self::$pivot_metrics[$dbs])) {
                $row_schema = self::$pivot_metrics[$dbs];
            } else if (isset(self::$pivot_titles[$dbs])) {
                $row_schema = self::$pivot_titles[$dbs];
            }
            // else if ($dbs == 'adjustment_amount') {
            //     $row_schema['total'] = 'sum';
            // }
            if (isset($row_schema['total'])) {
                if (isset($row_schema['aggregate']) && $row_schema['aggregate'] == 'avg') {
                    $totals[$dbs] = round($totals[$dbs] / $c, 2);
                }
                if (isset($row_schema['total_formula'])) {
                    if ($row_schema['total_formula'] == true) {
                        if ($query['formula'][$dbs]) {
                            $formula = $query['formula'][$dbs];
                            if (is_string($formula) && !empty($formula)) {
                                $r = QueryHelper::attachFormula([$totals], [
                                    $dbs => $formula
                                ]);
                                $totals[$dbs] = $r[0][$dbs];
                            }
                        }
                    }
                }
            }
            $html .= $render($dbs, $totals);
        }
        $html .= '</tr></tfoot>';

        $html .= '</table>';

        // $html .= $this->getHTMLAdjustmentTable($TrafficEndpoints);

        return $html;
    }

    private function getHTMLAdjustmentTable(&$TrafficEndpoints)
    {
        $html = '';

        if (is_array($this->result_adjustments) && count($this->result_adjustments) > 0) {
            $html .= '<h5>Adjustments</h5>
                        <table id="bi_table_adjustments" class="table" style=" table-layout: fixed; word-wrap: break-word !important; ">
                        <thead>
                            <tr style=" background-color: #292f4c; color: #fff !important; ">
                                <th style="color: #fff;font-size: 13px !important;font-weight: 500;">Traffic Endpoint</th>
                                <th style="color: #fff;font-size: 13px !important;font-weight: 500;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>';
            foreach ($this->result_adjustments as $trafficEndpoint => $amount) {
                $html .= '<tr>
                            <td style=" font-size: 13px; ">' . $TrafficEndpoints[strtolower($trafficEndpoint)] . '</td>
                            <td style=" font-size: 13px; ">' . $amount . '</td>
                        </tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    private function downloadCSV($DataSources)
    {

        $sortedDataSchema = $DataSources['sortedDataSchema'];
        $schema = $DataSources['schema'];
        $data = $DataSources['data'];
        $DataSchema = $DataSources['DataSchema'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $countries = $DataSources['countries'];
        $languages = $DataSources['languages'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        // $filename = 'export_bi_' . date('Y-m-d') . '.csv';
        $delimiter = ',';

        // header('Content-Type: application/csv');
        // header('Content-Disposition: attachment; filename="' . $filename . '";');

        if ((bool)($this->payload['adjustment'] ?? false) == false) {
            foreach ($sortedDataSchema as $k => $dbs) {
                if ($dbs == 'adjustment_amount') {
                    unset($sortedDataSchema[$k]);
                }
            }
        }

        // clean output buffer
        ob_end_clean();

        $handle = fopen('php://output', 'w');

        // use keys as column titles
        $header = [];
        foreach ($sortedDataSchema as $dbs) {
            foreach ($schema as $t) {
                if ($t['name'] == $dbs) {
                    $header[] = $t['title'];
                    break;
                }
            }
        }

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
            if (!$b && !isset($sortedDataSchema[$dbs])) {
                continue;
            }

            $c = $c + 1;
            $row = [];
            foreach ($sortedDataSchema as $dbs) { //DataSchema

                if ('brokerId' == $dbs) {
                    $row[] = ($partner[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('TrafficEndpoint' == $dbs) {
                    $row[] = ($TrafficEndpoints[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $row[] = ($account_managers[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('MasterAffiliate' == $dbs) {
                    $row[] = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('master_brand' == $dbs) {
                    $row[] = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('integration' == $dbs || 'integrationId' == $dbs) {
                    $row[] = ($integrations[strtolower($datas['integrationId']) ?? ''] ?? '');
                } elseif ('Timestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[] = date("d-m-Y H:i:s", $seconds);
                } elseif ('depositTimestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[] = date("d-m-Y H:i:s", $seconds);
                } elseif ('endpointDepositTimestamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[] = date("d-m-Y H:i:s", $seconds);
                } elseif ('CampaignId' == $dbs) {
                    $row[] = ($campaigns[$datas[$dbs] ?? ''] ?? '');
                } elseif ('cost' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('deposit_revenue' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('cpl' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('rpl' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('cr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('p_cr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('pm' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('master_brand_payout' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('master_affiliate_payout' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('affiliate_cost' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                    /*} elseif ('broker_crg_revenue' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('crg_revenue' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('no_crg_cpl_cost' == $dbs || 'broker_no_crg_cpl_revenue' == $dbs) {
                    $row[] = '$' . $datas[$dbs];*/
                } elseif ('profit' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('country' == $dbs) {
                    $row[] = ($countries[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('language' == $dbs) {
                    $row[] = ($languages[$datas[$dbs] ?? ''] ?? '');
                } else {
                    $row[] = $datas[$dbs];
                }
            }

            fputcsv($handle, $row, $delimiter);
        }

        fclose($handle);

        // flush buffer
        // ob_flush();

        // use exit to get rid of unexpected output afterward
        // exit();
    }

    public function buildTimestamp()
    {
        $string = $this->payload['timeframe'];
        $explode = explode(' - ', $string);

        $time_range = array();

        $this->start = strtotime($this->givebackstamp($explode[0]) . " 00:00:00");
        $this->end = strtotime($this->givebackstamp($explode[1]) . " 23:59:59");

        $start = new \MongoDB\BSON\UTCDateTime($this->start * 1000);
        $end = new \MongoDB\BSON\UTCDateTime($this->end * 1000);

        $time_range['start'] = $start;
        $time_range['end'] = $end;

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

        foreach ($this->payload['pivot'] as $pivot) {
            $array[$pivot] = array($pivot => 'val');

            if ($pivot == 'integration') {
                $array[] = array('integrationId' => 'val');
            }
            if ($pivot == 'account_manager') {
                $array['TrafficEndpoint'] = array('TrafficEndpoint' => 'val');
            }
        }

        $this->formulas->attach('adjustment_amount', $array);

        foreach ($post_metrics as $metrics) {
            if ($metrics == 'revenue' || (int)$metrics == 1) {
                $this->formulas->attach('deposit_revenue', $array);
            } elseif ($metrics == 'cost' || (int)$metrics == 2) {
                $this->formulas->attach('cost', $array);
                $formula['cost'] = '__cost__';
            } elseif ($metrics == 'leads' || (int)$metrics == 4) {
                $this->formulas->attach('Leads', $array);
            } elseif ($metrics == 'blocked_leads' || (int)$metrics == 5) {
                $this->formulas->attach('BlockedLeads', $array);
            } elseif ($metrics == 'ftd' || (int)$metrics == 6) {
                $this->formulas->attach('Depositors', $array);
            } elseif ($metrics == 'approved_ftds' || (int)$metrics == 7) {
                $this->formulas->attach('ApprovedDepositors.approved_deposit', $array);
            } elseif ($metrics == 'test_FTD') {
                $this->formulas->attach('test_FTD', $array);
            } elseif ($metrics == 'fake_FTD') {
                $this->formulas->attach('fake_FTD', $array);
            }

            if ($metrics == 'crg_already_paid_ftd') {
                $this->formulas->attach('crg_already_paid_ftd', $array);
            }

            if ($metrics == 'broker_crg_already_paid_ftd') {
                $this->formulas->attach('broker_crg_already_paid_ftd', $array);
            }

            if ($metrics == 'test_lead') {
                $this->formulas->attach('test_lead', $array);
            }

            if ($metrics == 'crg_leads') {
                $this->formulas->attach('crg_leads', $array);
            }

            if ($metrics == 'broker_crg_leads') {
                $this->formulas->attach('broker_crg_leads', $array);
            }

            if ($metrics == 'cpl_leads') {
                $this->formulas->attach('cpl_leads', $array);
            }

            if ($metrics == 'broker_cpl_leads') {
                $this->formulas->attach('broker_cpl_leads', $array);
            }

            if ($metrics == 'cr' || (int)$metrics == 8) {
                $this->formulas->attach('Leads', $array);

                // Check this
                if (!isset($array['Depositors'])) {
                    $array['Depositors'] = array('depositor' => [
                        'type' => 'count',
                        'formula' => '
                            if (
                                __(bool)depositor__ == TRUE &&
                                ' .
                            // TODO: Access
                            // (customUserAccess::is_forbidden('deposit_disapproved')
                            (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' __(bool)deposit_disapproved__ == FALSE && ' : '') .
                            '__depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' ) {
                                return true;
                            }
                            return false;
                        ',
                        'formula_return' => false
                    ]);
                }

                $formula['cr'] = 'round( __leads__ > 0 ? ((__deposits__ / __leads__) * 100) : 0, 2)';
            } elseif ($metrics == 'b_cr') {
                $this->formulas->attach(['valid_leads', 'Depositors'], $array);

                $formula['b_cr'] = 'round(__valid_leads__ > 0 && __deposits__ > 0? (__deposits__ / __valid_leads__) * 100 : 0,0)';
            } elseif ($metrics == 'a_cr') {
                $this->formulas->attach(['valid_leads', 'ApprovedDepositors.depositor'], $array);

                $formula['a_cr'] = 'round(__valid_leads__ > 0 && __approveddeposits__ > 0? (__valid_leads__ / __approveddeposits__) * 100 : 0,0)';
            } elseif ($metrics == 'p_cr' || (int)$metrics == 11) {
                $this->formulas->attach(['ApprovedDepositors.depositor', 'Leads'], $array);
                // $formula['p_cr'] = 'round(__leads__ > 0 && __approveddeposits__ > 0? (__leads__ / __approveddeposits__) * 100 : 0,0)';
                $formula['p_cr'] = 'round( __leads__ > 0 ? (( __approveddeposits__ / __leads__ ) * 100) : 0, 2)';
            } elseif ($metrics == 'profit_margin' || (int)$metrics == 9) {
                $this->formulas->attach(['cost', 'deposit_revenue'], $array);

                $formula['pm'] = 'round(__revenue__ > 0 ? ((__revenue__-__cost__)/__revenue__)*100 : 0, 0)';
            } elseif ($metrics == 'profit') {
                $this->formulas->attach(['cost', 'deposit_revenue'], $array);

                $formula['profit'] = 'round((float)__revenue__ - (float)__cost__, 2)';
            } elseif ($metrics == 'avg_rpl' || (int)$metrics == 10) {
                $this->formulas->attach(['deposit_revenue', 'Leads'], $array);

                $formula['rpl'] = 'round(__leads__ > 0 ? __revenue__ / __leads__ : 0,0)';
            } elseif ($metrics == 'cpl' || (int)$metrics == 12) {
                $this->formulas->attach(['cost', 'Leads'], $array);

                $formula['cpl'] = 'round(__leads__ > 0 ? __cost__ / __leads__ : 0, 2)';
            } elseif ($metrics == 'affiliate_cost' || (int)$metrics == 13) {
                $this->formulas->attach('affiliate_cost', $array);
            } elseif ($metrics == 'master_affiliate_payout' || (int)$metrics == 14) {
                $this->formulas->attach('master_affiliate_payout', $array);
            } elseif ($metrics == 'master_brand_payout' || (int)$metrics == 15) {
                $this->formulas->attach('master_brand_payout', $array);
            } elseif ($metrics ==  'mismatch') {
                $this->formulas->attach(['all_leads', 'mismatch'], $array);

                $formula['mismatch'] = 'round(( __leads__ + __mismatch_leads__) > 0 ? ((__mismatch_leads__ / (__leads__ + __mismatch_leads__)) * 100) : 0,0)';
            } elseif ($metrics == 'redirect') {
                $this->formulas->attach('redirect', $array);

                $formula['redirect'] = 'round( __leads__ > 0 ? (( __hit_the_redirect__ / __leads__) * 100) : 0,0)';
            } elseif ($metrics == 'fraudHighRisk') {
                $this->formulas->attach('fraudHighRisk', $array);

                $formula['fraudHighRisk'] = 'round( __leads__ > 0 ? ((__high_risk__ / __leads__) * 100) : 0,0)';
            } elseif ($metrics == 'fraudMediumRisk') {
                $this->formulas->attach('fraudMediumRisk', $array);

                $formula['fraudMediumRisk'] = 'round( __leads__ > 0 ? ((__medium_risk__ / __leads__) * 100) : 0,0)';
            } elseif ($metrics == 'fraudLowRisk') {
                $this->formulas->attach('fraudLowRisk', $array);

                $formula['fraudLowRisk'] = 'round( __leads__ > 0 ? ((__low_risk__ / __leads__) * 100) : 0,0)';
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
// $report = new serviceReporting('', $_POST, $_GET);
// echo json_encode($report->Handler());
