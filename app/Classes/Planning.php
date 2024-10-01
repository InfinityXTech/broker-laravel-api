<?php

namespace App\Classes;

use App\Helpers\ClientHelper;
use App\Models\Broker;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Models\Brokers\BrokerCrg;
use App\Models\Brokers\BrokerCaps;
use App\Models\Brokers\BrokerPayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use App\Models\Brokers\BrokerIntegration;
use App\Models\TrafficEndpoints\TrafficEndpointPayout;

class Planning
{
    public static $regulated = [
        'all' => 'All',
        '0' => 'Non-Regulated',
        '1' => 'Regulated'
    ];

    public static $crg_types = [
        1 => 'Payout Deal',
        2 => 'CRG Deal',
        3 => 'Payout + CRG Deal'
    ];

    public static $traffic_sources = [
        'push_traffic' => 'Push Traffic',
        'domain_redirect' => 'Domain Redirect',
        'rtb' => 'RTB',
        'pop' => 'POP',
        'native' => 'NATIVE',
        'google' => 'GOOGLE',
        'facebook' => 'FACEBOOK',
        'ib' => 'IB',
        'seo' => 'SEO',
        'email_marketing' => 'Email Marketing',
        'affiliates' => 'Affiliates',
        'data' => 'Data'
    ];

    private function get_endpoint_names()
    {
        $partners = TrafficEndpoint::all(['token'])->toArray();
        $result = [];
        foreach ($partners as $partner) {
            $result[$partner['_id']] = ($partner['token'] ?? '');
        }
        return $result;
    }

    public function get_countries_and_languages(array $payload)
    {
        $result = ['countries' => [], 'languages' => []];

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();

        $country_codes = [];
        $language_codes = [];

        $where = [];

        // TODO: add later
        // $permissions = permissionsManagement::get_user_permissions('brokers');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('planning[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        }

        // brokers
        $brokers = Broker::all(['_id', 'partner_name', 'token', 'created_by', 'account_manager'])->toArray();
        // $mongo = new MongoDBObjects('partner', $where);
        // $brokers = $mongo->findMany(['projection' => ['_id' => 1]]);
        $in = [];
        foreach ($brokers as $broker) {
            $in[] = $broker['_id'];
        }

        // broker_payouts
        // $where = ['broker' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_payouts', $where);
        // $broker_payouts = $mongo->findMany(['projection' => ['enabled' => 1, 'country_code' => 1, 'language_code' => 1]]);

        // $result = DB::collection('changes')->raw(function($collection)

        $broker_payouts = BrokerPayout::all(['broker', 'enabled', 'country_code', 'language_code'])->whereIn('broker', $in)->toArray();
        foreach ($broker_payouts as $broker_payout) {
            if ((bool)($broker_payout['enabled'] ?? false)) {
                if (isset($broker_payout['country_code'])) {
                    $country_codes[$broker_payout['country_code']] = ['broker_payout' => true];
                }
                if (isset($broker_payout['language_code'])) {
                    $language_codes[$broker_payout['language_code']] = ['broker_payout' => true];
                }
            }
        }

        // broker_caps
        // $where = ['broker' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_caps', $where);
        // $broker_caps = $mongo->findMany(['projection' => ['enable_traffic' => 1, 'country_code' => 1, 'language_code' => 1]]);
        $broker_caps = BrokerCaps::all(['broker', 'enable_traffic', 'country_code', 'language_code'])->whereIn('broker', $in)->toArray();
        foreach ($broker_caps as $broker_cap) {
            if ((bool)$broker_cap['enable_traffic']) {
                $country_code = $broker_cap['country_code'] ?? '';
                if (!isset($country_codes[$country_code])) {
                    $country_codes[$country_code] = [];
                }
                $country_codes[$country_code]['broker_cap'] = true;
                $language_code_array = ($broker_cap['language_code'] ?? []);
                foreach ($language_code_array as $language_code) {
                    if (!isset($language_codes[$language_code])) {
                        $language_codes[$language_code] = [];
                    }
                    $language_codes[$language_code]['broker_cap'] = true;
                }
            }
        }

        // unique and map
        foreach ($country_codes as $country_code => $params) {
            if (
                (empty($country_code)) ||
                (($params['broker_payout'] ?? false) && ($params['broker_cap'] ?? false) && !empty($country_code))
            ) {
                $result['countries'][$country_code] = $countries[strtolower($country_code)];
            }
        }

        foreach ($language_codes as $language_code => $params) {
            if (
                (empty($language_code)) ||
                (($params['broker_payout'] ?? false) && ($params['broker_cap'] ?? false) && !empty($language_code))
            ) {
                if (!empty($language_code)) {
                    $result['languages'][$language_code] = $languages[$language_code];
                }
            }
        }

        return $result;
    }

