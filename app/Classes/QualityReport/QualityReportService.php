<?php

namespace App\Classes\QualityReport;

use App\Models\User;
use App\Helpers\CryptHelper;
use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use App\Classes\QualityReport\QualityReportMeta;

class QualityReportService extends QualityReportMeta
{

    public $payload;

    private $start;
    private $end;

    public function __construct($payload)
    {
        $this->payload = $payload;
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

    public function Handler()
    {
        // TODO: Access
        // if (!permissionsManagement::is_allow('quality_reports', ['all', 'view'])) {
        //     return ['success' => false, 'message' => permissionsManagement::get_error_message()];
        // }

        $time = $this->buildTimestamp();
        $query = $this->buildParameterArray();

        $conditions = array();
        if (isset($this->payload['broker']) && count(($this->payload['broker'])) > 0) {
            $conditions['brokerId'] = ($conditions['brokerId'] ?? []) + $this->payload['broker'];
        }

        if (isset($this->payload['traffic_endpoint']) && count($this->payload['traffic_endpoint']) > 0) {
            $conditions['TrafficEndpoint'] = ($conditions['TrafficEndpoint'] ?? []) + $this->payload['traffic_endpoint'];
        }

        if (isset($this->payload['campaign']) && count($this->payload['campaign']) > 0) {
            $conditions['CampaignId'] = ($conditions['CampaignId'] ?? []) + $this->payload['campaign'];
        }

        if (isset($this->payload['country'])) {
            $conditions['country'] = ($conditions['country'] ?? []) + $this->payload['country'];
        }

        if (isset($this->payload['language'])) {
            $conditions['language'] = ($conditions['language'] ?? []) + $this->payload['language'];
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

        // $conditions['match_with_broker'] = [new \MongoDB\BSON\Decimal128('1')];

        if (Gate::allows('custom:quality_report[disable_test_lead]')) {
            $conditions['test_lead'] = [new \MongoDB\BSON\Decimal128('0')];
        }

        // traffic endpoints is only assing
        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            list($traffic_endpoints, $account_managers) = $this->getTrafficEndpoints();
            $traffic_endpoint_ids = array_keys($traffic_endpoints);
            if (empty($traffic_endpoint_ids)) {
                $traffic_endpoint_ids[] = 'nothing';
            }
            $conditions['TrafficEndpoint'] ??= [];
            foreach($traffic_endpoint_ids as $traffic_endpoint_id) {
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
            foreach($broker_ids as $broker_id) {
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
                            'depositTimestamp' => array('$gte' => $time['start'], '$lte' => $time['end']),
                            'depositor' => true,
                            'deposit_disapproved' => false
                        ]
                    ]
                ]
            ];
        }

