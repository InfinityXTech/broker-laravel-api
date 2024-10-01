<?php

namespace App\Classes;

use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Models\Brokers\BrokerCrg;
use App\Models\Brokers\BrokerPayout;
use Illuminate\Support\Facades\Cache;
use phpDocumentor\Reflection\Types\Boolean;
use App\Models\TrafficEndpoints\TrafficEndpointPayout;

class PartnerPayouts
{
    private $payouts;

    public function __construct($payouts)
    {
        $this->payouts = $payouts;
    }

    public static function fromBrokers(string $brokerId = '')
    {
        $cache_key = 'BrokerPayout_' . $brokerId . ClientHelper::clientId();
        $cached = Cache::get($cache_key, null);
        if ($cached != null) {
            return new PartnerPayouts($cached);
        }

        $query = BrokerPayout::query();

        if (!empty($brokerId)) {
            $query = $query->where('broker', '=', $brokerId);
        }

        $find = $query->get([
            '_id',
            'broker',
            'country_code',
            'language_code',
            'payout',
            'enabled'
        ])->toArray();

        $broker_payouts = [];
        foreach ($find as $broker_payout) {
            $broker_id = $broker_payout['broker'];
            $key = strtolower($broker_payout['country_code']);
            if (isset($broker_payout['language_code']) && !empty($broker_payout['language_code'])) {
                $key .= '_' . strtolower($broker_payout['language_code']);
            }
            if (!isset($broker_payouts[$broker_id])) {
                $broker_payouts[$broker_id] = [];
            }
            $broker_payouts[$broker_id][$key] = $broker_payout;
        }

        Cache::put($cache_key, $broker_payouts, 5);

        return new PartnerPayouts($broker_payouts);
    }

    public static function fromBrokersCRG(string $brokerId = '')
    {

        $cache_key = 'BrokersCRG_' . $brokerId . ClientHelper::clientId();
        $cached = Cache::get($cache_key, null);
        if ($cached != null) {
            return new PartnerPayouts($cached);
        }

        $query = BrokerCrg::query()->whereIn('status', ['1', 1]);

        if (!empty($brokerId)) {
            $query = $query->where('broker', '=', $brokerId);
        }

        $find = $query->get([
            '_id',
            'broker',
            'country_code',
            'language_code',
            'status',
            'type',
            'endpoint',
            'min_crg',
            'payout',
            'blocked_schedule'
        ])->toArray();

        $broker_payouts = [];
        foreach ($find as $broker_payout) {
            $broker_id = $broker_payout['broker'];
            if (!empty($broker_payout['language_code']) && is_string($broker_payout['language_code'] ?? '')) {
                if (is_string($broker_payout['country_code'])) {
                    $key = strtolower($broker_payout['country_code']);
                    if (!empty($language_code)) {
                        $key .= '_' . strtolower($language_code);
                    }
                    if (!isset($broker_payouts[$broker_id])) {
                        $broker_payouts[$broker_id] = [];
                    }
                    if (!isset($broker_payouts[$broker_id][$key])) {
                        $broker_payouts[$broker_id][$key] = [];
                    }
                    $broker_payouts[$broker_id][$key][] = $broker_payout;
                } else {
                    foreach (($broker_payout['country_code'] ?? []) as $country) {
                        $key = strtolower($country);
                        if (!empty($language_code)) {
                            $key .= '_' . strtolower($language_code);
                        }
                        if (!isset($broker_payouts[$broker_id])) {
                            $broker_payouts[$broker_id] = [];
                        }
                        if (!isset($broker_payouts[$broker_id][$key])) {
                            $broker_payouts[$broker_id][$key] = [];
                        }
                        $broker_payouts[$broker_id][$key][] = $broker_payout;
                    }
                }
            } else {
                foreach (((array)$broker_payout['language_code'] ?? []) as $language_code) {
                    if (is_string($broker_payout['country_code'])) {
                        $key = strtolower($broker_payout['country_code']);
                        if (!empty($language_code)) {
                            $key .= '_' . strtolower($language_code);
                        }
                        if (!isset($broker_payouts[$broker_id])) {
                            $broker_payouts[$broker_id] = [];
                        }
                        if (!isset($broker_payouts[$broker_id][$key])) {
                            $broker_payouts[$broker_id][$key] = [];
                        }
                        $broker_payouts[$broker_id][$key][] = $broker_payout;
                    } else {
                        foreach (($broker_payout['country_code'] ?? []) as $country) {
                            $key = strtolower($country);
                            if (!empty($language_code)) {
                                $key .= '_' . strtolower($language_code);
                            }
                            if (!isset($broker_payouts[$broker_id])) {
                                $broker_payouts[$broker_id] = [];
                            }
                            if (!isset($broker_payouts[$broker_id][$key])) {
                                $broker_payouts[$broker_id][$key] = [];
                            }
                            $broker_payouts[$broker_id][$key][] = $broker_payout;
                        }
                    }
                }
            }
        }

        Cache::put($cache_key, $broker_payouts, 5);

        return new PartnerPayouts($broker_payouts);
    }

