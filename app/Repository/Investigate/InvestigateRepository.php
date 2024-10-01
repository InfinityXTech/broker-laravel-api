<?php

namespace App\Repository\Investigate;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use stdClass;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\CryptHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Investigate\IInvestigateRepository;

class InvestigateRepository extends BaseRepository implements IInvestigateRepository
{
    public function __construct()
    {
    }

    private function decrypt_array($data)
    {
        try {
            if ($data != null) {
                if (is_array($data) && count($data) > 0) {
                    foreach ($data as $k => &$v) {
                        if (isset($v['$oid'])) {
                            continue;
                        }
                        if (is_string($v) && !empty($v)) {
                            // $c = $v[strlen($v) - 1];
                            // if ($c == '=') {
                            $_v = CryptHelper::decrypt($v);
                            if (!empty($_v)) {
                                $v = $_v;
                            }
                            // }
                        } else if ($v != null && is_array($v) && count($v) > 0) {
                            $v = $this->decrypt_array($v);
                        }
                    }
                } else if (is_string($data)) {
                    $data = CryptHelper::decrypt($data);
                }
            }
        } catch (\Exception $ex) {
        }
        return $data;
    }

    public function fetchUser(string $leadId)
    { // we will fetch user Logs and Data
        $where = array();
        $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
        $mongo = new MongoDBObjects('leads', $where);
        $user = $mongo->find([
            'projection' => [
                '_id' => 1,
                'Timestamp' => 1,
                'TrafficEndpoint' => 1,
                'first_name' => 1,
                'last_name' => 1,
                'email' => 1,
                'country' => 1,
                'language' => 1,
                'publisher_click' => 1,
                'funnel_lp' => 1,
                'phone' => 1,
                'match_with_broker' => 1,
                'log_post' => 1,
                'log_php_input' => 1,
                'redirect_info_logs' => 1,

                'redirect_url' => 1,
                'real_ip' => 1,
                'real_useragent' => 1,
                'real_country' => 1,

                'riskReason' => 1,
                'blockReasons' => 1
            ]
        ]);

        $where = array();
        $where = ['$or' => [
            ['user_id' => $leadId],
            ['user_id' => new \MongoDB\BSON\ObjectId($leadId)]
        ]];
        $mongo_log = new MongoDBObjects('logs_serving', $where);
        $user_log = $mongo_log->findMany();

        foreach ($user_log as &$d) {
            if (isset($d['requestResponse']) && isset($d['requestResponse']['request'])) {
                $request = $d['requestResponse']['request'];
                if (!is_string($request)) {
                    $request = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($request)), true);
                }
                $d['requestResponse']['request'] = $this->decrypt_array($request);
            }
        }

        $data = array();
        $data['user_data'] = $user;
        $data['user_log'] = $user_log;

        return $data;
    }

    public function logs(string $leadId): array
    {

        $result = [
            'user_data' => [],
            'user_log' => [],
        ];

        if (empty($leadId)) {
            return $result;
        }

        $render_executedSeconds = function ($requestResponse) {
            if (isset($requestResponse['executedSeconds'])) {
                $executedSeconds = $requestResponse['executedSeconds'];
                // if ($executedSeconds > 6) {
                // return ' (<span style="color:red;">' . $executedSeconds . ' seconds</span>)';
                // } else {
                return ' (' . $executedSeconds . ' seconds)';
                // }
            }
            return '';
        };

        $data = $this->fetchUser($leadId);
        //user data table
        $mongocampaign = new MongoDBObjects('campaigns', array());
        $findcampaign = $mongocampaign->findMany();

        $campaigns = array();

        foreach ($findcampaign as $end) {
            $idend = (array)$end['_id'];
            $oidend = $idend['oid'];

            $campaigns[strtolower($oidend)] = $end['name'];
        }

        $mongo = new MongoDBObjects('partner', ['partner_type' => '1']);
        $find = $mongo->findMany();
        $broker = array();
        foreach ($find as $b) {
            $id = (array)$b['_id'];
            $broker[$id['oid']] = GeneralHelper::broker_name($b);
        }

        $mongo = new MongoDBObjects('broker_integrations', []);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
        $broker_integrations = array();
        foreach ($find as $b) {
            $id = (array)$b['_id'];
            $broker_integrations[$id['oid']] = GeneralHelper::broker_integration_name($b); //$b['name'] ?? '';
        }

        $mongoendpoint = new MongoDBObjects('TrafficEndpoints', array());
        $findendpoint = $mongoendpoint->findMany(['projection' => ['_id' => 1, 'token' => 1]]);
        $endpoints = array();

        foreach ($findendpoint as $end) {
            $idend = (array)$end['_id'];
            $oidend = $idend['oid'];

            $endpoints[strtolower($oidend)] = $end['token'] ?? '';
        }

        $filter = ['Timestamp', 'first_name', 'last_name', 'email', 'country', 'language', 'publisher_click', 'funnel_lp', 'phone'];

        //if ($this->user == '5f686bc7f6cfb63ad84ae912' or $this->user == '5f748269a55c606b1907fc82'  or $this->user == '60534e6a8e01d72cc252e200') {
        // if (auth::is_current_user_admin() || permissionsManagement::is_current_user_role('tech_support')) {
        // 	echo '<div class="form_label" style="width:100%">Post Data</div>';
        // 	echo '<div style=" font-size: 11px; " class="json">' . $data['user_data']['log_post'] . '</div>';
        // 	echo '<div style=" font-size: 11px; ">' . $data['user_data']['log_php_input'] . '</div>';
        $filter[] = 'log_post';
        $filter[] = 'log_php_input';
        // }

        $redirect_info_logs = (array)($data['user_data']['redirect_info_logs'] ?? []);

        usort($redirect_info_logs, function ($a, $b) {
            $mil = $mil2 = 0;
            if (isset($b['timestamp'])) {
                $ts = (array)$a['timestamp'];
                $mil = $ts['milliseconds'];
            }

            if (isset($b['timestamp'])) {
                $ts2 = (array)$b['timestamp'];
                $mil2 = $ts2['milliseconds'];
            }

            return $mil < $mil2;
        });

        $user_data_redirect_logs = [
            'columns' => [
                "_id" => [
                    "label" => "#",
                    "sortable" => false,
                    "renderCell" => "simple.Index"
                ],
                "timestamp" => [
                    "label" => "Timestamp",
                    "renderCell" => "simple.DateTime"
                ],
                "riskScore" => [
                    "label" => "Risk Score",
                ],
                "riskScale" => [
                    "label" => "Risk Scale",
                ],
                "riskReason" => [
                    "label" => "Risk Reason",
                    "sortable" => false,
                    "renderCell" => "custom.RiskReason"
                ],
                "blockReasons" => [
                    "label" => "Block Reasons",
                    "sortable" => false,
                    "renderCell" => "custom.BlockReasons"
                ],
                "hit_the_redirect" => [
                    "label" => "Redirected",
                ],
                "real_useragent" => [
                    "label" => "User Agent",
                ],
                "DeviceBrand" => [
                    "label" => "Device Brand",
                ],
                "UserLanguage" => [
                    "label" => "User Language",
                ],
                "OS" => [
                    "label" => "OS",
                ],
                "OSVersion" => [
                    "label" => "OS Version",
                ],
                "Browser" => [
                    "label" => "Browser",
                ],
                "OSBrowser" => [
                    "label" => "OS Browser",
                ],
                "DeviceType" => [
                    "label" => "DeviceType",
                ],
                "real_language" => [
                    "label" => "Language",
                ],
                "real_ip" => [
                    "label" => "Ip",
                ],
                "real_country" => [
                    "label" => "Country",
                ],
                "region" => [
                    "label" => "Region",
                ],
                "region_code" => [
                    "label" => "Region code",
                ],
                "city" => [
                    "label" => "City",
                ],
                "zip_code" => [
                    "label" => "Zip code",
                ],
                "connection_type" => [
                    "label" => "Connection Type",
                ],
                "latitude" => [
                    "label" => "Latitude",
                ],
                "longitude" => [
                    "label" => "Longitude",
                ],
                "isp" => [
                    "label" => "Isp",
                ],
            ],
            'items' => $redirect_info_logs
        ];

        $user_data = array_filter(
            (array)$data['user_data'],
            function ($key) use ($filter) {
                return (in_array($key, $filter));
            },
            ARRAY_FILTER_USE_KEY
        );

        CryptHelper::decrypt_lead_data_array($user_data);

        foreach (['first_name', 'last_name'] as $f) {
            if (!empty($user_data[$f])) {
                $user_data[$f] = ucfirst($user_data[$f]);
            }
        }

        $filter = ['redirect_url', 'real_ip', 'real_useragent', 'real_country', 'riskReason', 'blockReasons'];
        $_user_redirect_data = [];

        if (empty($data['user_data']['real_ip'] ?? '')) {
            $data['user_data']['real_country'] = '';
        }

        if (((int)$data['user_data']['match_with_broker'] ?? 0) == 1) {
            $_user_redirect_data = array_filter(
                (array)$data['user_data'],
                function ($val, $key) use ($filter) {
                    return (in_array($key, $filter) && !empty($val));
                },
                ARRAY_FILTER_USE_BOTH
            );
        }

        $file_names = ['redirect_url' => 'Redirect Url', 'real_ip' => 'Real IP', 'real_useragent' => 'Real UserAgent', 'real_country' => 'Real Country', 'riskReason' => 'Risk Reason', 'blockReasons' => 'Block Reasons'];
        $user_redirect_data = array_map(
            function ($k, $v) use ($file_names) {
                switch ($k) {
                    case 'riskReason': {
                            if (!is_string($v) && count($v ?? []) > 0) {
                                $s = '';
                                foreach ((array)$v as $vv) {
                                    $s .= (!empty($s) ? ', ' : '') . $vv['reason'];
                                }
                                $v = $s;
                            } else {
                                $v = 'None';
                            }
                            break;
                        }
                    case 'blockReasons': {
                            if (!is_string($v) && count($v ?? []) > 0) {
                                $s = '';
                                foreach ((array)$v as $vv) {
                                    $s .= (!empty($s) ? ', ' : '') . $vv;
                                }
                                $v = $s;
                            } else {
                                $v = 'None';
                            }
                            break;
                        }
                }
                return ['key' => $file_names[$k], 'value' => $v];
            },
            array_keys($_user_redirect_data),
            $_user_redirect_data
        );

        usort($user_redirect_data, function ($key1, $key2) {
            return ($key1['key'] > $key2['key']);
        });

        $str_to_replace = "*****";
        // $input_str = $user_data['phone'] ?? '';
        // $phone = ;

        $user_data['phone'] = $str_to_replace . substr($user_data['phone'] ?? '', 5);
        $user_data['endpoint'] = [
            '_id' => $data['user_data']['TrafficEndpoint'] ?? '',
            'name' => $endpoints[strtolower($data['user_data']['TrafficEndpoint'])] ?? ''
        ];
        if (!empty($user_data['log_post'])) {
            $log_post = json_decode($user_data['log_post'], true) ?? [];
            CryptHelper::decrypt_lead_data_array($log_post);
            $log_post = array_map(
                function ($k, $v) {
                    if ($k == 'first_name' || $k == 'last_name') {
                        $v = ucfirst($v ?? '');
                    }
                    return ['key' => $k, 'value' => $v];
                },
                array_keys($log_post),
                $log_post
            );
            $user_data['log_post'] = $log_post;
        }

        if (!Gate::allows('role:admin') && !Gate::allows('role:tech_support')) {
            $user_data['log_post'] = [];
        }

        if (!empty($user_data['log_php_input'])) {
            $log_php_input = json_decode($user_data['log_php_input'], true);
            $log_php_input = array_map(
                function ($k, $v) {
                    return ['key' => $k, 'value' => $v];
                },
                array_keys($log_php_input),
                $log_post
            );
            $user_data['log_php_input'] = $log_php_input;
        }

        $result['user_data'][] = $user_data;
        $result['user_data_redirect_logs'] = $user_data_redirect_logs;

        $result['user_redirect_data'] = $user_redirect_data;

        foreach ($data['user_log'] as $log) {

            // $cssClass = '';
            // if ($log['requestResponse']['success'] == false) {
            // 	$cssClass = 'errorSuccess';
            // }

            // $requestPopover = '';
            // if (isset($log['requestResponse']['request']) && (auth::is_current_user_admin() || permissionsManagement::is_current_user_role('tech_support'))) {
            // 	$requestPopover = '<div class=\'json\'>' . json_encode((array)$log['requestResponse']['request']) . '</div>';
            // 	$requestPopover = str_replace('"', '&quot;', $requestPopover);
            // }
            // $requestPopoverAttr = '';
            // if (!empty($requestPopover)) {
            // 	$requestPopoverAttr = ' title="Request" data-toggle="popover" data-placement="bottom" data-content="' . $requestPopover . '"';
            // }

            // if (isset($log['requestResponse']) && isset($log['requestResponse']['integration'])) {
            // 	echo '<div class="col-3 log-title">Integration: ' . $log['requestResponse']['integration']['name'] . '</div>';
            // } else {
            // 	echo '<div class="col-3 log-title"></div>';
            // }

            // if (isset($log['requestResponse']) && isset($log['requestResponse']['integration']) && isset($log['requestResponse']['integration']['partnerId']) && isset($broker[$log['requestResponse']['integration']['partnerId']])) {
            // 	$response = $log['requestResponse']['response'] ?? '';
            // 	if (!is_string($response)) {
            // 		$response = print_r($response, true);
            // 	}
            // 	$download_response = ' <a title="Download Response" href="data:text/html;charset=utf-8,' . str_replace('"', '%22', $response) . '" download="response.txt"><b>&#x2BAF;</b></a>';
            // 	echo '<div class="col-3 log-title">Broker <a href="/Brokers/' . $log['requestResponse']['integration']['partnerId'] . '" target="_blank" style="color: white;">' . $broker[$log['requestResponse']['integration']['partnerId']] . '</a></div>';
            // 	echo '<div class="col-4 log-title"' . $requestPopoverAttr . '>Broker ResponseCode ' . (isset($log['requestResponse']['responseCode']) ? $log['requestResponse']['responseCode'] : 'Unknown') . $render_executedSeconds($log['requestResponse']) . $download_response . '</div>';
            // } else {
            // 	echo '<div class="col-7 log-title"></div>';
            // }

            $logs = [];

            $responseData = null;

            $requestResponse = (array)($log['requestResponse'] ?? []);
            $response = ($requestResponse['response'] ?? null);
            $waterfall = $requestResponse['waterfall'] ?? [];
            $CRG = (array)($requestResponse['CRG'] ?? []);
            $leadMessage = (array)($requestResponse['lead'] ?? []);

            $log['broker'] = [
                '_id' => $requestResponse['integration']['partnerId'] ?? '',
                'name' => $broker[$requestResponse['integration']['partnerId'] ?? ''] ?? ''
            ];

            $integrationId = '';
            $integration = json_decode(json_encode($requestResponse['integration'] ?? []), true);
            if (isset($integration['_id']['oid'])) {
                $integrationId = (string)($integration['_id']['oid'] ?? null) ?? '';
            }

            $log['broker_integrations'] = [
                '_id' => $integrationId, //(isset($requestResponse['integration'])) ? (string)$requestResponse['integration']['_id'] ?? '' : '',
                'name' => (isset($requestResponse['integration'])) ? GeneralHelper::broker_integration_name((array)$requestResponse['integration']) : '' //$requestResponse['integration']['name'] ?? '' : ''
            ];

            $log['response_code'] = $requestResponse['responseCode'] ?? '';
            $log['time'] = $render_executedSeconds($log['requestResponse']);
            $log['resplonse_download_content'] = '';

            if (isset($log['requestResponse'])) {
                $response = $log['requestResponse']['response'] ?? '';
                if (empty($response) && isset($log['requestResponse']['responseError']) && !empty($log['requestResponse']['responseError'])) {
                    $response = 'Response is empty. ' . $log['requestResponse']['responseError'];
                } else if (is_string($response)) {
                    $is_html = strpos(strtolower($response), '<html') != false;
                    if ($is_html) {
                        $response = htmlspecialchars($response);
                    }
                    $log['resplonse_download_content'] = str_replace('"', '%22', $response);
                    if ($is_html) {
                        $response = '<blockquote><pre><code style="max-height:500px;display:block">' . $response . '</code></pre></blockquote>';
                    }
                } else if (!is_null($response)) {
                    $r = (array)$response;
                    CryptHelper::decrypt_lead_data_array($r);
                    $log['resplonse_download_content'] = str_replace('"', '%22', print_r($r, true));
                }
            }

            // if (!customUserAccess::is_allow('CRG')) {
            // if (!Gate::allows('role:admin')) {
            // if (!Gate::allows('custom[CRG]')) {
            // 	$CRG = [];
            // }
            // }

            if (!empty($response)) {
                if (is_string($response) && !empty($response)) {
                    $responseData = json_decode($response, true);
                    if (JSON_ERROR_NONE !== json_last_error()) {
                        $responseData = ['Response' => $response];
                        // echo 'error json decode';
                    }
                } else if (is_array($response)) {
                    $responseData = $response;
                } else if (is_object($response) && isset($response) && $response != null) {
                    $responseData = (array)$response;
                }
            } else {
                $responseData = ['Response' => 'Response is empty'];
            }

            if (isset($waterfall) && count($waterfall) > 0) {
                $data_value = '';
                if (isset($waterfall['brokerId'])) {
                    // $data_value .= '<div>BrokerId: ' . $waterfall['brokerId'] . '</div>';

                    $log['skip_broker'] = [
                        '_id' => $waterfall['brokerId'] ?? '',
                        'name' => $broker[$waterfall['brokerId']] ?? ''
                    ];

                    $log['skip_broker_integrations'] = [
                        '_id' => $waterfall['integrationId'] ?? '',
                        'name' => $broker_integrations[$waterfall['integrationId']] ?? ''
                    ];

                    // $data_value .= '<div>' .
                    // 	'<strong>Broker</strong>: <a style="color: black;" target="_blank" href="/Brokers/' . $waterfall['brokerId'] . '">' . $broker[$waterfall['brokerId']] . '</a>' .
                    // 	', <strong>Integration</strong>: ' . $broker_integrations[$waterfall['integrationId']] .
                    // 	'</div>';
                }
                // if (isset($waterfall['integrationId'])) {
                // $data_value .= '<div>IntegrationId: ' . $waterfall['integrationId'] . '</div>';
                // $data_value .= '<div>Integration: ' . $broker_integrations[$waterfall['integrationId']] . '</div>';
                // }
                if (isset($waterfall['message'])) {
                    // $data_value .= '<div><strong>Message</strong>: ' . implode("\n", (array)$waterfall['message']) . '</div>';
                    $data_value .= implode("\n", (array)$waterfall['message']);
                }

                $logs[] = [
                    'title' => 'WaterFall',
                    'value' => $data_value
                ];
            } elseif (isset($leadMessage) && count($leadMessage) > 0) {
                $data_value = [];
                foreach ($leadMessage as $message) {
                    $data_value[] = [
                        'name' => '',
                        'message' => $message
                    ];
                }

                $logs[] = [
                    'title' => 'Lead',
                    'value' => $data_value
                ];

                // echo '<div class="col-2 log-data-title">Waterfall</div>';
                // echo '<div class="col-10 log-data-value">' . $data_value . '</div>';
            } elseif (isset($CRG) && count($CRG) > 0) {
                $data_value = [];
                if (isset($CRG['message']) && isset($CRG['message']['broker'])) {
                    foreach ($CRG['message']['broker'] as $p) {
                        // $data_value .= '<div><strong>CRG Deal (broker)</strong> ' . $p['crg_deal_id'] . ': ' . $p['short_message'] . '</div>';
                        $data_value[] = [
                            'name' => 'CRG Deal (broker) ' . $p['crg_deal_id'] . ': ',
                            'message' => $p['short_message']
                        ];
                    }
                }
                if (isset($CRG['message']) && isset($CRG['message']['endpoint'])) {
                    foreach ($CRG['message']['endpoint'] as $p) {
                        // $data_value .= '<div><strong>CRG Deal (endpoint)</strong> ' . $p['crg_deal_id'] . ': ' . $p['short_message'] . '</div>';
                        $data_value[] = [
                            'name' => 'CRG Deal (endpoint) ' . $p['crg_deal_id'],
                            'message' => $p['short_message']
                        ];
                    }
                }
                // if (!empty($data_value)) {
                // 	echo '<div class="col-2 log-data-title">CRG</div>';
                // 	echo '<div class="col-10 log-data-value">' . $data_value . '</div>';
                // }

                if (Gate::allows('custom[CRG]')) {
                    $logs[] = [
                        'title' => 'CRG',
                        'value' => $data_value
                    ];
                }
            } else {

                //echo '<pre>'.print_r($log,true).'</pre>';
                $responseData = $responseData === null ? array() : $responseData;
                foreach ((array)$responseData as $headlineresponse => $datarepose) {
                    $data_value = $datarepose;
                    if (is_string($datarepose) && !empty($datarepose)) {
                        $data_value = $datarepose;
                    } else if (is_int($datarepose)) {
                        $data_value = $datarepose;
                    } else if (is_bool($datarepose)) {
                        $data_value = $datarepose;
                    } else if (is_array($datarepose)) {
                        $data_value = '<pre>' . print_r($datarepose, true) . '</pre>';
                    } else if (is_object($datarepose) && isset($datarepose) && $datarepose != null) {
                        $data_value = '<pre>' . print_r((array)$datarepose, true) . '</pre>';
                    }
                    // echo '<div class="col-2 log-data-title">' . (is_string($headlineresponse) && !empty($headlineresponse) ? $headlineresponse : '') . '</div>';
                    // echo '<div class="col-10 log-data-value">' . $data_value . '</div>';
                    $logs[] = [
                        'title' => (is_string($headlineresponse) && !empty($headlineresponse) ? $headlineresponse : ''),
                        'value' => $data_value
                    ];
                }
            }

            if (count($logs) > 0) {
                $log['logs'] = $logs;
                $result['user_log'][] = $log;
            }
        }

        return $result;
    }
}
