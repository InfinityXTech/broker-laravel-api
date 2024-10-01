<?php

namespace App\Classes\Brokers;

use Exception;
use App\Models\User;
use App\Models\Broker;
use App\Classes\DailyCaps;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Classes\PartnerPayouts;
use App\Classes\BlockingSchedule;
use App\Classes\FormattedSchedule;
use App\Models\Brokers\BrokerCaps;
use App\Classes\History\HistoryDiff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\Brokers\BrokerCapsLog;
use App\Models\TrafficEndpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class ManageCaps
{
    private $cached_integrations = null;
    private $cached_brokers = null;

    private $endpoint_names_cached = null;

    private $integration_statuses = [
        0 => 'inactive',
        1 => 'active',
        2 => 'draft'
    ];

    private $priorities = [
        0 => 'general',
        1 => 'Low',
        2 => 'Medium',
        3 => 'High',
    ];

    private function get_feed_config($id)
    {
        $clientConfig = ClientHelper::clientConfig();
        $url = $clientConfig['serving_domain'] . config('remote.feed_details_url_path');

        if ($id == 'all') {
            $_traffic_endpoints = TrafficEndpoint::query()->whereIn('status', ['1', 1])->get(['_id']);
            $traffic_endpoints = $_traffic_endpoints ? $_traffic_endpoints->toArray() : [];
            $ids = implode(',', array_column($traffic_endpoints, '_id'));
            $post = ['ids' => $ids];
        } else {
            $post = ['id' => $id];
        }

        $headers = [];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 400);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $server_output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $httperror = '';
        if (curl_errno($ch)) $httperror = curl_error($ch);

        if (!empty($httperror)) {
            throw new \Exception($httperror . ', Code:' . $httpcode);
        }

        curl_close($ch);

        $data = json_decode($server_output, true);
        if ($data === null) {
            throw new \Exception('Invalid json data');
        }

        return $data;
    }

    public function create(array $payload): ?Model
    {
        $exists = BrokerCaps::where([
            'broker' => $payload['broker'],
            'country_code' => $payload['country_code'],
            'language_code' => ['$in' => $payload['language_code']],
            'integration' => $payload['integration'],
            'cap_type' => $payload['cap_type'],
            // 'period_type' => $payload['period_type'] ?? 'D'
        ])
            ->first();

        if ($exists) {
            throw new \Exception("Daily cap with same parameters already exists.\nPlease change Country, Language, Integration or Cap Type and try again.");
        }
        if (!$this->validate_endpoint_dailycaps_unique($payload['endpoint_dailycaps'] ?? [])) {
            throw new \Exception("There are duplicates in cap allocations for traffic endpoints.");
        }
        if (!$this->validate_endpoint_dailycaps_values($payload['endpoint_dailycaps'] ?? [], $payload)) {
            throw new \Exception("Sum of allocated caps exceeds total daily cap value");
        }

        return BrokerCaps::create($payload);
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = BrokerCaps::query()->find($modelId);

        $exists = BrokerCaps::where([
            'broker' => $model->broker,
            'country_code' => $model->country_code,
            'language_code' => ['$in' => $payload['language_code']],
            'integration' => $model->integration,
            'cap_type' => $model->cap_type,
            // 'period_type' => $model->period_type ?? 'D'
        ])
            ->where('_id', '<>', $modelId)
            ->first();

        if ($exists) {
            throw new \Exception("Daily cap with same parameters already exists.\nPlease change Language and try again.");
        }
        if (isset($payload['endpoint_dailycaps']) && !$this->validate_endpoint_dailycaps_unique($payload['endpoint_dailycaps'])) {
            throw new \Exception("There are duplicates in cap allocations for traffic endpoints.");
        }
        if (isset($payload['endpoint_dailycaps']) && !$this->validate_endpoint_dailycaps_values($payload['endpoint_dailycaps'], $payload)) {
            throw new \Exception("Sum of allocated caps exceeds total daily cap value");
        }

        if ($model->daily_cap != $payload['daily_cap']) {
            BrokerCapsLog::create([
                'broker_cap' => $model->id,
                'daily_cap' => $payload['daily_cap'],
            ]);
        }
        return $model->update($payload);
    }

    private function calculate_live_cap(array $match_rule): array
    {
        $mongo = new MongoDBObjects('leads', []);
        $list = $mongo->aggregate(['pipeline' => [
            [
                '$match' => $match_rule
            ],
            [
                '$project' => [
                    // 'brokerId' => 1,
                    // 'match_with_broker' => 1,
                    // 'test_lead' => 1,
                    'depositor' => 1,
                    'integrationId' => 1,
                    'country' => 1,
                    'language' => 1
                ]
            ],
            [
                '$addFields' => [
                    'leads' => 1,
                    'depositors' => ['$toInt' => '$depositor']
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'integrationId' => '$integrationId',
                        'country' => ['$toLower' => '$country'],
                        'language' => ['$toLower' => '$language'],
                        // 'endpointId' => '$TrafficEndpoint'
                    ],
                    'leads' => ['$sum' => '$leads'],
                    'ftds' => ['$sum' => '$depositors']
                ]
            ],
        ]], false, false);

        $result = [];
        foreach ($list as $group) {
            $integrationId = $group['_id']['integrationId'];
            $country = $group['_id']['country'];
            $language = $group['_id']['language'];
            // $endpointId = $group['_id']['endpointId'];
            // $result[$integrationId][$country][$language][$endpointId]['leads'] += $group['leads'];
            // $result[$integrationId][$country][$language][$endpointId]['ftds'] += $group['ftds'];

            $result[$integrationId][$country][$language]['leads'] ??= 0;
            $result[$integrationId][$country][$language]['ftds'] ??= 0;

            $result[$integrationId][$country][$language]['leads'] += $group['leads'];
            $result[$integrationId][$country][$language]['ftds'] += $group['ftds'];
        }
        return $result;
    }

    private function calculate_leads_live_cap(array $brokerIds = [], array $integrationIds = []): array
    {
        $where = [
            'clientId' => ClientHelper::clientId(),
            // 'brokerId' => ['$exists' => true],
            // 'integrationId' => ['$exists' => true],
            'match_with_broker' => 1,
            'test_lead' => 0,
            // 'Timestamp' => ['$gte' => $start_time],
        ];

        if (!empty($brokerIds)) {
            $where['brokerId'] = ['$in' => array_merge($brokerIds[0] ?? [], $brokerIds[1] ?? [])];
        }

        // get disabled
        $disabled_cache_key = 'calculate_leads_live_cap_disabled' . '_' . ClientHelper::clientId() . '_' . md5(serialize($where));
        $disabled_cached = Cache::get($disabled_cache_key, null) ?? [];
        $integrationIds[0] ??= [];
        $integrationIds[1] ??= [];
        // GeneralHelper::PrintR($disabled_cached);die();
        foreach (array_keys($disabled_cached) as $integrationId) {
            if (in_array($integrationId, $integrationIds[1])) {
                $disabled_cached = [];
                break;
            }
            // if (!in_array($integrationId, $integrationIds[0])) {
            //     $disabled_cached = [];
            //     break;
            // }
            $i = array_search($integrationId, $integrationIds[0]);
            if ($i >= 0) {
                // echo $integrationId;
                // GeneralHelper::PrintR($integrationIds);
                array_splice($integrationIds[0], $i, 1);
                // GeneralHelper::PrintR($integrationIds);
                // die();
            }
        }

        if (!empty($integrationIds)) {
            $p = array_merge($integrationIds[1] ?? [], (empty($disabled_cached) ? $integrationIds[0] ?? [] : []));
            // $p = array_merge($integrationIds[0] ?? [], $integrationIds[1] ?? []);
            if (!empty($p)) {
                $where['integrationId'] = ['$in' => $p];
            }
        }

        $cache_key = 'calculate_leads_live_cap_' . ClientHelper::clientId() . '_' . md5(serialize($where));
        $data = Cache::get($cache_key, null);
        if (!isset($data)) {
            $data = $this->calculate_live_cap($where) ?? [];
            Cache::put($cache_key, $data, 60 * 10);
        }

        if (empty($disabled_cached)) {
            foreach ($integrationIds[0] ?? [] as $integrationId) {
                if (isset($data[$integrationId])) {
                    $disabled_cached[$integrationId] = $data[$integrationId];
                }
            }
            // GeneralHelper::PrintR($disabled_cached);die();
            if (!empty($disabled_cached)) {
                Cache::put($disabled_cache_key, $disabled_cached, 60 * 60 * 24);
            }
        } else {
            foreach ($disabled_cached as $integrationId => $d) {
                $data[$integrationId] = $d;
            }
        }

        return $data;
    }

    private function calculate_ftd_live_cap(array $brokerIds = [], array $integrationIds = []): array
    {
        $where = [
            'clientId' => ClientHelper::clientId(),
            // 'brokerId' => ['$exists' => true],
            // 'integrationId' => ['$exists' => true],
            'match_with_broker' => 1,
            'test_lead' => 0,
            'depositor' => true,
            // 'depositTimestamp' => ['$gte' => $start_time],
            /*'$or' => [
                ['Timestamp' => ['$gte' => $start_time]],
                ['depositTimestamp' => ['$gte' => $start_time]],
            ]*/
        ];

        if (!empty($brokerIds)) {
            $where['brokerId'] = ['$in' => array_merge($brokerIds[0] ?? [], $brokerIds[1] ?? [])];
        }

        // get disabled
        $disabled_cache_key = 'calculate_ftd_live_cap_disabled_' . ClientHelper::clientId() . '_' .  md5(serialize($where));
        $disabled_cached = Cache::get($disabled_cache_key, null) ?? [];
        $integrationIds[0] ??= [];
        $integrationIds[1] ??= [];
        // GeneralHelper::PrintR($disabled_cached);die();
        foreach (array_keys($disabled_cached) as $integrationId) {
            if (in_array($integrationId, $integrationIds[1])) {
                $disabled_cached = [];
                break;
            }
            // if (!in_array($integrationId, $integrationIds[0])) {
            //     $disabled_cached = [];
            //     break;
            // }
            $i = array_search($integrationId, $integrationIds[0]);
            if ($i >= 0) {
                // echo $integrationId;
                // GeneralHelper::PrintR($integrationIds);
                array_splice($integrationIds[0], $i, 1);
                // GeneralHelper::PrintR($integrationIds);
                // die();
            }
        }

        if (!empty($integrationIds)) {
            $p = array_merge($integrationIds[1] ?? [], (empty($disabled_cached) ? $integrationIds[0] ?? [] : []));
            // $p = array_merge($integrationIds[0] ?? [], $integrationIds[1] ?? []);
            if (!empty($p)) {
                $where['integrationId'] = ['$in' => $p];
            }
        }
        // if (!empty($integrationIds)) {
        //     $where['integrationId'] = ['$in' => $integrationIds];
        // }

        $cache_key = 'calculate_ftd_live_cap_' . ClientHelper::clientId() . '_' . md5(serialize($where));
        $data = Cache::get($cache_key, null);
        if (!isset($data)) {
            $data = $this->calculate_live_cap($where);
            Cache::put($cache_key, $data, 60 * 10);
        }

        if (empty($disabled_cached)) {
            foreach ($integrationIds[0] ?? [] as $integrationId) {
                if (isset($data[$integrationId])) {
                    $disabled_cached[$integrationId] = $data[$integrationId];
                }
            }
            // GeneralHelper::PrintR($disabled_cached);die();
            if (!empty($disabled_cached)) {
                Cache::put($disabled_cache_key, $disabled_cached, 60 * 60 * 24);
            }
        } else {
            foreach ($disabled_cached as $integrationId => $d) {
                $data[$integrationId] = $d;
            }
        }

        return $data;
    }

    private function live_stat_caps(array &$live_caps, array &$cap): array
    {
        $result = [];

        $integrationId = $cap['integration'];
        $country = $cap['country_code'];
        $languages = $cap['language_code'] ?? [];
        // $endpoint_dailycaps = (array)($cap['endpoint_dailycaps'] ?? []);

        // $endpoint_livecaps = [];
        $total_live_caps = 0;

        // $endpoint_ftd_livecaps = [];
        $total_ftd_live_caps = 0;

        // if (!empty($endpoint_dailycaps)) {
        //     foreach ($endpoint_dailycaps['endpoint'] as $endpointId) {
        //         $endpoint_livecap = 0;
        //         $endpoint_ftd_livecap = 0;
        //         foreach ($languages as $language) {
        //             $endpoint_livecap += $live_caps[$integrationId][$country][$language][$endpointId]['leads'] ?? 0;
        //             $endpoint_ftd_livecap += $live_caps[$integrationId][$country][$language][$endpointId]['ftds'] ?? 0;
        //         }
        //         $endpoint_livecaps[] = $endpoint_livecap;
        //         $endpoint_ftd_livecaps[] = $endpoint_ftd_livecap;
        //     }
        // }

        foreach ($languages as $language) {
            $total_live_caps += array_sum((array)($live_caps[$integrationId][$country][$language]['leads'] ?? []));
            $total_ftd_live_caps += array_sum((array)($live_caps[$integrationId][$country][$language]['ftds'] ?? []));
        }

        $result = [
            // 'total_endpoint_livecaps' => $endpoint_livecaps,
            'total_live_caps' => $total_live_caps,
            // 'total_ftd_endpoint_livecaps' => $endpoint_livecaps,
            'total_ftd_live_caps' => $total_ftd_live_caps,
            'total_cr_live_caps' => round($total_live_caps > 0 ? (($total_ftd_live_caps / $total_live_caps) * 100) : 0, 2)
        ];

        return $result;
    }

    public function feed_caps($filter = []): array
    {

        $brokerId = $filter['broker_id'] ?? '';

        $broker_names = array_map(function ($partner) {
            $result = [
                '_id' => (string)$partner['_id'],
                'name' => GeneralHelper::broker_name($partner),
            ];
            if (isset($partner['financial_status']) && $partner['financial_status'] == 'hold') {
                $result['error'] = 'Broker financial status is hold';
            }
            if (isset($partner['status']) && $partner['status'] != '1') {
                $result['error'] = 'Broker status is not active';
            }
            return $result;
        }, $this->get_brokers($brokerId));

        $integrations = $this->get_integrations($brokerId);
        $endpoint_names = $this->get_endpoint_names(true);
        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();
        $payouts = PartnerPayouts::fromBrokers($brokerId);
        $crgs = PartnerPayouts::fromBrokersCRG($brokerId)->getAllPayouts();
        $payouts_with_crg = PartnerPayouts::fromBrokersWithCRG(true, $brokerId)->getAllPayouts();

        $where = [];
        if ($filter['broker_id'] ?? false) {
            $where['broker'] = $filter['broker_id'];
        }
        if ($filter['integration_id'] ?? false) {
            $where['integration'] = $filter['integration_id'];
        }
        if ($filter['country_code'] ?? false) {
            $where['country_code'] = $filter['country_code'];
        }
        if ($filter['language_code'] ?? false) {
            $where['language_code'] = $filter['language_code'];
        }
        if ($filter['cap_type'] ?? false) {
            $where['cap_type'] = $filter['cap_type'];
        }
        if (isset($filter['enable_traffic'])) {
            $where['enable_traffic'] = (bool)$filter['enable_traffic'];
        }

        $is_only_assigned = Gate::allows('brokers[is_only_assigned=1]');

        if ($is_only_assigned) {
            $brokerIds = [];
            $current_user_id = Auth::id();
            $allow_brokers = Broker::query()->where('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id']);
            foreach ($allow_brokers as $broker) {
                $brokerIds[] = $broker->_id;
            }
            if (count($brokerIds) > 0) {
                if (isset($where['broker'])) {
                    $where['$and'] = [
                        ['broker' => $where['broker']],
                        ['broker' => ['$in' => $brokerIds]]
                    ];
                } else {
                    $where['broker'] = ['$in' => $brokerIds];
                }
            } else {
                $where['broker'] = 'no_one';
            }
            // GeneralHelper::PrintR($where);die();
        }

        $mongo = new MongoDBObjects('broker_caps', $where);
        $data = $mongo->findMany();

        $response = [];

        $live_caps = [];
        // foreach ($data as $datas) {
        //     if ($datas['cap_type'] == 'leads') {
        //         $live_caps[$datas['cap_type']] = $this->calculate_leads_live_cap($brokerId);
        //     } else if ($datas['cap_type'] == 'ftd') {
        //         $live_caps[$datas['cap_type']] = $this->calculate_ftd_live_cap($brokerId);
        //     }
        // }

        $integrationIds = [];
        $brokerIds = [];
        foreach ($data as $cap) {
            $enabled = (int)($cap['enable_traffic'] ?? false);
            $integrationIds[$enabled] ??= [];
            $brokerIds[$enabled] ??= [];
            if (!empty($cap['integration'] ?? '') && !in_array($cap['integration'], $integrationIds)) {
                $integrationIds[$enabled][] = $cap['integration'];
            }
            if (!empty($cap['broker'] ?? '') && !in_array($cap['broker'], $integrationIds)) {
                $brokerIds[$enabled][] = $cap['broker'];
            }
        }

        // $integrationIds = array_reduce($data, function (?array $carry, array $cap) {
        //     $carry ??= [];
        //     if (!empty($cap['integration'] ?? '') && !in_array($cap['integration'], $carry)) {
        //         $carry[] = $cap['integration'];
        //     }
        //     return $carry;
        // }) ?? [];

        // $brokerIds = array_reduce($data, function (?array $carry, array $cap) {
        //     $carry ??= [];
        //     if (!empty($cap['broker'] ?? '') && !in_array($cap['broker'], $carry)) {
        //         $carry[] = $cap['broker'];
        //     }
        //     return $carry;
        // }) ?? [];

        // $integrationIds = [];
        // $brokerIds = [];
        // if (!empty($brokerId)) {
        //     $brokerIds[] = $brokerId;
        // }

        foreach ($data as $datas) {
            $item = [];

            $item['_id'] = (string)$datas['_id'];

            $cap_type = $datas['cap_type'] ?? 'leads';
            if (!isset($live_caps[$cap_type])) {
                if ($cap_type == 'leads') {
                    $live_caps[$cap_type] = $this->calculate_leads_live_cap($brokerIds, $integrationIds);
                } else if ($cap_type == 'ftd') {
                    $live_caps[$cap_type] = $this->calculate_ftd_live_cap($brokerIds, $integrationIds);
                }
            }

            $item['stat'] = $this->live_stat_caps($live_caps[$datas['cap_type']], $datas);

            $item['broker'] = $broker_names[$datas['broker']] ?? ['_id' => $datas['broker'], 'name' => $datas['broker'], 'error' => 'Broker not exists'];

            $item['country'] = [
                'code' => $datas['country_code'],
                'name' => $countries[$datas['country_code']],
            ];

            $item['languages'] = array_map(function ($language) use ($payouts, $datas, $languages) {
                $result = ['code' => $language, 'name' => ($languages[$language] ?? '') . ' (' . strtoupper($language) . ')'];
                $error = $payouts->getPayoutError($datas['broker'], $datas['country_code'], $language);
                if ($error !== false) {
                    $result['error'] = $error . ': ' . $datas['country_code'] . '_' . $language;
                }
                return $result;
            }, (array)$datas['language_code']);

            $integration_status = $integrations[$datas['integration']]['status'] ?? 0;
            $item['integration'] = [
                '_id' => $datas['integration'],
                'name' => $integrations[$datas['integration']]['name'],
                'error' => ($integration_status != 1) ? 'Integration status is ' . $this->integration_statuses[$integration_status] : null,
            ];

            $item['cap_type'] = $datas['cap_type'];
            $item['priority'] = (empty($datas['priority'] ?? '') ? 'general' : $datas['priority']);
            $item['daily_cap'] = (int)$datas['daily_cap'];
            $item['live_caps'] = (int)($datas['live_caps'] ?? 0);
            $item['caps_alloc'] = array_map(function ($item) use ($endpoint_names) {
                if (!empty($item['endpointId'] ?? '') && !isset($endpoint_names[$item['endpointId']])) {
                    $endpoint_names = $this->get_endpoint_names(false);
                }
                if (isset($item['endpointId'])) {
                    $item['name'] = $endpoint_names[$item['endpointId'] ?? ''] ?? '';
                }
                return $item;
            }, DailyCaps::get_all_allocations($datas));

            $item['enable_traffic'] = $datas['enable_traffic'];
            $item['note'] = $datas['note'] ?? '';

            $item['weekends'] = BlockingSchedule::workOnWeekends($datas['blocked_schedule'] ?? []);
            if (isset($filter['weekends']) && ($item['weekends'] != (bool)$filter['weekends'])) {
                continue;
            }

            $item['payouts_crg'] = $payouts_with_crg[$datas['broker'] ?? ''] ?? [];


            // $item['_payouts'] = $payouts->getAllPayouts()[$datas['broker'] ?? ''];
            // $item['_blocked_schedule'] = $datas['blocked_schedule'] ?? [];

            $item['crg_week'] = [];
            $item['crg_weekends'] = [];
            $item['crg_blocked_schedule'] = [];

            $_languages = (array)$datas['language_code'] ?? [];
            $crg_exists = [];
            foreach ($crgs[$datas['broker']] ?? [] as $locations => $_crgs) {
                foreach ($_crgs as $crg) {
                    $restrict_traffic_endpoints = array_reduce((array)$crg['endpoint'] ?? [], function (?array $carry, string $traffic_endpoint_id) use ($endpoint_names) {
                        $carry ??= [];
                        $carry[] = $endpoint_names[$traffic_endpoint_id];
                        return array_unique($carry);
                    }) ?? [];

                    $parts = explode('_', $locations);
                    $_country =  $parts[0];
                    $_language = count($parts) > 1 ? $parts[1] : '';
                    if (
                        ((int)$crg['status'] ?? 0) == 1 &&
                        (((int)$crg['type'] ?? 0) == 2 || ((int)$crg['type'] ?? 0) == 3) &&
                        $_country == $datas['country_code'] &&
                        (empty($_language) || in_array($_language, $_languages))
                    ) {

                        if (in_array((string)$crg['_id'], $crg_exists)) {
                            continue;
                        }

                        $crg_exists[] = (string)$crg['_id'];

                        $blocked_schedule = (array)($crg['blocked_schedule'] ?? []);

                        if (count($blocked_schedule) > 0) {

                            $timezone = $blocked_schedule['timezone'] ?? '';

                            if (isset($blocked_schedule['timezone'])) {
                                unset($blocked_schedule['timezone']);
                            }

                            $item['crg_blocked_schedule'][] = $blocked_schedule;

                            $crg_week = new FormattedSchedule($blocked_schedule, false);
                            $crg_week_str = $crg_week->getData();
                            if (!empty($crg_week_str)) {
                                $item['crg_week'][] = ['traffic_endpoints' => $restrict_traffic_endpoints, 'schedule' => $crg_week_str, 'timezone' => $timezone];
                            }

                            $crg_weekends = new FormattedSchedule($blocked_schedule, true);
                            $crg_weekends_str = $crg_weekends->getData();
                            if (!empty($crg_weekends_str)) {
                                $item['crg_weekends'][] = ['traffic_endpoints' => $restrict_traffic_endpoints, 'schedule' => $crg_weekends_str, 'timezone' => $timezone];
                            }
                        } else {
                            $item['crg_week'][] = ['traffic_endpoints' => $restrict_traffic_endpoints, 'schedule' => '24/5', 'timezone' => ''];
                            $item['crg_weekends'][] = ['traffic_endpoints' => $restrict_traffic_endpoints, 'schedule' => '24/2', 'timezone' => ''];
                        }
                    }
                }
            }

            $response[] = $item;
        }

        return $response;
    }

    public function available_endpoints(string $id): array
    {
        $result = [];
        if (!empty($id)) {
            $where = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            $mongo = new MongoDBObjects('broker_caps', $where);
            $data = $mongo->find();

            $autoCampaignDatas = $this->get_feed_config('all');
            $endpoint_names = $this->get_endpoint_names();

            foreach ($autoCampaignDatas as $traffic_endpoint_id => $caps) {
                foreach ($caps as $geo => $waterfalls) {
                    $country = substr($geo, 0, 2);
                    $language = substr($geo, 3, 2);

                    $cap_language = ((array)$data['language_code'] ?? []);

                    // if (
                    //     // ($waterfall['cap_id'] ?? '') == $id &&
                    //     $traffic_endpoint_id == '63d0efe270838a563e2f2d23'
                    // ) {
                    //     GeneralHelper::PrintR([
                    //         'a' => $country == $data['country_code'],
                    //         '$country' => $country,
                    //         'country_code' => $data['country_code'],
                    //         '$language' => $language,
                    //         '$cap_language' => json_encode($cap_language)
                    //     ]);
                    // }

                    if (
                        $country == $data['country_code'] &&
                        (in_array($language, $cap_language) || count($cap_language) == 0)
                    ) {

                        $waterfalls = $waterfalls['waterfalls'] ?? [];
                        $waterfalls = array_merge(['general' => $waterfalls['general'] ?? []], $waterfalls['group_by'] ?? []);

                        foreach ($waterfalls as $group => $gwaterfalls) {
                            // if (isset($waterfalls['general'])) {
                            //     $waterfalls = $waterfalls['general'];
                            // }
                            foreach ($gwaterfalls as $integration_id => $_waterfalls) {
                                foreach ($_waterfalls as $waterfall) {
                                    $title = ($endpoint_names[$traffic_endpoint_id] ?? '') . ($group != 'general' ? ' (' . str_replace('|||', ' | ', $group) . ')' : '');
                                    if (
                                        (int)($waterfall['skipped'] ?? 0) == 0 &&
                                        ($waterfall['cap_id'] ?? '') == $id &&
                                        !in_array($title, array_column($result, 'title'))
                                    ) {
                                        $result[] = [
                                            '_id' => $traffic_endpoint_id,
                                            'title' => $title
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function logs($id, $limit = 20)
    {
        $where = [
            'primary_key' => new \MongoDB\BSON\ObjectId($id),
            'collection' => 'broker_caps'
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
            $diff->init($history, $history_logs[$i + 1] ?? null);

            $response[] = [
                '_id' => (string)$history['_id'],
                'details' => json_decode(json_encode($history ?? []), true),
                'timestamp' => $history['timestamp'],
                'action' => $history['action'],
                'changed_by' => User::query()->find((string)$history['action_by'])->name,
                'data' => implode(', ', array_filter([
                    $diff->array('language_code', 'Languages'),
                    $diff->value('daily_cap', 'Daily Cap'),
                    $diff->value('priority', 'Priority'),
                    $diff->value('enable_traffic', 'Traffic Enabled'),
                    $diff->value('note', 'Note'),
                    $diff->value('restrict_type', 'Endpoint Restriction Type'),
                    $diff->endpoints('restrict_endpoints', 'Endpoint Restrictions'),
                    $diff->value('blocked_funnels_type', 'Funnel Restriction Type'),
                    $diff->array('blocked_funnels', 'Funnel Restrictions'),

                    $diff->endpoint_dailycaps('endpoint_dailycaps', 'Endpoint Caps'),
                    $diff->endpoint_dailycaps_priorities('endpoint_priorities', 'Endpoint Priorities'),

                    $diff->blocked_schedule('blocked_schedule', 'Schedule'),
                ]))
            ];
        }
        return $response;
    }

    public function download_cap_countries($brokerId = '')
    {
        $brokers = $this->get_brokers($brokerId);
        $integrations = $this->get_integrations();
        $countries = GeneralHelper::countries();
        $payouts = PartnerPayouts::fromBrokers();

        $where = [
            'enable_traffic' => true,
        ];
        if (!empty($brokerId)) {
            $where['broker'] = $brokerId;
        }
        $mongo = new MongoDBObjects('broker_caps', $where);
        $data = $mongo->findMany();

        $result = [];

        foreach ($data as $datas) {
            $broker = $brokers[$datas['broker']];
            if ($broker['status'] != '1') {
                continue;
            }
            if (($broker['financial_status'] ?? '') == 'hold') {
                continue;
            }

            $integration = $integrations[$datas['integration']];
            if ($integration['status'] != '1') {
                continue;
            }

            $language_error = false;
            foreach ($datas['language_code'] as $language) {
                $error = $payouts->getPayoutError($datas['broker'], $datas['country_code'], $language);
                if ($error !== false) {
                    $language_error = true;
                    break;
                }
            }
            if ($language_error) {
                continue;
            }

            if (!isset($result[$datas['country_code']])) {
                $result[$datas['country_code']] = [
                    'country' => $countries[$datas['country_code']],
                    'languages' => [],
                ];
            }
            foreach ($datas['language_code'] as $language) {
                $result[$datas['country_code']]['languages'][$language] = true;
            }
        }

        $html = "Code,Country,Languages\n";
        foreach ($result as $code => $data) {
            $html .= $code . ',' . $data['country'] . ',' . implode(' ', array_keys($data['languages'])) . "\n";
        }
        return $html;
    }

    private function validate_endpoint_dailycaps_unique($data)
    {
        return empty($data) or count($data['endpoint']) == count(array_unique($data['endpoint']));
    }

    private function validate_endpoint_dailycaps_values($data, $payload)
    {
        return empty($data) or array_sum($data['daily_cap']) <= $payload['daily_cap'];
    }

    private function get_brokers($broker_id = '')
    {
        if ($this->cached_brokers == null) {
            $where = []; //'partner_type' => '1'];
            if (!empty($broker_id)) {
                $where['_id'] = new \MongoDB\BSON\ObjectId($broker_id);
            }
            $mongo = new MongoDBObjects('partner', $where);
            $partners = $mongo->findMany(['projection' => ['_id' => 1, 'partner_name' => 1, 'token' => 1, 'status' => 1, 'created_by' => 1, 'account_manager' => 1, 'financial_status' => 1]]);
            $result = [];
            foreach ($partners as $partner) {
                $id = (array)$partner['_id'];
                $id = $id['oid'];
                $result[$id] = $partner;
            }
            $this->cached_brokers = $result;
        }
        return $this->cached_brokers;
    }

    private function get_integrations($broker_id = '')
    {
        if ($this->cached_integrations == null) {
            $where = [];
            if (!empty($broker_id)) {
                $where['partnerId'] = $broker_id;
            }
            $mongo = new MongoDBObjects('broker_integrations', $where);
            $integrations = $mongo->findMany(['projection' => ['_id' => 1, 'status' => 1, 'name' => 1, 'partnerId' => 1]]);
            $result = [];
            foreach ($integrations as $integration) {
                $id = (array)$integration['_id'];
                $id = $id['oid'];
                $integration['name'] = GeneralHelper::broker_integration_name($integration);
                $result[$id] = $integration;
            }
            $this->cached_integrations = $result;
        }
        return $this->cached_integrations;
    }

    private function get_endpoint_names(bool $cache = true)
    {
        if ($cache == true) {
            $traffic_endpoint_cache_key = 'TrafficEndpoint_id_token_only_token_' . ClientHelper::clientId();
            $traffic_endpoints = Cache::get($traffic_endpoint_cache_key, null);
            if (is_array($traffic_endpoints)) {
                return $traffic_endpoints;
            }
        }

        if ($this->endpoint_names_cached != null) {
            return $this->endpoint_names_cached;
        }

        $where = [];
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1]]);
        $result = [];
        foreach ($partners as $partner) {
            $result[MongoDBObjects::get_id($partner)] = $partner['token'] ?? '';
        }

        if ($cache == true) {
            Cache::put($traffic_endpoint_cache_key, $traffic_endpoints, 60 * 60);
        }

        $this->endpoint_names_cached = $result;
        return $result;
    }
}