    public static function fromBrokersWithCRG(bool $enabled = null, string $brokerId = '')
    {
        $result = [];
        $_payouts = self::fromBrokers($brokerId)->getAllPayouts();
        $_payouts_crg = self::fromBrokersCRG($brokerId)->getAllPayouts();

        $def = [
            'payout' => [
                'by_country_language' => [],
                'by_amount' => []
            ],
            'crg' => [
                'by_country_language' => [],
                'by_amount' => []
            ]
        ];

        foreach ($_payouts as $brokerId => $languages) {
            if (!isset($result[$brokerId])) {
                $result[$brokerId] = $def;
            }
            foreach ($languages as $language => $payout) {

                $_enabled = ((bool)($payout['enabled'] ?? false)) == true;
                $_payout = (float)($payout['payout'] ?? 0);

                if ($enabled === true && !$_enabled) {
                    continue;
                }
                if ($enabled === false && $_enabled) {
                    continue;
                }
                if ((float)($_payout ?? 0) == 0) {
                    continue;
                }

                if (!isset($result[$brokerId]['payout']['by_country_language'][$language])) {
                    $result[$brokerId]['payout']['by_country_language'][$language] = [];
                }

                if (!isset($result[$brokerId]['payout']['by_amount'][$_payout])) {
                    $result[$brokerId]['payout']['by_amount'][$_payout] = [];
                }

                $item = [
                    'enabled' => $_enabled,
                    'country_language' => $language,
                    'amount' => $_payout
                ];

                $result[$brokerId]['payout']['by_country_language'][$language][] = $item;
                $result[$brokerId]['payout']['by_amount'][$_payout][] = $item;
            }
        }

        $traffic_endpoint_cache_key = 'TrafficEndpoint_id_token_' . ClientHelper::clientId();
        $get_traffic_endpoints = function () use ($traffic_endpoint_cache_key) {
            $traffic_endpoints = [];
            $_traffic_endpoints = TrafficEndpoint::all(['_id', 'token']);
            foreach ($_traffic_endpoints as $traffic_endpoint) {
                $traffic_endpoints[$traffic_endpoint->_id] = ['_id' => $traffic_endpoint->_id, 'token' => $traffic_endpoint->token];
            }
            Cache::put($traffic_endpoint_cache_key, $traffic_endpoints, 60 * 60);
            return $traffic_endpoints;
        };

        $traffic_endpoints = Cache::get($traffic_endpoint_cache_key, null);
        if ($traffic_endpoints == null) {
            $traffic_endpoints = $get_traffic_endpoints();
        }

        $crg_by_amount = function ($brokerId, $name, $def) use (&$result, $traffic_endpoints, $get_traffic_endpoints) {
            if (!isset($result[$brokerId][$name]['by_amount'][$def['amount']])) {
                $result[$brokerId][$name]['by_amount'][$def['amount']] = [];
            }
            $_endpoints = [];
            foreach ($def['endpoint'] as $id) {

                if (!isset($traffic_endpoints[$id])) {
                    $traffic_endpoints = $get_traffic_endpoints();
                }

                if (isset($traffic_endpoints[$id])) {
                    $_endpoints[] = $traffic_endpoints[$id];
                }
            }
            $def['endpoint'] = $_endpoints;
            $result[$brokerId][$name]['by_amount'][$def['amount']][] = $def;
        };

        foreach ($_payouts_crg as $brokerId => $languages) {
            if (!isset($result[$brokerId])) {
                $result[$brokerId] = $def;
            }
            foreach ($languages as $language => $crgs) {
                foreach ($crgs as $crg) {
                    $_enabled = ((int)$crg['status'] ?? 0) == 1;

                    if ($enabled === true && !$_enabled) {
                        continue;
                    }
                    if ($enabled === false && $_enabled) {
                        continue;
                    }

                    $item_payout = [
                        'enabled' => $_enabled,
                        'country_language' => $language,
                        'endpoint' => (array)($crg['endpoint'] ?? []),
                        'amount' => (float)($crg['payout'] ?? 0)
                    ];

                    $item_crg = [
                        'enabled' => $_enabled,
                        'country_language' => $language,
                        'endpoint' => (array)($crg['endpoint'] ?? []),
                        'amount' => (float)($crg['min_crg'] ?? 0)
                    ];

                    if ((int)$crg['type'] == 1 && $crg['payout'] > 0) { //Payout Deal
                        $result[$brokerId]['payout']['by_country_language'][$language][] = $item_payout;
                        $crg_by_amount($brokerId, 'payout', $item_payout);
                    } else
                if ((int)$crg['type'] == 3) { //Payout + CRG Deal
                        $result[$brokerId]['payout']['by_country_language'][$language][] = $item_payout;
                        if ($crg['payout'] > 0) {
                            $crg_by_amount($brokerId, 'payout', $item_payout);
                        }
                        if ($crg['min_crg'] > 0) {
                            $result[$brokerId]['crg']['by_country_language'][$language][] = $item_crg;
                            $crg_by_amount($brokerId, 'crg', $item_crg);
                        }
                    } else
                if ((int)$crg['type'] == 2 && $crg['min_crg'] > 0) { //CRG Deal
                        $result[$brokerId]['crg']['by_country_language'][$language][] = $item_crg;
                        $crg_by_amount($brokerId, 'crg', $item_crg);
                    }
                }
            }
        }
        return new PartnerPayouts($result);
    }