        $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);

        $hooks = [];

        // TODO: Access
        if (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
            $hooks[] = function (&$cell) {
                if ($cell['deposit_disapproved'] && $cell['depositor']) {
                    $status = 'No Answer'; //'Call back - Personal';
                    if ($cell['status'] ?? false) $cell['status'] = $status;
                    if ($cell['broker_status'] ?? false) $cell['broker_status'] = $status;
                }
            };
        }
        if (in_array('account_manager', ($this->payload['pivot'] ?? []))) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_endpoint_account_managers();
            }
            $hooks[] = function (&$cell) use ($account_managers) {
                $cell['account_manager'] = $account_managers[$cell['TrafficEndpoint'] ?? ''] ?? '';
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

        $ext_replace = [
            '__all_leads__' => fn ($k, $d) => (int)($d['all_leads'] ?? 0),
            '__mismatch_leads__' => fn ($k, $d) => (int)($d['mismatch'] ?? 0),
            '__hit_the_redirect__' => fn ($k, $d) => (int)($d['redirect'] ?? 0),
            '__high_risk__' => fn ($k, $d) => (int)($d['fraudHighRisk'] ?? 0),
            '__medium_risk__' => fn ($k, $d) => (int)($d['fraudMediumRisk'] ?? 0),
            '__low_risk__' => fn ($k, $d) => (int)($d['fraudLowRisk'] ?? 0),
        ];

        $f = QueryHelper::attachFormula($data, $query['formula'], $ext_replace);
        // $f = $this->attachFormula($data, $query['formula']);

        foreach ($f as &$d) {
            CryptHelper::decrypt_lead_data_array($d);
        }

        $data = $this->buildView($f);

        return $data;
    }

    private function getTrafficEndpoints(): array
    {
        $cache_key = 'quality_report_traffic_endpoints_' . Auth::id();
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
        $cache_key = 'quality_report_brokers_' . Auth::id();
        $result = Cache::get($cache_key);
        if ($result) {
            return $result;
        }

        $partner = [];
        $where = [];
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

    public function buildView($data)
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

        $except = ['integrationId', 'revenue'];
        $schema = QueryHelper::schemaTitles($this->get_titles(), $data[0], $except);
        $DataSchema = QueryHelper::DataSchema($data[0], $except);

        // $metrics = array_unique(array_merge($this->payload['pivot'] ?? [], $this->payload['metrics'] ?? []));
        $metrics = array_unique($this->payload['metrics'] ?? []);

        for ($i = 0; $i < count($metrics); $i++) {
            $metric = $metrics[$i];
            if (isset(QualityReportMeta::$pivot_metrics[$metric]) && isset(QualityReportMeta::$pivot_metrics[$metric]['name'])) {
                $metrics[$i] = QualityReportMeta::$pivot_metrics[$metric]['name'];
            }
        }

        $sortedDataSchema = [];
        $required = array_merge(['TrafficEndpoint'], $this->payload['pivot'] ?? []); //, 'Leads', 'test'
        foreach ($required as $metric) {
            if (!in_array($metric, $sortedDataSchema)) {
                $sortedDataSchema[] = $metric;
            }
        }

        foreach ($metrics as $metric) {
            if (in_array($metric, $DataSchema)) {
                $sortedDataSchema[] = $metric;
            }
        }

        $data_sources = [
            'sortedDataSchema' => $sortedDataSchema,
            'schema' => $schema,
            'data' => $data,
            'DataSchema' => $DataSchema,
            'TrafficEndpoints' => $traffic_endpoints,
            'account_managers' => $account_managers,
            'partner' => $partner,
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
        $sortedDataSchema = $DataSources['sortedDataSchema'];
        $schema = $DataSources['schema'];
        $data = $DataSources['data'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];

        $result = [
            'columns' => [],
            'items' => [],
        ];

        $money = ['cost', 'adjustment_amount', 'deposit_revenue', 'cpl', 'rpl'];
        $percent = ['cr', 'pm', 'mismatch', 'redirect', 'fraudHighRisk', 'fraudMediumRisk', 'fraudLowRisk'];

        foreach ($sortedDataSchema as $dbs) {

            foreach ($schema as $t) {

                if ($t['name'] == $dbs) {
                    $column = [
                        "key" => $t['name'],
                        "label" => $t['title'],
                    ];

                    if (in_array($dbs, $money)) {
                        $column['renderCell'] = "simple.MoneyColor";
                    }

                    if (in_array($dbs, $percent)) {
                        $column['renderCell'] = "simple.Percent";
                    }

                    $result['columns'][$t['name']] = $column;

                    break;
                }
            }
        }

        $render = function ($dbs, &$datas) use ($TrafficEndpoints, $account_managers, $campaigns, $partner, $integrations) {
            $result = '';
            if ('wrong_number' == $dbs) {
                $wrong_number = ($datas['Leads'] > 0 ? ($datas['wrong_number'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['wrong_number'] > 0 ? $datas['wrong_number'] . ' Leads (' . round($wrong_number, 2) . '%)' : '0%');
            } elseif ('do_not_call' == $dbs) {
                $do_not_call = ($datas['Leads'] > 0 ? ($datas['do_not_call'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['do_not_call'] > 0 ? $datas['do_not_call'] . ' Leads (' . round($do_not_call, 2)  . '%)' : '0%');
            } elseif ('callback' == $dbs) {
                $callback = ($datas['Leads'] > 0 ? ($datas['callback'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['callback'] > 0 ? $datas['callback'] . ' Leads (' . round($callback, 2) . '%)' : '0%');
            } elseif ('not_interested' == $dbs) {
                $not_interested = ($datas['Leads'] > 0 ? ($datas['not_interested'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['not_interested'] > 0 ? $datas['not_interested'] . ' Leads (' . round($not_interested, 2) . '%)' : '0%');
            } elseif ('no_answer' == $dbs) {
                $no_answer = ($datas['Leads'] > 0 ? ($datas['no_answer'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['no_answer'] > 0 ? $datas['no_answer'] . ' Leads (' . round($no_answer, 2) . '%)' : '0%');
            } elseif ('potential' == $dbs) {
                $potential = ($datas['Leads'] > 0 ? ($datas['potential'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['potential'] > 0 ? $datas['potential'] . ' Leads (' . round($potential, 2) . '%)' : '0%');
            } elseif ('Depositors' == $dbs) {
                $Depositors = ($datas['Leads'] > 0 ? ($datas['Depositors'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['Depositors'] > 0 ? $datas['Depositors'] . ' Leads (' . round($Depositors, 2) . '%)' : '0%');
            } elseif ('under_age' == $dbs) {
                $under_age = ($datas['Leads'] > 0 ? ($datas['under_age'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['under_age'] > 0 ? $datas['under_age'] . ' Leads (' . round($under_age, 2) . '%)' : '0%');
            } elseif ('low_quality' == $dbs) {
                $low_quality = ($datas['Leads'] > 0 ? ($datas['low_quality'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['low_quality'] > 0 ? $datas['low_quality'] . ' Leads (' . round($low_quality, 2) . '%)' : '0%');
            } elseif ('language_barrier' == $dbs) {
                $language_barrier = ($datas['Leads'] > 0 ? ($datas['language_barrier'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['language_barrier'] > 0 ? $datas['language_barrier'] . ' Leads (' . round($language_barrier, 2) . '%)' : '0%');
            } elseif ('new' == $dbs) {
                $new = ($datas['Leads'] > 0 ? ($datas['new'] / $datas['Leads']) * 100 : 0);
                $result = ($datas['new'] > 0 ? $datas['new'] . ' Leads (' . round($new, 2) . '%)' : '0%');
            } elseif ('calling' == $dbs) {
                $calling = ($datas['Leads'] > 0 ? ($datas['calling'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['calling'] > 0 ? $datas['calling'] . ' Leads (' . round($calling, 2) . '%)' : '0%');
            } elseif ('test' == $dbs) {
                $test = ($datas['Leads'] > 0 ? ($datas['test'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['test'] > 0 ? $datas['test'] . ' Test Leads (' . round($test, 2) . '%)' : '0%');
            } elseif ('payment_decline' == $dbs) {
                $payment_decline = ($datas['Leads'] > 0 ? ($datas['payment_decline'] / (int)$datas['Leads']) * 100 : 0);
                $result = ($datas['payment_decline'] > 0 ? $datas['payment_decline'] . ' Leads (' . round($payment_decline, 2) . '%)' : '0%');
            } elseif ('TrafficEndpoint' == $dbs) {
                $result = $TrafficEndpoints[strtolower($datas[$dbs])] ?? '';
            } elseif ('account_manager' == $dbs) {
                $result = $account_managers[strtolower($datas[$dbs])] ?? '';
            } elseif ('CampaignId' == $dbs) {
                $result = ($campaigns[$datas[$dbs]] ?? '');
            } elseif ('brokerId' == $dbs) {
                $result = ($partner[strtolower($datas[$dbs])] ?? '');
            } elseif ('integration' == $dbs) {
                $result = $integrations[strtolower($datas['integrationId'] ?? '')] ?? '';
            } else {
                $result = $datas[$dbs];
            }
            return $result;
        };

        $totals = [];

        foreach ($data as $ndata => $datas) {

            $b = true;
            // foreach ($DataSchema as $dbs) {
            foreach ($sortedDataSchema as $dbs) {
                if ('TrafficEndpoint' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $TrafficEndpoints)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            $row = [];
            // foreach ($DataSchema as $dbs) {
            foreach ($sortedDataSchema as $dbs) {
                $totals[$dbs] = ($totals[$dbs] ?? 0) + (is_numeric($datas[$dbs]) ? $datas[$dbs] : 0);
                $row[$dbs] = $render($dbs, $datas);
            }
            $result['items'][] = $row;
        }

        // $html .= '<tfoot><tr class="total" style="background-color: #292f4c;color: #fff !important;">';

        $raw = [];
        // foreach ($DataSchema as $dbs) {
        foreach ($sortedDataSchema as $dbs) {
            $row_schema = self::$pivot_titles[$dbs] ?? [];
            if (($row_schema['total'] ?? false) == false) {
                $totals[$dbs] = '';
            }
            $raw[$dbs] = $render($dbs, $totals);
        }
        $result['totals'] = $raw;

        return $result;
    }

    private function downloadCSV($DataSources)
    {
        $sortedDataSchema = $DataSources['sortedDataSchema'];
        $schema = $DataSources['schema'];
        $data = $DataSources['data'];
        $TrafficEndpoints = $DataSources['TrafficEndpoints'];
        $account_managers = $DataSources['account_managers'];
        $partner = $DataSources['partner'];
        $integrations = $DataSources['integrations'];
        $campaigns = $DataSources['campaigns'];
        $DataSchema = $DataSources['DataSchema'];

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
                } elseif ('pcr' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('pm' == $dbs) {
                    $row[] = $datas[$dbs] . '%';
                } elseif ('wrong_number' == $dbs) {
                    $wrong_number = ($datas['Leads'] > 0 ? ($datas['wrong_number'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['wrong_number'] > 0 ? $datas['wrong_number'] . ' Leads (' . round($wrong_number, 2) . '%)' : '0%');
                } else if ('do_not_call' == $dbs) {
                    $do_not_call = ($datas['Leads'] > 0 ? ($datas['do_not_call'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['do_not_call'] > 0 ? $datas['do_not_call'] . ' Leads (' . round($do_not_call, 2)  . '%)' : '0%');
                } elseif ('callback' == $dbs) {
                    $callback = ($datas['Leads'] > 0 ? ($datas['callback'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['callback'] > 0 ? $datas['callback'] . ' Leads (' . round($callback, 2) . '%)' : '0%');
                } elseif ('not_interested' == $dbs) {
                    $not_interested = ($datas['Leads'] > 0 ? ($datas['not_interested'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['not_interested'] > 0 ? $datas['not_interested'] . ' Leads (' . round($not_interested, 2) . '%)' : '0%');
                } elseif ('no_answer' == $dbs) {
                    $no_answer = ($datas['Leads'] > 0 ? ($datas['no_answer'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['no_answer'] > 0 ? $datas['no_answer'] . ' Leads (' . round($no_answer, 2) . '%)' : '0%');
                } elseif ('potential' == $dbs) {
                    $potential = ($datas['Leads'] > 0 ? ($datas['potential'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['potential'] > 0 ? $datas['potential'] . ' Leads (' . round($potential, 2) . '%)' : '0%');
                } elseif ('Depositors' == $dbs) {
                    $Depositors = ($datas['Leads'] > 0 ? ($datas['Depositors'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['Depositors'] > 0 ? $datas['Depositors'] . ' Leads (' . round($Depositors, 2) . '%)' : '0%');
                } elseif ('under_age' == $dbs) {
                    $under_age = ($datas['Leads'] > 0 ? ($datas['under_age'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['under_age'] > 0 ? $datas['under_age'] . ' Leads (' . round($under_age, 2) . '%)' : '0%');
                } elseif ('low_quality' == $dbs) {
                    $low_quality = ($datas['Leads'] > 0 ? ($datas['low_quality'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['low_quality'] > 0 ? $datas['low_quality'] . ' Leads (' . round($low_quality, 2) . '%)' : '0%');
                } elseif ('language_barrier' == $dbs) {
                    $language_barrier = ($datas['Leads'] > 0 ? ($datas['language_barrier'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['language_barrier'] > 0 ? $datas['language_barrier'] . ' Leads (' . round($language_barrier, 2) . '%)' : '0%');
                } elseif ('new' == $dbs) {
                    $new = ($datas['Leads'] > 0 ? ($datas['new'] / $datas['Leads']) * 100 : 0);
                    $row[] = ($datas['new'] > 0 ? $datas['new'] . ' Leads (' . round($new, 2) . '%)' : '0%');
                } elseif ('calling' == $dbs) {
                    $calling = ($datas['Leads'] > 0 ? ($datas['calling'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['calling'] > 0 ? $datas['calling'] . ' Leads (' . round($calling, 2) . '%)' : '0%');
                } elseif ('test' == $dbs) {
                    $test = ($datas['Leads'] > 0 ? ($datas['test'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['test'] > 0 ? $datas['test'] . ' Test Leads (' . round($test, 2) . '%)' : '0%');
                } elseif ('payment_decline' == $dbs) {
                    $payment_decline = ($datas['Leads'] > 0 ? ($datas['payment_decline'] / (int)$datas['Leads']) * 100 : 0);
                    $row[] = ($datas['payment_decline'] > 0 ? $datas['payment_decline'] . ' Leads (' . round($payment_decline, 2) . '%)' : '0%');
                } else {
                    $row[] = $datas[$dbs];
                }
            }

            fputcsv($handle, $row, $delimiter);
        }

        fclose($handle);
    }

    public function buildTimestamp()
    {
        $string = $this->payload['timeframe'];
        $explode = explode(' - ', $string);

        $time_range = array();

        $this->start = strtotime($this->givebackstamp($explode[0]) . " 00:00:00");
        $this->end = strtotime($this->givebackstamp($explode[1]) . " 23:59:59");

        $time_range['start'] = new \MongoDB\BSON\UTCDateTime($this->start * 1000);
        $time_range['end'] = new \MongoDB\BSON\UTCDateTime($this->end * 1000);
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

        $array[] = array('TrafficEndpoint' => 'val');

        foreach (($this->payload['pivot'] ?? []) as $pivot) {
            $array[] = array($pivot => 'val');

            if ($pivot == 'integration') {
                $array[] = array('integrationId' => 'val');
            }
            if ($pivot == 'account_manager') {
                $array['TrafficEndpoint'] = array('TrafficEndpoint' => 'val');
            }
        }

        $array['all_leads'] = array('all_leads' => [
            'type' => 'count',
            'formula' => '
                if ( __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                    return true;
                }
                return false;
            ',
            'formula_return' => false
        ]);

        //$array['Leads'] = array('Leads' => 'count', 'where' => 'match_with_broker', 'value' => 1);
        $array['Leads'] = array('Leads' => [
            'type' => 'count',
            'formula' => '
                if ( __match_with_broker__ == 1 && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                    return true;
                }
                return false;
            ',
            'formula_return' => false
        ]);

        //$array['Depositors'] = array('Depositors' => array('type' => 'count', 'where' => 'depositor', 'value' => true));
        $array['Depositors'] = array('Depositors' => [
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

        $statuses = [
            'wrong_number' => 'Wrong Number',
            'do_not_call' => 'Do Not Call',
            'callback' => 'Callback',
            'not_interested' => 'Not Interested',
            'no_answer' => 'No Answer',
            'potential' => 'Call back - Personal',
            'under_age' => 'Under Age',
            'language_barrier' => 'Language barrier',
            'low_quality' => 'Low Quality',
            'new' => 'new',
            'calling' => 'Calling',
            'test' => 'test',
            'payment_decline' => 'Payment Decline',
            'invalid' => 'Invalid'
        ];

        foreach ($statuses as $status => $status_value) {
            $array[$status] = array($status => [
                'type' => 'count',
                'formula' => '
                    if (preg_match(\'/' . $status_value . '/i\', __(string)status__) &&  __match_with_broker__ == 1 && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                        return true;
                    }
                    return false;
                ',
                'formula_return' => false
            ]);
        }

        // $array['wrong_number'] = array('wrong_number' => array('type' => 'count', 'where' => 'status', 'value' => 'Wrong Number'));
        // $array['do_not_call'] = array('do_not_call' => array('type' => 'count', 'where' => 'status', 'value' => 'Do Not Call'));
        // $array['callback'] = array('callback' => array('type' => 'count', 'where' => 'status', 'value' => 'Callback'));
        // $array['not_interested'] = array('not_interested' => array('type' => 'count', 'where' => 'status', 'value' => 'Not Interested'));
        // $array['no_answer'] = array('no_answer' => array('type' => 'count', 'where' => 'status', 'value' => 'No Answer'));
        // $array['potential'] = array('potential' => array('type' => 'count', 'where' => 'status', 'value' => 'Call back - Personal'));
        // $array['under_age'] = array('under_age' => array('type' => 'count', 'where' => 'status', 'value' => 'Under Age'));
        // $array['language_barrier'] = array('language_barrier' => array('type' => 'count', 'where' => 'status', 'value' => 'Language barrier'));
        // $array['low_quality'] = array('low_quality' => array('type' => 'count', 'where' => 'status', 'value' => 'Low Quality'));
        // $array['new'] = array('new' => array('type' => 'count', 'where' => 'status', 'value' => 'new'));
        // $array['calling'] = array('Calling' => array('type' => 'count', 'where' => 'status', 'value' => 'Calling'));
        // $array['test'] = array('test' => array('type' => 'count', 'where' => 'status', 'value' => 'test'));
        // $array['payment_decline'] = array('Payment Decline' => array('type' => 'count', 'where' => 'status', 'value' => 'Payment Decline'));

        foreach (($this->payload['pivot'] ?? []) as $pivot) {
            if ($pivot == 'ftd') {
                //$array['ftd'] = array('Depositors' => array('type' => 'count', 'where' => 'depositor', 'value' => true));
                $array['ftd'] = array('Depositors' => [
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
        }

        foreach (($this->payload['metrics'] ?? []) as $metric) {
            switch ($metric) {
                case 'test': {
                        $array['test'] = array('test' => [
                            'type' => 'count',
                            'formula' => '
                        if ( (bool)__test_lead__ == TRUE ) {
                            return true;
                        }
                        return false;
                    ',
                            'formula_return' => false
                        ]);
                        break;
                    }
                case 'mismatch': {
                        $array['mismatch'] = array('mismatch_leads' => [
                            'type' => 'count',
                            'formula' => '
                            if ( __match_with_broker__ == 0 && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' ) {
                                return true;
                            }
                            return false;
                        ',
                            'formula_return' => false
                        ]);

                        $formula['mismatch'] = 'round(( __leads__ + __mismatch_leads__) > 0 ? ((__mismatch_leads__ / (__leads__ + __mismatch_leads__)) * 100) : 0,0)';
                        break;
                    }
                case 'redirect': {

                        // if (!in_array('redirect', $array)) {
                        $array['redirect'] = array('hit_the_redirect' => [
                            'type' => 'count',
                            'formula' => '
                                if ( __(bool)hit_the_redirect__ == TRUE ) {
                                    return true;
                                }
                                return false;
                            ',
                            'formula_return' => false
                        ]);
                        // }

                        $formula['redirect'] = 'round( __leads__ > 0 ? (( __hit_the_redirect__ / __leads__) * 100) : 0,0)';
                        break;
                    }
                case 'fraudHighRisk': {
                        $array['fraudHighRisk'] = array('fraud_high_risk' => [
                            'type' => 'count',
                            'formula' => '
                            if ( __(string)riskScale__ == "High Risk" || __(string)riskScale__ == "Very High Risk" ) {
                                return true;
                            }
                            return false;
                        ',
                            'formula_return' => false
                        ]);

                        $formula['fraudHighRisk'] = 'round( __leads__ > 0 ? ((__high_risk__ / __leads__) * 100) : 0,0)';
                        break;
                    }
                case 'fraudMediumRisk': {
                        $array['fraudMediumRisk'] = array('fraud_medium_risk' => [
                            'type' => 'count',
                            'formula' => '
                            if ( __(string)riskScale__ == "Medium Risk" ) {
                                return true;
                            }
                            return false;
                        ',
                            'formula_return' => false
                        ]);

                        $formula['fraudMediumRisk'] = 'round( __leads__ > 0 ? ((__medium_risk__ / __leads__) * 100) : 0,0)';
                        break;
                    }
                case 'fraudLowRisk': {
                        $array['fraudLowRisk'] = array('fraud_low_risk' => [
                            'type' => 'count',
                            'formula' => '
                            if ( __(string)riskScale__ == "Low Risk" ) {
                                return true;
                            }
                            return false;
                        ',
                            'formula_return' => false
                        ]);

                        $formula['fraudLowRisk'] = 'round( __leads__ > 0 ? ((__low_risk__ / __leads__) * 100) : 0,0)';
                        break;
                    }
            }
        }

        $formula['cr'] = 'round( __leads__ > 0 ? ((__deposits__ / __leads__) * 100) : 0,0)';

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }
}
//error_reporting(E_ALL);
//ini_set("display_errors","On");
// $qreport = new serviceQualityReport('', $_POST);
// echo json_encode($qreport->Handler());
