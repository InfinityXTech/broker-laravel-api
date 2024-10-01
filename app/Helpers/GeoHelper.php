<?php

namespace App\Helpers;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Cache;

class GeoHelper
{
    public static function getUserIP(): string
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ip = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ip = $_SERVER['REMOTE_ADDR'];
        return $ip;
    }

    public static function getGeoData(string $ip)
    {
        try {

            $cache_key = 'geo_' . $ip;
            $geo = Cache::get($cache_key);
            if ($geo) {
                return $geo;
            }

            $locationData = self::fetchLocationData($ip);
            $geo = [
                'country' => $locationData['country'],
                'region' => $locationData['region_name'],
                'region_code' => $locationData['region_code'],
                'city' => $locationData['city'],
                'zip_code' => $locationData['zip_code'],
                'connection_type' => $locationData['connection_type'],
                'latitude' => $locationData['latitude'],
                'longitude' => $locationData['longitude'],
                'isp' => $locationData['isp']
            ];
            Cache::put($cache_key, $geo, 60 * 60 * 1); // 1 hour
        } catch (\Exception $ex) {
        }
        return $geo;
    }

    /**
     * Get Location by IP (maxmindDb)
     *
     * @param string $ip
     * @return array
     */
    private static function fetchLocationData(string $ip): array
    {
        if (isset($ip) && !empty($ip)) {

            try {

                $maxmind_db_path = storage_path('maxmind_db');

                $reader = new Reader($maxmind_db_path . DIRECTORY_SEPARATOR . 'GeoIP2-City.mmdb');
                $array = array();
                $record = $reader->city($ip);

                $array['country'] = $record->country->isoCode;
                $array['region_name'] = $record->mostSpecificSubdivision->name;
                $array['region_code'] = $record->mostSpecificSubdivision->isoCode;
                $array['city'] = $record->city->name;
                $array['zip_code'] = $record->postal->code;
                $array['latitude'] = $record->location->latitude;
                $array['longitude'] = $record->location->longitude;
                $reader = new Reader($maxmind_db_path . DIRECTORY_SEPARATOR . 'GeoIP2-Connection-Type.mmdb');
                $record = $reader->connectionType($ip);
                $array['connection_type'] = $record->connectionType;
                $reader = new Reader($maxmind_db_path . DIRECTORY_SEPARATOR . 'GeoIP2-ISP.mmdb');
                $record = $reader->isp($ip);
                $array['isp'] = $record->isp;
            } catch (\Exception $ex) {

                //get from service http://api.ipapi.com
                $result = self::fetchLocationDataIpapi($ip);
                if ($result['success']) {
                    $array = $result['data'];
                } else {
                    throw $ex;
                }
            }
        } else {
            $array = array();
            $array['country'] = '';
            $array['region_name'] = '';
            $array['region_code'] = '';
            $array['city'] = '';
            $array['zip_code'] = '';
            $array['latitude'] = '';
            $array['longitude'] = '';
            $array['connection_type'] = '';
            $array['isp'] = '';
        }

        return $array;
    }

    /**
     * Get Location by IP (Ipapi)
     *
     * @param string $ip
     * @return array
     */
    private static function fetchLocationDataIpapi(string $ip): array
    {
        $result = ['success' => false];
        if (isset($ip) && !empty($ip)) {

            $iPapiKey = '702974b140212c28d0c3fa469e6fb350';
            $data = json_decode(file_get_contents('http://api.ipapi.com/api/' . $ip . '?access_key=' . $iPapiKey), true);

            if ($data) {

                $array = array();
                $array['country'] = $data['country_code'];
                $array['region_name'] = $data['region_name'];
                $array['region_code'] = $data['region_code'];
                $array['city'] = $data['city'];
                $array['zip_code'] = $data['zip'];
                $array['latitude'] = $data['latitude'];
                $array['longitude'] = $data['longitude'];
                $array['connection_type'] = '';
                $array['isp'] = '';
                $result['success'] = true;
                $result['data'] = $array;
            }
        }

        return $result;
    }
}
