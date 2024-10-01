<?php

namespace App\Classes\TrafficEndpoints;

use App\Models\Broker;
use App\Classes\DailyCaps;
use App\Helpers\ClientHelper;

use App\Helpers\GeneralHelper;
use App\Classes\PartnerPayouts;

use App\Models\Brokers\BrokerCrg;
use App\Models\Brokers\BrokerCaps;
use App\Models\Brokers\BrokerIntegration;
use App\Models\TrafficEndpoints\TrafficEndpointPrivateDeal;

class ManageFeed
{

    private $payload = [];

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    private $cap_types = [
        'leads' => 'Leads',
        'ftd' => 'FTD',
    ];

    function get_feed_config($id)
    {
        $clientConfig = ClientHelper::clientConfig();
        $url = $clientConfig['serving_domain'] . config('remote.feed_details_url_path') . '?gzip'; //&base64';

        $post = ['id' => $id];
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

        if (!empty($server_output)) {
            $server_output = gzuncompress($server_output); // gzdecode($server_output); //gzuncompress
            // $server_output = base64_decode($server_output);
        }

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

    function get_broker_names()
    {
        // ->where('partner_type', '=', '1')
        $partners = Broker::query()->get(['partner_name', 'token', 'created_by', 'account_manager'])->toArray();
        $result = [];
        foreach ($partners as $partner) {
            $result[$partner['_id']] = GeneralHelper::broker_name($partner);
        }
        return $result;
    }

    function get_broker_caps()
    {
        $caps = BrokerCaps::query()
            ->get(['_id', 'cap_type', 'endpoint_dailycaps', 'endpoint', 'daily_cap', 'endpoint_livecaps', 'live_caps'])
            ->toArray();

        $result = [];
        foreach ($caps as $cap) {
            $result[$cap['_id']] = $cap;
        }
        return $result;
    }

    function get_integration_names()
    {
        $integrations = BrokerIntegration::query()->get(['name', 'partnerId'])->toArray();
        $result = [];
        foreach ($integrations as $integration) {
            $result[$integration['_id']] = GeneralHelper::broker_integration_name($integration); //$integration['name'] ?? '';
        }
        return $result;
    }

    private function getCRGGroupByeExtraFields(array $field_names): array
    {
        if (count($field_names) > 0) {
            $broker_crgs = BrokerCrg::query()->whereIn('status', ['1', 1])->get()->toArray();
            $endpoint_crgs = TrafficEndpointPrivateDeal::query()->whereIn('status', ['1', 1])->get()->toArray();

            $_broker_crgs = $this->getCRGMultiFieldValuesUnique($broker_crgs, $field_names);
            $_endpoint_crgs = $this->getCRGMultiFieldValuesUnique($endpoint_crgs, $field_names);
            return array_unique(array_merge($_broker_crgs, $_endpoint_crgs));
        }
        return [];
    }

    private function getCRGMultiFieldValuesUnique(array $crg_deals, array $field_names): array
    {
        $result = [];

        // get data
        $array = $crg_deals ?? [];

        // filter crg when we have at least one value of this fields
        $filtered = array_filter($array, function (array $crg) use ($field_names) {
            $cnt = array_reduce($field_names, fn (?int $carry, string $field_name) => $carry = ($carry ?? 0) + count((array)($crg[$field_name] ?? []))) ?? 0;
            return $cnt > 0;
        });

        if (count($filtered) == 0) {
            return [];
        }

        // group by field name and work with values
        $group_by_field_name = array_reduce($filtered, function (?array $carry, array $crg) use ($field_names) {
            $carry ??= [];
            $group_by_field_name = array_reduce($field_names, function (?array $fcarry, string $field_name) use ($crg) {
                $fcarry ??= [];
                $fcarry[$field_name] ??= [];
                $list = array_filter((array)($crg[$field_name] ?? []), fn ($l) => !empty(trim($l ?? '')));
                if (count($list) > 0) {
                    $list = array_map('trim', $list);
                    $list = array_map('strtolower', $list);
                    $fcarry[$field_name] = array_merge($fcarry[$field_name] ?? [], array_map(fn ($v) => $v, $list));
                }
                return $fcarry;
            }) ?? [];

            foreach ($group_by_field_name as $field_name => $values) {
                $carry[$field_name] = array_unique(array_merge($carry[$field_name] ?? [], $values));
            }
            return $carry;
        }) ?? [];

        foreach ($field_names as $field_name) {
            $group_by_field_name[$field_name] ??= [];
        }

        // make chains
        $result = [];
        $a = $group_by_field_name[$field_names[0]] ?? [];
        if (count($a) == 1) {
            // $result = [$crg_data_name . '_' . $a[0] . '|||'];
            $result = [$a[0] . '|||'];
        } else {
            for ($k = 1; $k < count($field_names); $k++) {
                $s = '';
                $b = $group_by_field_name[$field_names[$k]];
                if (count($a) == 0) {
                    foreach ($b as $v2) {
                        $s = '|||' . $v2;
                        // $result[] = $crg_data_name . '_' . $s;
                        $result[] = $s;
                        // echo $s . PHP_EOL;
                    }
                } else {
                    foreach ($a as $v) {
                        foreach ($b as $v2) {
                            $s = $v . '|||' . $v2;
                            // $result[] = $crg_data_name . '_' . $s;
                            $result[] = $s;
                            // echo $s . PHP_EOL;
                        }
                    }
                }
            }
        }

        $i = 0;
        foreach ($group_by_field_name as $field_name => $words) {
            foreach ($words as $word) {
                $v = '';
                if ($i == 0) {
                    $v = $word . '|||';
                } else {
                    $v = '|||' . $word;
                }
                if (!in_array($v, $result)) {
                    $result[] = $v;
                }
            }
            $i++;
        }

        return array_filter($result ?? [], fn ($r) => $r != '|||');
    }

    public function feed_traffic_endpoint_visualization_group_by_fields(string $traffic_endpoint_id = ''): array
    {
        $field_names = ['sub_publisher_list', 'funnel_list'];

        $broker_crgs = BrokerCrg::query()
            ->whereIn('status', ['1', 1])
            ->get(array_merge($field_names, ['endpoint']))
            ->toArray();

        $broker_crgs = array_filter($broker_crgs, function (array $crg) use ($traffic_endpoint_id) {
            $endpoints = (array)($crg['endpoint'] ?? []);
            return in_array($traffic_endpoint_id, $endpoints);
        });

        $endpoint_crgs = TrafficEndpointPrivateDeal::query();
        if (!empty($traffic_endpoint_id)) {
            $endpoint_crgs = $endpoint_crgs->where('TrafficEndpoint', '=', $traffic_endpoint_id);
        }
        $endpoint_crgs = $endpoint_crgs->whereIn('status', ['1', 1])->get($field_names)->toArray();

        $filtered = array_filter(array_merge($broker_crgs, $endpoint_crgs), function (array $crg) use ($field_names) {
            $cnt = array_reduce($field_names, fn (?int $carry, string $field_name) => $carry = ($carry ?? 0) + count((array)($crg[$field_name] ?? []))) ?? 0;
            return $cnt > 0;
        });

        $result = array_reduce($filtered, function (?array $carry, array $crg) use ($field_names) {
            $carry ??= [];
            $group_by_field_name = array_reduce($field_names, function (?array $fcarry, string $field_name) use ($crg) {
                $fcarry ??= [];
                $fcarry[$field_name] ??= [];
                $list = array_map(fn ($item) => strtolower(trim(preg_replace('/\s+/', '', $item ?? ''))), (array)($crg[$field_name] ?? []));
                $list = array_filter($list, fn ($l) => !empty(trim($l ?? '')));
                if (count($list) > 0) {
                    $fcarry[$field_name] = array_merge($fcarry[$field_name] ?? [], $list);
                }
                return $fcarry;
            }) ?? [];

            foreach ($group_by_field_name as $field_name => $values) {
                $carry[$field_name] = array_unique(array_merge($carry[$field_name] ?? [], $values));
            }
            return $carry;
        }) ?? [];

        $caps = BrokerCaps::query()->where('enable_traffic', '=', true)->get(['endpoint_dailycaps', 'blocked_funnels', 'broker', 'restrict_endpoints']);
        $caps = $caps ? $caps->toArray() : [];

        $caps = array_filter($caps, function (array $cap) use ($traffic_endpoint_id) {
            $restrict_endpoints = (array)($cap['restrict_endpoints'] ?? []);
            return in_array($traffic_endpoint_id, $restrict_endpoints);
        });

        $brokerIds = array_reduce((array)$caps ?? [], function (?array $curry, array $cap) {
            $curry ??= [];
            $brokerId = $cap['broker'] ?? '';
            if (!in_array($brokerId, $curry)) {
                $curry[] = $brokerId;
            }
            return $curry;
        }) ?? [];

        $brokerIds = array_map(fn (string $brokerId) => new \MongoDB\BSON\ObjectId($brokerId), $brokerIds);
        $_brokers = Broker::query()->whereIn('_id', $brokerIds)->get(['_id', 'status'])->toArray();
        $brokers = [];
        foreach ($_brokers as $broker) {
            $brokers[(string)$broker['_id']] = (bool)($broker['status'] ?? false);
        }

        foreach ($caps as $cap) {
            if (($brokers[$cap['broker'] ?? ''] ?? false)) {
                $endpoint_dailycaps = json_decode(json_encode((array)($cap['endpoint_dailycaps'] ?? [])), true);
                if (is_string($endpoint_dailycaps) && $endpoint_dailycaps == "") {
                    $endpoint_dailycaps = [];
                }
                $endpoints = $endpoint_dailycaps['endpoint'] ?? [];
                for ($i = 0; $i < count($endpoints); $i++) {
                    if ($traffic_endpoint_id == $endpoints[$i]) {
                        $sub_publisher_lists = (array)($endpoint_dailycaps['sub_publisher_list'][$i] ?? []);
                        $sub_publisher_lists = array_map(fn ($item) => strtolower(trim(preg_replace('/\s+/', '', $item ?? ''))), $sub_publisher_lists);
                        $sub_publisher_lists = array_filter($sub_publisher_lists, fn ($l) => !empty($l));
                        $result['sub_publisher_list'] = array_unique(array_merge($result['sub_publisher_list'] ?? [], $sub_publisher_lists));
                    }
                }

                $blocked_funnels = (array)($cap['blocked_funnels'] ?? []);
                $blocked_funnels = array_map(fn ($item) => strtolower(trim(preg_replace('/\s+/', '', $item ?? ''))), $blocked_funnels);
                $blocked_funnels = array_filter($blocked_funnels, fn ($l) => !empty($l));
                $result['funnel_list'] = array_unique(array_merge($result['funnel_list'] ?? [], $blocked_funnels));
            }
        }

        // $_ext_group = ['sub_publisher_list', 'funnel_list'];
        // $pairs = $this->getCRGGroupByeExtraFields($_ext_group);
        // $result = [];
        // array_map(function (string $v) use (&$result) {
        //     $title = '';
        //     if (strpos($v, '|||') === 0) {
        //         $title = 'Funnel: ' . substr($v, 3);
        //     } else if (strpos($v, '|||') === strlen($v) - 3) {
        //         $title = 'Sub Publisher: ' . substr($v, 0, strpos($v, '|||'));
        //     } else {
        //         $pairs = explode('|||', $v);
        //         $title = 'Sub Publisher: ' . $pairs[0] . ', Funnel: ' . $pairs[1];
        //     }
        //     $result[] = ['value' => $v, 'label' => $title];
        // }, $pairs);

        return $result;
    }

    function feed_traffic_endpoint_visualization(string $id)
    {
        $datas = $this->get_feed_config($id);

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();
        $broker_caps = $this->get_broker_caps();
        $broker_names = $this->get_broker_names();
        $integration_names = $this->get_integration_names();
        $unused_payouts = PartnerPayouts::fromTrafficEndpoints()->getPayouts($id);

        $active_icon = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs" viewBox="0 0 200 200" width="15" height="15"><g transform="matrix(8.333333333333334,0,0,8.333333333333334,0,0)"><path d="M9.000 12.000 A3.000 3.000 0 1 0 15.000 12.000 A3.000 3.000 0 1 0 9.000 12.000 Z" fill="none" stroke="#5bb990" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M7.5,22.5a4.5,4.5,0,0,1,9,0Z" fill="none" stroke="#5bb990" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M7.227,16.773a6.75,6.75,0,1,1,9.546,0" fill="none" stroke="#5bb990" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M4.575,19.425a10.5,10.5,0,1,1,14.85,0" fill="none" stroke="#5bb990" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></g></svg>';
        $inactive_icon = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs" viewBox="0 0 200 200" width="15" height="15"><g transform="matrix(8.333333333333334,0,0,8.333333333333334,0,0)"><path d="M9.000 12.000 A3.000 3.000 0 1 0 15.000 12.000 A3.000 3.000 0 1 0 9.000 12.000 Z" fill="none" stroke="#dc4692" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M7.5,22.5a4.5,4.5,0,0,1,9,0Z" fill="none" stroke="##dc4692" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M7.227,16.773a6.75,6.75,0,1,1,9.546,0" fill="none" stroke="#dc4692" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M4.575,19.425a10.5,10.5,0,1,1,14.85,0" fill="none" stroke="#dc4692" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></g></svg>';

        $result = [];

        foreach ($datas as $code => $data) {

            $result_item = [];

            $_waterfalls = $data['waterfalls'] ?? [];

            $group_by_fields = '';
            if (!empty($this->payload['sub_publisher'] ?? '')) {
                $group_by_fields = $this->payload['sub_publisher'] . '|||';
            }
            if (!empty($this->payload['funnel'] ?? '')) {
                $group_by_fields .= (empty($group_by_fields) ? '|||' : '') . $this->payload['funnel'];
            }

            $waterfalls = [];
            if (!empty($group_by_fields)) {
                if (isset($_waterfalls['group_by'][$group_by_fields])) {
                    $waterfalls = $_waterfalls['group_by'][$group_by_fields] ?? [];
                } else {
                    $group_by_fields = ($this->payload['sub_publisher'] ?? '') . '|||';
                    if (isset($_waterfalls['group_by'][$group_by_fields])) {
                        $waterfalls = $_waterfalls['group_by'][$group_by_fields] ?? [];
                    } else {
                        $group_by_fields = '|||' . ($this->payload['funnel'] ?? '');
                        if (isset($_waterfalls['group_by'][$group_by_fields])) {
                            $waterfalls = $_waterfalls['group_by'][$group_by_fields] ?? [];
                        }
                    }
                }
            }

            if (empty($waterfalls)) {
                $waterfalls = $_waterfalls['general'] ?? [];
            }

            $active_waterfalls = array_filter($waterfalls, function ($integrations) {
                return count($integrations ?? []) > 0 && $integrations[0]['skipped'] == false;
                // if ($integrations['general']) {
                //     return count($integrations['general']) > 0 && $integrations['general'][0]['skipped'] == false;
                // } else {
                //     return count($integrations) > 0 && $integrations[0]['skipped'] == false;
                // }
            });

            $show_skips = ($this->payload['skip_details'] ?? '');
            if ($show_skips == 'hide' || ($show_skips == 'skip' && !empty($active_waterfalls))) {
                $waterfalls = $active_waterfalls;
            }

            if (empty($waterfalls)) {
                continue;
            }

            $country = substr($code, 0, 2);
            $language = substr($code, 3, 2);

            if (
                isset($this->payload['country']) &&
                !empty($this->payload['country']) &&
                strtolower($this->payload['country']) != strtolower($country)
            ) {
                continue;
            }

            if (
                isset($this->payload['language']) &&
                !empty($this->payload['language']) &&
                strtolower($this->payload['language']) != strtolower($language)
            ) {
                continue;
            }

            unset($unused_payouts[$country], $unused_payouts[$code]);

            $result_item['country'] = $country;
            $result_item['language'] = $language;

            $result_item['country_title'] = $countries[$country] ?? '';
            $result_item['language_title'] = $languages[$language] ?? '';

            $result_item['list'] = [];

            foreach ($waterfalls as $integrationId => $rules) {

                // if (!empty($group_by_fields)) {
                //     // echo $group_by_fields;
                //     // GeneralHelper::PrintR($rules);die();
                //     $rules = $rules['group_by'][$group_by_fields] ?? [];
                // } else {
                //     $rules = $rules['general'] ?? [];
                // }

                foreach ($rules as $rule) {

                    $cap = $broker_caps[$rule['cap_id']];
                    $brokerId = $rule['broker_id'];

                    if ($rule['skipped']) {
                        $skip_details = (array)($rule['skip_details'] ?? []);
                        $icon = $inactive_icon;
                    } else {
                        $skip_details = [];
                        $icon = $active_icon;
                    }

                    $ep_daily_cap = DailyCaps::get_endpoint_daily_cap($cap, $id);
                    $ep_live_caps = DailyCaps::get_endpoint_live_caps($cap, $id);

                    $result_item['list'][] = [
                        'skip_details' => $skip_details,
                        'icon' => $icon,
                        'broker_title' => $broker_names[$brokerId] ?? '',
                        'integration_title' => $integration_names[$integrationId] ?? '',
                        'cap_type' => $this->cap_types[$cap['cap_type']] ?? '',
                        'ep_live_caps' => $ep_live_caps,
                        'ep_daily_cap' => $ep_daily_cap
                    ];
                }
            }

            $result[] = $result_item;
        }

        foreach ((array)($unused_payouts ?? []) as $payout) {

            if (!($payout['enabled'] ?? false)) {
                continue;
            }

            $result_item = [];

            $country = $payout['country_code'] ?? '';
            $language = $payout['language_code'] ?? '';

            if (
                isset($this->payload['country']) &&
                strtolower($this->payload['country']) != strtolower($country)
            ) {
                continue;
            }

            if (
                isset($this->payload['language']) &&
                strtolower($this->payload['language']) != strtolower($language)
            ) {
                continue;
            }

            $result_item['country'] = $country;
            $result_item['language'] = $language;

            $result_item['country_title'] = $countries[$country] ?? '';
            $result_item['language_title'] = $languages[$language] ?? '';

            $result_item['icon'] = $inactive_icon;

            $result_item['body'] = 'There is no Broker integration connections at the current time';

            $result[] = $result_item;
        }

        if (count($result) == 0) {
            $result['message'] = 'There is no Broker integration connections at the current time';
        }

        return $result;
    }
}
