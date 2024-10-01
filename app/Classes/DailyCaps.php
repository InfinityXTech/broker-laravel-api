<?php
namespace App\Classes;

class DailyCaps
{
    public static function is_exceeded($broker_cap, $endpoint)
    {
        return self::get_endpoint_live_caps($broker_cap, $endpoint) >= self::get_endpoint_daily_cap($broker_cap, $endpoint);
    }

    public static function has_allocations($broker_cap)
    {
        return !empty((array)$broker_cap['endpoint_dailycaps']);
    }

    public static function get_all_allocations($broker_cap)
    {
        $total_daily_cap = 0;
        $total_live_cap = 0;
        $result = [];

        foreach ($broker_cap['endpoint_dailycaps']['endpoint'] ?? [] as $i => $endpointId) {
            $daily_cap = (int)($broker_cap['endpoint_dailycaps']['daily_cap'][$i] ?? 0);
            $live_cap = (int)($broker_cap['endpoint_livecaps'][$i] ?? 0);
            $total_daily_cap += $daily_cap;
            $total_live_cap += $live_cap;

            $result[] = [
                'endpointId' => $endpointId,
                'daily_cap' => $daily_cap,
                'live_cap' => $live_cap,
            ];
        }
        if ($broker_cap['daily_cap'] > $total_daily_cap) {
            $result[] = [
                'name' => 'Market',
                'daily_cap' => $broker_cap['daily_cap'] - $total_daily_cap,
                'live_cap' => ($broker_cap['live_caps'] ?? 0) - $total_live_cap,
            ];
        }
        return $result;
    }

    public static function get_endpoint_daily_cap($broker_cap, $endpoint)
    {
        $allocated_cap = 0;
        $endpoint_dailycaps = (array)($broker_cap['endpoint_dailycaps'] ?? []);
        if (!empty($endpoint_dailycaps)) {

            $index = array_search($endpoint, (array)$endpoint_dailycaps['endpoint']);
            if ($index !== false) {
                return (int)$endpoint_dailycaps['daily_cap'][$index];
            }

            $allocated_cap = array_sum((array)$endpoint_dailycaps['daily_cap']);
        }
        return $broker_cap['daily_cap'] - $allocated_cap;
    }

    public static function get_endpoint_live_caps($broker_cap, $endpoint)
    {
        $allocated_cap = 0;
        $endpoint_dailycaps = (array)($broker_cap['endpoint_dailycaps'] ?? []);
        if (!empty($endpoint_dailycaps)) {

            $index = array_search($endpoint, (array)$endpoint_dailycaps['endpoint']);
            if ($index !== false) {
                return (int)($broker_cap['endpoint_livecaps'][$index] ?? 0);
            }

            $allocated_cap = array_sum((array)$broker_cap['endpoint_livecaps']);
        }
        return ($broker_cap['live_caps'] ?? 0) - $allocated_cap;
    }
}
