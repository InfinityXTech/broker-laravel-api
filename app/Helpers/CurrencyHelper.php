<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CurrencyHelper
{
    private static $TTL = 600;
    public static $DailyTTL = 86400;

    public static function ToUsd($value, $currency)
    {
        return round($value * self::GetRate($currency), 2);
    }

    public static function GetRate($currency)
    {
        $currency = strtolower($currency);
        if ($currency == 'usd') {
            return 1;
        }

        $rate = self::GetRateOnDate($currency, date('Y-m-d'), true, true);

        if ($rate > 0) {
            return $rate;
        }

        $cache_key = 'currency_rate_' . $currency;

        $rate = Cache::get($cache_key);
        if ($rate == null) {
            $rate = self::RequestRate($currency, 'USD');
            if (is_numeric($rate)) {
                Cache::put($cache_key, $rate, self::$TTL);
            }
        }
        return $rate;
    }

    private static function RequestRate($from_currency, $to_currency)
    {
        $url = 'https://www.alphavantage.co/query?function=CURRENCY_EXCHANGE_RATE&from_currency=' . $from_currency .
            '&to_currency=' . $to_currency . '&apikey=' . config('remote.alphavantage_apikey');
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        if (!isset($data['Realtime Currency Exchange Rate'])) {
            return $data;
        }
        return (float)$data['Realtime Currency Exchange Rate']['5. Exchange Rate'];
    }

    public static function GetRateOnDate($currency, $datetime, $sync_if_not_exist = true, $last_time = false)
    {
        $currency = strtolower($currency);
        if ($currency == 'usd') {
            return 1;
        }
        $cache_key = 'currency_rate_' . $currency . '_' . date('Y-m-d', strtotime($datetime));
        $rates = null;

        // $rates = Cache::get($cache_key);
        // if ($rates == null) {
        //     $rates = self::RequestDailyRates($currency, 'USD');
        // }

        if ($rates == null) {
            $content = Storage::disk('local')->get('rates/' . date('Y-m-d', strtotime($datetime)) . '.json');
            if (!empty($content)) {
                $rates = json_decode($content, true);
            }
        }

        $rate = 0;
        if ($rates != null) {
            if ($last_time == true) {
                $max_dt = null;
                foreach ($rates as $dt => $data) {
                    if ($max_dt == null) {
                        $max_dt = strtotime($dt);
                    } else if ($max_dt < strtotime($dt)) {
                        $max_dt = strtotime($dt);
                    }
                }
                if ($max_dt != null) {
                    $dt = date('Y-m-d H:i:00', $max_dt);
                    $rate = $rates[$dt] ?? [];
                    $rate = $rate['4. close'] ?? 0;
                }
            } else {
                $dt = date('Y-m-d H:i:00', strtotime($datetime) - date("Z"));
                $rate = $rates[$dt] ?? [];
                $rate = $rate['4. close'] ?? 0;
            }
        }

        if ($sync_if_not_exist && $rate == 0 && date('Y-m-d', strtotime($datetime) == date('Y-m-d'))) {
            $d = new CurrencyHelper();
            $d->Sync($currency);
            $rate = self::GetRateOnDate($currency, $datetime, false, $last_time);
        }

        return $rate;
    }

    public static function RequestDailyRates($from_currency, $to_currency)
    {
        $url = 'https://www.alphavantage.co/query?function=CRYPTO_INTRADAY&symbol=' . $from_currency .
            '&market=' . $to_currency . '&interval=1min&apikey=' . config('remote.alphavantage_apikey');
        $str = file_get_contents($url);
        $json = json_decode($str, true);
        return $json['Time Series Crypto (1min)'] ?? [];
    }

    public function Sync($currency)
    {
        $cache_days = 30;

        $rates = CurrencyHelper::RequestDailyRates($currency, 'USD');

        $path = storage_path('app') . '/rates';

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $group_rates = [];
        foreach ($rates as $time => $rate) {
            $dt = date('Y-m-d', strtotime($time));
            if (!isset($group_rates[$dt])) {
                $group_rates[$dt] = [];
            }
            $group_rates[$dt][] = ['time' => $time, 'rate' => $rate];
        }

        foreach ($group_rates as $dt => $rates) {

            // cache rates
            $cache_key = 'currency_rate_' . $currency . '_' . $dt;
            $cache_rates = Cache::get($cache_key);
            $cache_rates = $cache_rates ?? [];
            foreach ($rates as $rate) {
                // if (!isset($cache_rates[$rate['time']])) {
                $cache_rates[$rate['time']] = $rate['rate'];
                // }
            }
            Cache::put($cache_key, $cache_rates, CurrencyHelper::$DailyTTL * $cache_days);

            // file rates
            $file_rates = [];
            $content = Storage::disk('local')->get('rates/' . $dt . '.json');
            if (!empty($content)) {
                $file_rates = json_decode($content, true);
            }

            foreach ($rates as $rate) {
                // if (!isset($file_rates[$rate['time']])) {
                $file_rates[$rate['time']] = $rate['rate'];
                // }
            }
            Storage::disk('local')->put('rates/' . $dt . '.json', json_encode($file_rates));
        }

        // clear old 
        $expire = strtotime('-' . $cache_days . ' DAYS');

        $files = glob($path . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (filemtime($file) > $expire) {
                continue;
            }
            unlink($file);
        }
    }
}
