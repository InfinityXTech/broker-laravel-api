<?php

namespace App\Classes\MarketingReport;

use App\Models\User;
use App\Helpers\QueryHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\MarketingReport\MarketingReportMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\Mongo\MongoQueryCache;
use App\Helpers\GeneralHelper;

class MarketingReportService extends MarketingReportMeta
{

    public $post;

    private $start;
    private $end;

    private $result;
    private $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
        parent::__construct();
    }

    private function get_affiliate_account_managers()
    {
        $where = ['account_manager' => ['$nin' => [null, '']]];
        $mongo = new MongoDBObjects('marketing_affiliates', $where);
        $find = $mongo->findMany();
        $account_managers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $account_managers[($id['oid'])] = $supply['account_manager'];
        }
        return $account_managers;
    }

    public function Handler()
    {

        $time = $this->buildTimestamp();
        $query = $this->buildParameterArray();

        $conditions = array();

        if (!empty($this->payload['ClickID'])) {
            $conditions['ClickID'] = $this->payload['ClickID'];
        }

        if (isset($this->payload['advertiser']) && count($this->payload['advertiser']) > 0) {
            $conditions['AdvertiserId'] = ($conditions['AdvertiserId'] ?? []) + $this->payload['advertiser'];
        }

        if (isset($this->payload['affiliate']) && count($this->payload['affiliate']) > 0) {
            $conditions['AffiliateId'] = ($conditions['AffiliateId'] ?? []) + $this->payload['affiliate'];
        }

        if (isset($this->payload['country']) && count($this->payload['country']) > 0) {
            $conditions['GeoCountryName'] = ($conditions['GeoCountryName'] ?? []) + $this->payload['country'];
        }

        if (isset($this->payload['language']) && count($this->payload['language']) > 0) {
            $conditions['UserLanguage'] = ($conditions['UserLanguage'] ?? []) + $this->payload['language'];
        }

        if (isset($this->payload['account_manager']) && count($this->payload['account_manager']) > 0) {

            if (!isset($account_managers)) {
                $account_managers = $this->get_affiliate_account_managers();
            }

            $filter_affiliates = [];
            if (isset($this->payload['AffiliateId']) && count($this->payload['AffiliateId']) > 0) {
                $filter_affiliates = $this->payload['AffiliateId'];
            }

            $added = false;
            foreach ($account_managers as $affiliate => $account_manager) {

                $pre_filter_allow = true;
                if (count($filter_affiliates) > 0 && !in_array($affiliate, $filter_affiliates)) {
                    $pre_filter_allow = false;
                }

                if ($pre_filter_allow && is_string($this->payload['account_manager']) && $account_manager == $this->payload['account_manager']) {
                    $conditions['AffiliateId'][] = $affiliate;
                    $added = true;
                } else
                if ($pre_filter_allow && is_array($this->payload['account_manager']) && in_array($account_manager, $this->payload['account_manager'])) {
                    $conditions['AffiliateId'][] = $affiliate;
                    $added = true;
                }
            }

            if (!$added) {
                $conditions['AffiliateId'] = ['false'];
            }
        }

        // $conditions['Approved'] = ['Approved' => true, 'Rejected' => true];

        $condition = QueryHelper::buildConditions($conditions);

        $queryMongo = new MongoQuery($time, ['mleads', 'mleads_event'], $query['query'], $condition);

        $hooks = [];

        // TODO: Access
        // if (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]')) {
        //     $hooks[] = function (&$cell) {
        //         if (($cell['deposit_disapproved'] ?? false) && ($cell['conversion'] ?? false)) {
        //             $status = 'No Answer'; //'Call back - Personal';
        //             if ($cell['status'] ?? false) $cell['status'] = $status;
        //             if ($cell['broker_status'] ?? false) $cell['broker_status'] = $status;
        //         }
        //     };
        // }

        if (in_array('account_manager', $this->payload['pivot'])) {
            if (!isset($account_managers)) {
                $account_managers = $this->get_affiliate_account_managers();
            }
            $hooks[] = function (&$cell) use ($account_managers) {
                $cell['account_manager'] = $account_managers[$cell['AffiliateId'] ?? ''] ?? '';
            };
        }

        if (!in_array('AffiliateId', $this->payload['pivot'])) {
            $hooks[] = function (&$cell) {
                unset($cell['AffiliateId']);
            };
        }

        $args = [
            'data' => 1,
            'projection' => ['collection' => 1],
            'hook_data' => function (&$data) use ($hooks) {
                foreach ($hooks as $hook) {
                    foreach ($data as &$v) {
                        $hook($v);
                    }
                }
            }
        ];

        $this->result = $queryMongo->queryMongo($args);

        $data = $this->result['result'];

        $f = QueryHelper::attachMarketingFormula($data, $query['formula']);

        $result = $this->buildView($f, $query);

        return $result;
    }

    public function buildView($data, $query)
    {

        if (count($data) == 0) {
            return [
                'columns' => [],
                'items' => []
            ];
        }

        $where = array();
        $mongo = new MongoDBObjects('marketing_campaigns', $where);
        $find = $mongo->findMany();
        $campaigns = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $campaigns[$id['oid']] = ($supply['name'] ?? '');
        }

        $twhere = [];
        if (Gate::allows('marketing_reports[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $twhere['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        }

        $mongo = new MongoDBObjects('marketing_affiliates', $twhere);
        $find = $mongo->findMany();
        $affiliates = array();
        $account_managers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $affiliates[strtolower($id['oid'])] = isset($supply['name']) ? $supply['name'] . (isset($supply['token']) ? ' (' . $supply['token'] . ')' : '') : $supply['token'] ?? '';

            $account_manager = $supply['account_manager'] ?? '';
            if (!empty($account_manager)) {
                $user = User::query()->find($account_manager);
                $account_managers[strtolower($account_manager)] = $user != null ? $user->name : '';
            }
        }

        $where = array();
        $mongo = new MongoDBObjects('marketing_advertisers', $where);
        $find = $mongo->findMany();
        $advertisers = array();

        foreach ($find as $supply) {
            $id = (array)$supply['_id'];
            $advertisers[$id['oid']] = isset($supply['name']) ? $supply['name'] . (isset($supply['token']) ? ' (' . $supply['token'] . ')' : '') : $supply['token'] ?? '';
        }

        // $where = array();
        // $mongo = new MongoDBObjects('Masters', $where);
        // $find = $mongo->findMany();
        $masters = array();

        // foreach ($find as $supply) {
        //     $id = (array)$supply['_id'];
        //     $masters[strtolower($id['oid'])] = ($supply['token'] ?? '');
        // }

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();

        $download = $this->payload['download'] ?? '';

        $metrics = $this->payload['metrics'];
        $fields = $data[0];

        $except = []; //'revenue'];
        if (!in_array('AffiliateId', $this->payload['pivot'])) {
            $except[] = 'AffiliateId';
        }
        $schema = QueryHelper::schemaTitles($this->get_titles(), $data[0], $except);
        $DataSchema = QueryHelper::DataSchema($data[0], $except);

        $pivot = isset($this->payload['pivot']) && is_array($this->payload['pivot']) ? $this->payload['pivot'] : [];
        $ordered_metrics = $metrics; // isset($this->payload['ordered_metrics']) && is_array($this->payload['ordered_metrics']) ? $this->payload['ordered_metrics'] : [];
        $metrics = array_unique(array_merge($pivot, $ordered_metrics)); //metrics

        for ($i = 0; $i < count($metrics); $i++) {
            $metric = $metrics[$i];
            if (isset(MarketingReportMeta::$pivot_metrics[$metric]) && isset(MarketingReportMeta::$pivot_metrics[$metric]['name'])) {
                $metrics[$i] = MarketingReportMeta::$pivot_metrics[$metric]['name'];
            }
        }

        $sortedDataSchema = [];
        foreach ($metrics as $metric) {
            if (in_array($metric, $DataSchema)) {
                $sortedDataSchema[] = $metric;
            }
        }
        foreach ($DataSchema as $metric) {
            if (!in_array($metric, $sortedDataSchema)) {
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
            'affiliates' => $affiliates,
            'account_managers' => $account_managers,
            'partner' => $advertisers,
            'masters' => $masters,
            'countries' => $countries,
            'languages' => $languages,
            'campaigns' => $campaigns
        ];

        switch ($download) {
            case 'csv': {
                    return [
                        'callback' => function () use ($data_sources) {
                            $this->downloadCSV($data_sources);
                        }
                    ];
                    break;
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
        $affiliates = $DataSources['affiliates'];
        $account_managers = $DataSources['account_managers'];
        $advertisers = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $countries = $DataSources['countries'];
        $languages = $DataSources['languages'];
        $campaigns = $DataSources['campaigns'];

        $result = [
            'columns' => [],
            'items' => []
        ];

        $money = ['cost', 'revenue', 'profit', 'cpl', 'rpl'];
        $percent = ['cr', 'pm'];
        foreach ($sortedDataSchema as $dbs) {

            foreach ($schema as $t) {
                if ($t['name'] == $dbs) {
                    $column = [
                        "key" => $t['name'],
                        "label" => $t['title'],
                    ];

                    if ($dbs == "ClickID") {
                        $column['renderCell'] = "custom.ClickID";
                    }

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
            // }
        }

        $totals = [];

        $render = function ($dbs, &$datas) use (
            $advertisers,
            $affiliates,
            $account_managers,
            $masters,
            $countries,
            $languages,
            $campaigns
        ) {
            $result = '';

            if (isset($datas[$dbs])) {

                if ($datas[$dbs] === '') {
                    $result = '';
                } else 
                if ('AdvertiserId' == $dbs) {
                    $result = ($advertisers[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('AffiliateId' == $dbs) {
                    $result = ($affiliates[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $result = ($account_managers[strtolower($datas[$dbs]) ?? ''] ?? '');
                    // } elseif ('MasterAffiliate' == $dbs) {
                    //     $result = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                    // } elseif ('master_brand' == $dbs) {
                    //     $result = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('GeoCountryName' == $dbs) {
                    $result = ($countries[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('UserLanguage' == $dbs) {
                    $result = ($languages[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('EventTimeStamp' == $dbs) {
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

        foreach ($data as $ndata => $datas) {

            $c = $c + 1;
            $b = true;
            foreach ($DataSchema as $dbs) {
                if ('AffiliateId' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $affiliates)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            $raw = [];
            foreach ($sortedDataSchema as $dbs) { //DataSchema

                $row_schema = null;
                if (isset(self::$pivot_metrics[$dbs])) {
                    $row_schema = self::$pivot_metrics[$dbs];
                } else if (isset(self::$pivot_titles[$dbs])) {
                    $row_schema = self::$pivot_titles[$dbs];
                }
                $is_total = isset($row_schema) && isset($row_schema['total']);
                if ($is_total) {
                    $totals[$dbs] = (isset($totals[$dbs]) ? $totals[$dbs] : 0) + (float)($datas[$dbs] ?? 0);
                } else {
                    $totals[$dbs] = '';
                }

                $raw[$dbs] = $render($dbs, $datas);
            }
            $result['items'][] = $raw;
        }

        // total
        $raw = [];
        foreach ($sortedDataSchema as $dbs) {
            $row_schema = null;
            if (isset(self::$pivot_metrics[$dbs])) {
                $row_schema = self::$pivot_metrics[$dbs];
            } else if (isset(self::$pivot_titles[$dbs])) {
                $row_schema = self::$pivot_titles[$dbs];
            }
            if (isset($row_schema['total'])) {
                if (isset($row_schema['aggregate']) && $row_schema['aggregate'] == 'avg') {
                    $totals[$dbs] = round($totals[$dbs] ?? 0 / $c, 2);
                }
                if (isset($row_schema['total_formula'])) {
                    if ($row_schema['total_formula'] == true) {
                        if ($query['formula'][$dbs]) {
                            $formula = $query['formula'][$dbs];
                            if (is_string($formula) && !empty($formula)) {
                                $r = QueryHelper::attachMarketingFormula([$totals], [
                                    $dbs => $formula
                                ]);
                                $totals[$dbs] = $r[0][$dbs];
                            }
                        }
                    }
                }
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
        $DataSchema = $DataSources['DataSchema'];
        $affiliates = $DataSources['affiliates'];
        $account_managers = $DataSources['account_managers'];
        $advertisers = $DataSources['partner'];
        $masters = $DataSources['masters'];
        $countries = $DataSources['countries'];
        $languages = $DataSources['languages'];
        $campaigns = $DataSources['campaigns'];

        // $filename = 'export_bi_' . date('Y-m-d') . '.csv';
        $delimiter = ',';

        // header('Content-Type: application/csv');
        // header('Content-Disposition: attachment; filename="' . $filename . '";');

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
                if ('AffiliateId' == $dbs) {
                    $ep = strtolower($datas[$dbs]);
                    if (!array_key_exists($ep, $affiliates)) $b = false;
                    break;
                }
            }
            if (!$b) continue;

            $c = $c + 1;
            $row = [];
            foreach ($sortedDataSchema as $dbs) { //DataSchema

                if ('AdvertiserId' == $dbs) {
                    $row[] = ($advertisers[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('AffiliateId' == $dbs) {
                    $row[] = ($affiliates[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('account_manager' == $dbs) {
                    $row[] = ($account_managers[strtolower($datas[$dbs]) ?? ''] ?? '');
                    // } elseif ('MasterAffiliate' == $dbs) {
                    //     $row[] = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                    // } elseif ('master_brand' == $dbs) {
                    //     $row[] = ($masters[strtolower($datas[$dbs]) ?? ''] ?? '');
                } elseif ('EventTimeStamp' == $dbs) {
                    $ts = (array)$datas[$dbs];
                    $mil = $datas[$dbs];
                    $seconds = $mil / 1000;
                    $row[] = date("d-m-Y H:i:s", $seconds);
                } elseif ('CampaignId' == $dbs) {
                    $row[] = ($campaigns[$datas[$dbs] ?? ''] ?? '');
                } elseif ('cost' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('revenue' == $dbs) {
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
                    // } elseif ('master_brand_payout' == $dbs) {
                    //     $row[] = '$' . $datas[$dbs];
                    // } elseif ('master_affiliate_payout' == $dbs) {
                    //     $row[] = '$' . $datas[$dbs];
                } elseif ('profit' == $dbs) {
                    $row[] = '$' . $datas[$dbs];
                } elseif ('GeoCountryName' == $dbs) {
                    $row[] = ($countries[strtolower($datas[$dbs] ?? '')] ?? '');
                } elseif ('UserLanguage' == $dbs) {
                    $row[] = ($languages[strtolower($datas[$dbs] ?? '')] ?? '');
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

        $time_range['where'] = [
            '$and' => [
                ['EventTimeStamp' => ['$gte' => $start, '$lte' => $end]],
                [
                    '$or' => [
                        ['Approved' => true],
                        ['Rejected' => true]
                    ]
                ]
            ]
        ];

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

            if ($pivot == 'account_manager') {
                $array['AffiliateId'] = array('AffiliateId' => 'val');
            }
        }

        $_affiliatePayout = array('cost' => [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
				$affiliatePayout = 0.0;
				if ( __(bool)Approved__ == TRUE ) {
					$affiliatePayout = __AffiliatePayout__;
				}
                return (float)$affiliatePayout;
            ',
            'formula_return' => false
        ]);

        $_advertiserPayout = array('revenue' =>  [
            'type' => 'sum',
            'field_type' => 'float',
            'formula' => '
                $advertiserPayout = 0.0;
                //if ( __(bool)Approved__ == TRUE || __(bool)Rejected__ == TRUE) {
                    $advertiserPayout = __AdvertiserPayout__;
				//}
                return (float)$advertiserPayout;
                ',
            'formula_return' => false
        ]);

        $_leads = array('Leads' => [
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

        $_conversions = array('conversion' => [
            'type' => 'count',
            'formula' => '
                if ( __(string)collection__ == "mleads" && __(bool)conversion__ == TRUE && strtoupper(__(string)EventType__) != "CLICK" ) {
                    return true;
                }
                return false;
            ',
            'formula_return' => false
        ]);

        foreach ($post_metrics as $metrics) {

            if ($metrics == 'revenue') {
                $array['revenue'] = $_advertiserPayout;
            } elseif ($metrics == 'cost') {
                $array['cost'] = $_affiliatePayout;
            } elseif ($metrics == 'leads') {
                $array['Leads'] = $_leads;
            } elseif ($metrics == 'conversion') {
                $array['Conversions'] = $_conversions;
            } elseif ($metrics == 'approved_conversion') {
                $array['ApprovedConversions'] = array('approved_conversion' => [
                    'type' => 'count',
                    'formula' => '
						if ( __(string)collection__ == "mleads" && __(bool)conversion__ == TRUE && strtoupper(__(string)EventType__) != "CLICK" && __(bool)Approved__ == TRUE ) {
							return true;
						}
						return false;
                    ',
                    'formula_return' => false
                ]);
            } elseif ($metrics == 'blocked_leads') {
                $array['blocked_leads'] = array('blocked_leads' => [
                    'type' => 'count',
                    'formula' => '
						if ( __(string)collection__ == "mleads" && strtoupper(__(string)EventType__) == "CLICK" && __(bool)Blocked__ == TRUE ) {
							return true;
						}
						return false;
                    ',
                    'formula_return' => false
                ]);
            } elseif ($metrics == 'test_lead') {
                $array['test_lead'] = array('test_lead' => [
                    'type' => 'count',
                    'formula' => '
                        if ( __(bool)test_lead__ == TRUE) {
                            return true;
                        }
                        return false;
                    ',
                    'formula_return' => false
                ]);
            } elseif ($metrics == 'cr') {
                if (!isset($array['Leads'])) {
                    $array['Leads'] = $_leads;
                }

                if (!isset($array['Conversions'])) {
                    $array['Conversions'] = $_conversions;
                }
                $formula['cr'] = 'round( __leads__ > 0 ? ((__conversions__ / __leads__) * 100) : 0,0)';
            } elseif ($metrics == 'p_cr' || (int)$metrics == 11) {
                if (!isset($array['Leads'])) {
                    $array['Leads'] = $_leads;
                }
            } elseif ($metrics == 'profit_margin') {
                if (!isset($array['cost'])) {
                    $array['cost'] = $_affiliatePayout;
                }
                if (!isset($array['revenue'])) {
                    $array['revenue'] = $_advertiserPayout;
                }
                $formula['pm'] = 'round(__revenue__ > 0 ? ((__revenue__-__cost__)/__revenue__)*100 : 0, 0)';
            } elseif ($metrics == 'profit') {
                if (!isset($array['cost'])) {
                    $array['cost'] = $_affiliatePayout;
                }
                if (!isset($array['revenue'])) {
                    $array['revenue'] = $_advertiserPayout;
                }
                $formula['profit'] = 'round((float)__revenue__ - (float)__cost__, 2)';
            } elseif ($metrics == 'avg_rpl') {
                if (!isset($array['revenue'])) {
                    $array['revenue'] = $_advertiserPayout;
                }
                if (!isset($array['Leads'])) {
                    $array['Leads'] = $_leads;
                }
                $formula['rpl'] = 'round(__leads__ > 0 ? __revenue__ / __leads__ : 0,0)';
            } elseif ($metrics == 'cpl') {

                if (!isset($array['cost'])) {
                    $array['cost'] = $_affiliatePayout;
                }

                if (!isset($array['Leads'])) {
                    $array['Leads'] = $_leads;
                }

                $formula['cpl'] = 'round(__leads__ > 0 ? __cost__ / __leads__ : 0, 2)';
            } elseif ($metrics == 'cost') {
                if (!isset($array['cost'])) {
                    $array['cost'] = $_affiliatePayout;
                }
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