    public function feedCrgs(array $payload): array
    {

        // if (!permissionsManagement::is_allow('planning')) {
        //     return ['success' => false, 'message' => permissionsManagement::get_error_message()];
        // }

        $where = [];
        $where['status'] = '1';

        // $status = $payload['status');
        // if (!empty($status) && $status != 0) {
        //     $where['status'] = $status;
        // }

        $country = $payload['country'] ?? '';
        // if (!empty($country)) {
        //     $where['country'] = $country;
        // }

        $language = $payload['language'] ?? '';
        // if (!empty($language)) {
        //     $where['language'] = $language;
        // }

        $desc_language = $payload['desc_language'] ?? '';
        if (!empty($desc_language)) {
            $where['languages'] = $desc_language;
        }

        $traffic_sources = $payload['traffic_sources'] ?? '';
        if (!empty($traffic_sources)) {
            $where['restricted_traffic'] = $traffic_sources;
        }

        $regulated = $payload['regulated'] ?? 'all';

        // TODO: add later
        // $permissions = permissionsManagement::get_user_permissions('brokers');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('planning[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        }


        // $mongo = new MongoDBObjects('partner', $where);
        // $brokers = $mongo->findMany();

        $query = Broker::query();
        foreach ($where as $column => $value) {
            if ($column == '$or') {
                if (count($value) > 0) {
                    $query = $query->where(function ($q) use ($value) {
                        foreach ($value as $c => $v) {
                            $q->orWhere($c, '=', $v);
                        }
                    });
                }
            } else {
                $query = $query->where($column, '=', $value);
            }
        }
        $brokers = $query->get(['_id', 'languages', 'restricted_traffic', 'partner_name', 'token', 'created_by', 'account_manager'])->toArray();

        $in = [];
        foreach ($brokers as $broker) {
            $in[] = $broker['_id'];
        }

        $clientId = ClientHelper::clientId();

        $traffic_endpoint_names = Cache::get('endpoint_names_' . $clientId);
        if (!$traffic_endpoint_names) {

            $traffic_endpoints = TrafficEndpoint::all(['token'])->toArray();
            $traffic_endpoint_names = [];
            foreach ($traffic_endpoints as $traffic_endpoint) {
                $traffic_endpoint_names[$traffic_endpoint['_id']] = ($traffic_endpoint['token'] ?? '');
            }

            Cache::put('endpoint_names_' . $clientId, $traffic_endpoint_names, 60 * 60);
        }


        // $biwhere = ['partnerId' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_integrations', $biwhere);
        // $_broker_integrations = $mongo->findMany();

        $_broker_integrations = BrokerIntegration::all()->whereIn('partnerId', $in)->toArray();
        $broker_integrations = [];
        foreach ($_broker_integrations as $broker_integration) {
            $broker_integration['name'] = GeneralHelper::broker_integration_name($broker_integration);
            $broker_integrations[$broker_integration['partnerId']][] = $broker_integration;
        }

        // broker_payouts
        // $pwhere = ['broker' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_payouts', $pwhere);
        // $_broker_payouts = $mongo->findMany();

        $_broker_payouts = BrokerPayout::all()->whereIn('broker', $in)->toArray();
        $broker_payouts = [];
        foreach ($_broker_payouts as $broker_payout) {
            $broker_payouts[$broker_payout['broker']][] = $broker_payout;
        }

        // broker_caps
        // $cwhere = ['broker' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_caps', $cwhere);
        // $_broker_caps = $mongo->findMany();

        $_broker_caps = BrokerCaps::all()->whereIn('broker', $in)->toArray();
        $broker_caps = [];
        foreach ($_broker_caps as $broker_cap) {
            $broker_caps[$broker_cap['broker']][] = $broker_cap;
        }

        // broker_crg
        // $pwhere = ['broker' => ['$in' => $in]];
        // $mongo = new MongoDBObjects('broker_crg', $pwhere);
        // $_broker_crgs = $mongo->findMany();

        $_broker_crgs = BrokerCrg::all()->whereIn('broker', $in)->toArray();
        $broker_crgs = [];
        foreach ($_broker_crgs as $broker_crg) {
            $broker_crgs[$broker_crg['broker']][] = $broker_crg;
        }

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();

        $endpoint_names = $this->get_endpoint_names();
        $endpoint_names = function ($id) use ($endpoint_names) {
            return is_string($id) ? ($endpoint_names[$id] ?? '') : '';
        };

        $result = [];

        foreach ($brokers as $broker) {

            $brokerId = $broker['_id'];

            $_broker_integrations = isset($broker_integrations[$brokerId]) ? $broker_integrations[$brokerId] : [];
            $_broker_crg = $broker_crgs[$brokerId] ?? [];

            // active
            $is_show = false;
            foreach (($broker_payouts[$brokerId] ?? []) as $payout) {
                if ((int)($payout['enabled'] ?? 0) == 1) {
                    foreach (($broker_caps[$brokerId] ?? []) as $cap) {
                        if (
                            ((int)$cap['enable_traffic'] == 1) &&
                            (
                                (empty($country)) ||
                                (empty($cap['country_code'])) ||
                                (is_string($country) && is_string($cap['country_code']) && strtolower($country == strtolower($cap['country_code'])))
                            )
                        ) {
                            $is_show = true;
                            break;
                            break;
                        }
                    }
                }
            }
            if (!$is_show) {
                continue;
            }

            // regulated
            $is_show = true;
            if ($regulated == '0' || $regulated == '1') {
                // mo
                if ($regulated == '0') {
                    foreach ($_broker_integrations as $bi) {
                        if (
                            (isset($bi['regulated']) && $bi['regulated'] == true)
                        ) {
                            $is_show = true;
                            break;
                        }
                    }
                }
                // yes
                if ($regulated == '1') {
                    $is_show = false;
                    foreach ($_broker_integrations as $bi) {
                        $is_show = $is_show | (isset($bi['regulated']) && $bi['regulated'] == true);
                    }
                }
            }
            if (!$is_show) {
                continue;
            }

            // country by payout
            if (!empty($country)) {
                $is_show = false;
                foreach (($broker_payouts[$brokerId] ?? []) as $payout) {
                    if (
                        ((int)($payout['enabled'] ?? false) == 1 && !empty($country)) &&
                        strtoupper($country) == strtoupper($country)
                    ) {
                        $is_show = true;
                        break;
                    }
                }

                if ($is_show) {
                    $is_show = false;
                    foreach (($broker_caps[$brokerId] ?? []) as $cap) {
                        if (
                            (int)$cap['enable_traffic'] == 1 && !empty($cap['country_code']) &&
                            strtoupper($cap['country_code']) == strtoupper($country)
                        ) {
                            $is_show = true;
                            break;
                        }
                    }
                }

                if (!$is_show) {
                    continue;
                }
            }

            // language
            if (!empty($language)) {
                $is_show = false;
                foreach (($broker_payouts[$brokerId] ?? []) as $payout) {
                    if (
                        ((int)$payout['enabled'] == 1) &&
                        (empty($payout['language_code'] ?? '') ||
                            (!empty($payout['language_code'])) && strtoupper($payout['language_code']) == strtoupper($language)
                        )
                    ) {
                        $is_show = true;
                        break;
                    }
                }

                if ($is_show) {
                    $is_show = false;
                    foreach (($broker_caps[$brokerId] ?? []) as $cap) {
                        if (((int)$cap['enable_traffic'] == 1)) {
                            $language_codes = (array)($cap['language_code'] ?? []);
                            foreach ($language_codes as $language_code) {
                                if (
                                    (!empty($language_code) && strtoupper($language_code) == strtoupper($language))
                                ) {
                                    $is_show = true;
                                    break;
                                    break;
                                }
                            }
                        }
                    }
                }

                // foreach ($_broker_integrations as $bi) {
                //     if (
                //         ($bi['status'] == '1') &&
                //         (isset($bi['languages']) && in_array($language, (array)$bi['languages']))
                //     ) {
                //         $is_show = true;
                //         break;
                //     }
                // }

                if (!$is_show) {
                    continue;
                }
            }

            // by broker payouts
            $geos = [];
            foreach (($broker_payouts[$brokerId] ?? []) as $payout) {
                if (
                    (
                        (empty($country)) ||
                        (!empty($country) && empty($payout['country_code'])) ||
                        (!empty($country) && is_string($payout['country_code']) && strtolower($country) == strtolower($payout['country_code'])) ||
                        (!empty($country) && !is_string($payout['country_code']) && in_array($country, (array)($payout['country_code'] ?? [])))
                    ) &&
                    (
                        (empty($language)) ||
                        (!empty($language) && empty($payout['language_code'])) ||
                        (!empty($language) && is_string($payout['language_code']) && strtolower($language) == strtolower($payout['language_code'])) ||
                        (!empty($language) && !is_string($payout['country_code']) && in_array($language, (array)($payout['language_code'] ?? [])))
                    ) &&
                    (int)($payout['enabled'] ?? 0) == 1 && !empty($payout['country_code'] ?? '')
                ) {
                    $geos[] = [
                        'country' => [
                            'code' => $payout['country_code'],
                            'title' => $countries[$payout['country_code']] ?? '',
                        ],
                        'language' => [
                            'code' => ($payout['language_code'] ?? ''),
                            'title' => (!empty($payout['language_code']) ? $languages[$payout['language_code']] : ''),
                        ],
                        'payout' => $payout['payout']
                    ];
                }
            }

            $_languages = (isset($broker['languages']) ? $broker['languages'] : []);
            $desk = [];
            foreach ($_languages as $_language) {
                $desk[] = [
                    'language' => [
                        'code' => $_language,
                        'title' => $languages[$_language]
                    ]
                ];
            }

            $bi_statuses = [
                '0' => 'inactive',
                '1' => 'active',
                '2' => 'archive'
            ];

            $regulations = [];
            foreach ($_broker_integrations as $bi) {
                if (
                    (
                        ($regulated == 'all') ||
                        ($regulated == '1' && isset($bi['regulated']) && $bi['regulated'] == true) ||
                        ($regulated == '0' && (!isset($bi['regulated']) || (isset($bi['regulated']) && $bi['regulated'] != true)))
                    ) &&
                    (
                        (empty($country)) ||
                        (!empty($country) && empty($bi['countries'])) ||
                        (!empty($country) && in_array($country, (array)$bi['countries']))
                    ) &&
                    (
                        (empty($language)) ||
                        (!empty($language) && empty($bi['languages'])) ||
                        (!empty($language) && in_array($language, (array)$bi['languages']))
                    ) &&
                    ($bi['status'] == '1'
                    )
                ) {
                    $regulations[] = [
                        'integration'  => [
                            'name' => $bi['name'],
                            '_id' => $bi['_id']
                        ],
                        'regulated' => ($bi['regulated'] ?? false) ? true : false,
                        'countries' => (array)($bi['countries'] ?? []),
                        'languages' => (array)($bi['languages'] ?? []),
                        'status' => [
                            'value' => ($bi['status'] ?? ''),
                            'title' => (isset($bi['status']) ? $bi_statuses[$bi['status']] : '')
                        ]
                    ];
                }
            }

            $_restricted_traffics = (isset($broker['restricted_traffic']) ? $broker['restricted_traffic'] : []);
            $restricted_traffics = [];
            foreach ($_restricted_traffics as $restricted_traffic) {
                $restricted_traffics[] = [
                    'value' => $restricted_traffic,
                    'title' => self::$traffic_sources[$restricted_traffic]
                ];
            }

            $crg_deals = [];
            foreach ($_broker_crg as $broker_crg) {
                if (
                    (
                        (empty($country)) ||
                        (!empty($country) && empty($broker_crg['country_code'])) ||
                        (!empty($country) && in_array($country, (array)$broker_crg['country_code']))
                    ) &&
                    (
                        (empty($language)) ||
                        (!empty($language) && empty($broker_crg['language_code'])) ||
                        (!empty($language) && in_array($language, (array)$broker_crg['language_code']))
                    ) &&
                    ($broker_crg['status'] == '1')
                ) {

                    $endpoints = array_map(function ($id) use ($traffic_endpoint_names) {
                        return [
                            '_id' => $id,
                            'title' => $traffic_endpoint_names[$id] ?? ''
                        ];
                    }, (array)($broker_crg['endpoint'] ?? []));

                    $ignore_endpoints = array_map(function ($id) use ($traffic_endpoint_names) {
                        return [
                            '_id' => $id,
                            'title' => $traffic_endpoint_names[$id] ?? ''
                        ];
                    }, (array)($broker_crg['ignore_endpoints'] ?? []));

                    $crg_deals[] = [
                        'broker_crg'  => [
                            'name' => $broker_crg['name'],
                            '_id' => $broker_crg['_id']
                        ],
                        'broker' => [
                            '_id' => $broker_crg['broker'],
                            'name' => $broker_crg['name'],
                        ],
                        'broker_crg_type' => [
                            'value' => $broker_crg['type'],
                            'title' => self::$crg_types[$broker_crg['type']],
                        ],
                        'min_crg' => $broker_crg['min_crg'] ?? '',
                        'countries' => (array)($broker_crg['country_code'] ?? []),
                        'languages' => (array)($broker_crg['language_code'] ?? []),
                        /*'endpoint' => [
                            '_id' => $broker_crg['endpoint'] ?? '',
                            'title' => $endpoint_names($broker_crg['endpoint'] ?? ''),
                        ],*/
                        'endpoints' => $endpoints,
                        'ignore_endpoints' => $ignore_endpoints
                    ];
                }
            }

            $result[] = [
                'broker' => [
                    '_id' => $brokerId,
                    'name' => GeneralHelper::broker_name($broker)
                ],
                'geos' => $geos,
                'desk' => $desk,
                'regulations' => $regulations,
                'restricted_traffics' => $restricted_traffics,
                'crg_deals' => $crg_deals
            ];
        }

        return $result;
    }
}