    public static function fromTrafficEndpoints()
    {
        $find = TrafficEndpointPayout::all([
            'TrafficEndpoint',
            'country_code',
            'language_code',
            'enabled'
        ])->toArray();
        $_endpoint_payouts = [];
        foreach ($find as $endpoint_payout) {
            $traffic_endpoint_id = $endpoint_payout['TrafficEndpoint'];
            $key = strtolower($endpoint_payout['country_code']);
            if (isset($endpoint_payout['language_code']) && !empty($endpoint_payout['language_code'])) {
                $key .= '_' . strtolower($endpoint_payout['language_code']);
            }
            if (!isset($_endpoint_payouts[$traffic_endpoint_id])) {
                $_endpoint_payouts[$traffic_endpoint_id] = [];
            }
            $_endpoint_payouts[$traffic_endpoint_id][$key] = $endpoint_payout;
        }
        return new PartnerPayouts($_endpoint_payouts);
    }

    public function getAllPayouts()
    {
        return $this->payouts;
    }

    public function getPayouts($partner_id)
    {
        return $this->payouts[$partner_id] ?? 0;
    }

    public function getPayout($partner_id, $country, $language)
    {
        $result = ['success' => false];

        $c = strtolower($country);
        $l = strtolower($language);

        if (isset($this->payouts[$partner_id])) {
            // country language
            if (
                (isset($this->payouts[$partner_id][$c . '_' . $l]))
            ) {
                $result['success'] = true;
                $result['payout'] = $this->payouts[$partner_id][$c . '_' . $l];
            }
            // country
            elseif (isset($this->payouts[$partner_id][$c])) {
                $result['success'] = true;
                $result['payout'] = $this->payouts[$partner_id][$c];
            } else {
                $result['found_another_country_language'] = false;
                foreach ($this->payouts[$partner_id] as $country_language => $payout) {
                    if (strlen($country_language) > 1 && strtolower(substr($country_language, 0, 2)) == $c) {
                        $result['found_another_country_language'] = true;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    public function getPayoutError($partner_id, $country, $language)
    {
        $payout = $this->getPayout($partner_id, $country, $language);
        if ($payout && $payout['success'] && isset($payout['payout'])) {

            $payout_enable = (isset($payout['payout']['enabled']) && ((int)$payout['payout']['enabled']) == 1);
            if (!$payout_enable) {
                return "Payout by country and language is disabled";
            }
        } elseif ($payout && !$payout['success']) {
            if (($payout['found_another_country_language'] ?? false) != false) {
                return "Payout has no country (language)"; // in this case - don't use disable language in JSON
            } else {
                return "Payout has no country (language)";
            }
        } else {
            return "Payout has no country (language)";
        }
        return false;
    }
}
