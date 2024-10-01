<?php

namespace App\Helpers;

use App\Models\Broker;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;

class GeneralHelper
{
    public static function Dump(...$values)
    {
        http_response_code(500);
        header('Access-Control-Allow-Origin: *');
        var_dump(...$values);
        // exit;
    }

    public static function PrintR(...$values)
    {
        http_response_code(500);
        header('Access-Control-Allow-Origin: *');
        print_r(...$values);
        // exit;
    }

    public static function GeDateFromTimestamp($timestamp, $format = false)
    {
        $result = null;
        $ts = (array)$timestamp;
        if (isset($ts['milliseconds'])) {
            $mil = $ts['milliseconds'];
            $result = $mil / 1000; // seconds
            if ($format) {
                $result = date($format, $result); //"Y-m-d H:i:s"
            }
        } else if (isset($ts['$date'])) {
            $date = (array)$ts['$date'];
            $result = $date['$numberLong'] / 1000;
            if ($format) {
                $result = date($format, $result); //"Y-m-d H:i:s"
            }
        }
        return $result;
    }

    public static function ToMongoDateTime($value): ?UTCDateTime
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return new UTCDateTime($value * 1000);
        }
        if (is_string($value)) {
            $time = strtotime($value);
            return $time !== false ? new UTCDateTime($time * 1000) : self::ToMongoDateTime(json_decode($value, true));
        }
        if (isset($value['$date']['$numberLong'])) {
            return new UTCDateTime($value['$date']['$numberLong']);
        }
        throw new \Exception("Failed to parse DateTime: " . json_encode($value));
    }

    public static function timeNiceDuration($durationInSeconds)
    {

        $duration = '';
        $days = floor($durationInSeconds / 86400);
        $durationInSeconds -= $days * 86400;
        $hours = floor($durationInSeconds / 3600);
        $durationInSeconds -= $hours * 3600;
        $minutes = floor($durationInSeconds / 60);
        $seconds = $durationInSeconds - $minutes * 60;

        if ($days > 0) {
            $duration .= $days . ' days';
        }
        if ($hours > 0) {
            $duration .= ' ' . $hours . ' hours';
        }
        if ($minutes > 0) {
            $duration .= ' ' . $minutes . ' minutes';
        }
        if ($seconds > 0) {
            $duration .= ' ' . $seconds . ' seconds';
        }
        return trim($duration);
    }

    public static function timeAgo($timestamp)
    {
        $datetime1 = new \DateTime("now");
        $datetime2 = date_create($timestamp);
        $diff = date_diff($datetime1, $datetime2);
        $timemsg = '';
        if ($diff->y > 0) {
            $timemsg = $diff->y . ' year' . ($diff->y > 1 ? "'s" : '');
        } else if ($diff->m > 0) {
            $timemsg = $diff->m . ' month' . ($diff->m > 1 ? "'s" : '');
        } else if ($diff->d > 0) {
            $timemsg = $diff->d . ' day' . ($diff->d > 1 ? "'s" : '');
        } else if ($diff->h > 0) {
            $timemsg = $diff->h . ' hour' . ($diff->h > 1 ? "'s" : '');
        } else if ($diff->i > 0) {
            $timemsg = $diff->i . ' minute' . ($diff->i > 1 ? "'s" : '');
        } else if ($diff->s > 0) {
            $timemsg = $diff->s . ' second' . ($diff->s > 1 ? "'s" : '');
        }

        $timemsg = $timemsg . ' ago';
        return $timemsg;
    }

    public static function get_current_user_token()
    {
        return Auth::user()->_id;
    }

    public static function get_user_ip()
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

    public static function format_size($size)
    {
        if ($size < 1024) {
            return $size . ' B';
        } else {
            $size = $size / 1024;
            $units = ['KB', 'MB', 'GB', 'TB'];
            foreach ($units as $unit) {
                if (round($size, 2) >= 1024) {
                    $size = $size / 1024;
                } else {
                    break;
                }
            }
            return round($size, 2) . ' ' . $unit;
        }
    }

    public static function get_mime_by_extension($extension)
    {
        $types = array(
            'ai'      => 'application/postscript',
            'aif'     => 'audio/x-aiff',
            'aifc'    => 'audio/x-aiff',
            'aiff'    => 'audio/x-aiff',
            'asc'     => 'text/plain',
            'atom'    => 'application/atom+xml',
            'atom'    => 'application/atom+xml',
            'au'      => 'audio/basic',
            'avi'     => 'video/x-msvideo',
            'bcpio'   => 'application/x-bcpio',
            'bin'     => 'application/octet-stream',
            'bmp'     => 'image/bmp',
            'cdf'     => 'application/x-netcdf',
            'cgm'     => 'image/cgm',
            'class'   => 'application/octet-stream',
            'cpio'    => 'application/x-cpio',
            'cpt'     => 'application/mac-compactpro',
            'csh'     => 'application/x-csh',
            'css'     => 'text/css',
            'csv'     => 'text/csv',
            'dcr'     => 'application/x-director',
            'dir'     => 'application/x-director',
            'djv'     => 'image/vnd.djvu',
            'djvu'    => 'image/vnd.djvu',
            'dll'     => 'application/octet-stream',
            'dmg'     => 'application/octet-stream',
            'dms'     => 'application/octet-stream',
            'doc'     => 'application/msword',
            'dtd'     => 'application/xml-dtd',
            'dvi'     => 'application/x-dvi',
            'dxr'     => 'application/x-director',
            'eps'     => 'application/postscript',
            'etx'     => 'text/x-setext',
            'exe'     => 'application/octet-stream',
            'ez'      => 'application/andrew-inset',
            'gif'     => 'image/gif',
            'gram'    => 'application/srgs',
            'grxml'   => 'application/srgs+xml',
            'gtar'    => 'application/x-gtar',
            'hdf'     => 'application/x-hdf',
            'hqx'     => 'application/mac-binhex40',
            'htm'     => 'text/html',
            'html'    => 'text/html',
            'ice'     => 'x-conference/x-cooltalk',
            'ico'     => 'image/x-icon',
            'ics'     => 'text/calendar',
            'ief'     => 'image/ief',
            'ifb'     => 'text/calendar',
            'iges'    => 'model/iges',
            'igs'     => 'model/iges',
            'jpe'     => 'image/jpeg',
            'jpeg'    => 'image/jpeg',
            'jpg'     => 'image/jpeg',
            'js'      => 'application/x-javascript',
            'json'    => 'application/json',
            'kar'     => 'audio/midi',
            'latex'   => 'application/x-latex',
            'lha'     => 'application/octet-stream',
            'lzh'     => 'application/octet-stream',
            'm3u'     => 'audio/x-mpegurl',
            'man'     => 'application/x-troff-man',
            'mathml'  => 'application/mathml+xml',
            'me'      => 'application/x-troff-me',
            'mesh'    => 'model/mesh',
            'mid'     => 'audio/midi',
            'midi'    => 'audio/midi',
            'mif'     => 'application/vnd.mif',
            'mov'     => 'video/quicktime',
            'movie'   => 'video/x-sgi-movie',
            'mp2'     => 'audio/mpeg',
            'mp3'     => 'audio/mpeg',
            'mpe'     => 'video/mpeg',
            'mpeg'    => 'video/mpeg',
            'mpg'     => 'video/mpeg',
            'mpga'    => 'audio/mpeg',
            'ms'      => 'application/x-troff-ms',
            'msh'     => 'model/mesh',
            'mxu'     => 'video/vnd.mpegurl',
            'nc'      => 'application/x-netcdf',
            'oda'     => 'application/oda',
            'ogg'     => 'application/ogg',
            'pbm'     => 'image/x-portable-bitmap',
            'pdb'     => 'chemical/x-pdb',
            'pdf'     => 'application/pdf',
            'pgm'     => 'image/x-portable-graymap',
            'pgn'     => 'application/x-chess-pgn',
            'png'     => 'image/png',
            'pnm'     => 'image/x-portable-anymap',
            'ppm'     => 'image/x-portable-pixmap',
            'ppt'     => 'application/vnd.ms-powerpoint',
            'ps'      => 'application/postscript',
            'qt'      => 'video/quicktime',
            'ra'      => 'audio/x-pn-realaudio',
            'ram'     => 'audio/x-pn-realaudio',
            'ras'     => 'image/x-cmu-raster',
            'rdf'     => 'application/rdf+xml',
            'rgb'     => 'image/x-rgb',
            'rm'      => 'application/vnd.rn-realmedia',
            'roff'    => 'application/x-troff',
            'rss'     => 'application/rss+xml',
            'rtf'     => 'text/rtf',
            'rtx'     => 'text/richtext',
            'sgm'     => 'text/sgml',
            'sgml'    => 'text/sgml',
            'sh'      => 'application/x-sh',
            'shar'    => 'application/x-shar',
            'silo'    => 'model/mesh',
            'sit'     => 'application/x-stuffit',
            'skd'     => 'application/x-koan',
            'skm'     => 'application/x-koan',
            'skp'     => 'application/x-koan',
            'skt'     => 'application/x-koan',
            'smi'     => 'application/smil',
            'smil'    => 'application/smil',
            'snd'     => 'audio/basic',
            'so'      => 'application/octet-stream',
            'spl'     => 'application/x-futuresplash',
            'src'     => 'application/x-wais-source',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc'  => 'application/x-sv4crc',
            'svg'     => 'image/svg+xml',
            'svgz'    => 'image/svg+xml',
            'swf'     => 'application/x-shockwave-flash',
            't'       => 'application/x-troff',
            'tar'     => 'application/x-tar',
            'tcl'     => 'application/x-tcl',
            'tex'     => 'application/x-tex',
            'texi'    => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'tif'     => 'image/tiff',
            'tiff'    => 'image/tiff',
            'tr'      => 'application/x-troff',
            'tsv'     => 'text/tab-separated-values',
            'txt'     => 'text/plain',
            'ustar'   => 'application/x-ustar',
            'vcd'     => 'application/x-cdlink',
            'vrml'    => 'model/vrml',
            'vxml'    => 'application/voicexml+xml',
            'wav'     => 'audio/x-wav',
            'wbmp'    => 'image/vnd.wap.wbmp',
            'wbxml'   => 'application/vnd.wap.wbxml',
            'wml'     => 'text/vnd.wap.wml',
            'wmlc'    => 'application/vnd.wap.wmlc',
            'wmls'    => 'text/vnd.wap.wmlscript',
            'wmlsc'   => 'application/vnd.wap.wmlscriptc',
            'wrl'     => 'model/vrml',
            'xbm'     => 'image/x-xbitmap',
            'xht'     => 'application/xhtml+xml',
            'xhtml'   => 'application/xhtml+xml',
            'xls'     => 'application/vnd.ms-excel',
            'xml'     => 'application/xml',
            'xpm'     => 'image/x-xpixmap',
            'xsl'     => 'application/xml',
            'xslt'    => 'application/xslt+xml',
            'xul'     => 'application/vnd.mozilla.xul+xml',
            'xwd'     => 'image/x-xwindowdump',
            'xyz'     => 'chemical/x-xyz',
            'zip'     => 'application/zip'
        );

        return array_key_exists($extension, $types) ? $types[$extension] : '';
    }

    public static function countries($key_lower = false)
    {

        $cache_key = 'countries_' . ($key_lower ? 'key_lower' : '');

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $file_path = storage_path('data/countries.json');
        $country_array = json_decode(file_get_contents($file_path), true);
        $ar_geo = array();
        foreach ($country_array as $geos) {
            $key = $key_lower ? strtolower($geos['code']) : $geos['code'];
            $ar_geo[$key] = $geos['name'];
        }

        Cache::put($cache_key, $ar_geo, 60 * 60 * 24 * 7); //7 days;

        return $ar_geo;
    }

    public static function languages($key_lower = false)
    {

        $cache_key = 'languages_' . ($key_lower ? 'key_lower' : '');

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $file_path = storage_path('data/languages.json');
        $language_array = json_decode(file_get_contents($file_path), true);
        $ar_geo = array();
        foreach ($language_array as $geos) {
            $key = $key_lower ? strtolower($geos['code']) : $geos['code'];
            $ar_geo[$key] = $geos['name'];
        }

        Cache::put($cache_key, $ar_geo, 60 * 60 * 24 * 7); //7 days;

        return $ar_geo;
    }

    public static function regions($country_code, $key_lower = false)
    {
        if (empty($country_code)) {
            return [];
        }

        $cache_key = 'regions_' . $country_code . '_' . ($key_lower ? 'key_lower' : '');

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $file_path = storage_path('data/regions.json');
        $regions_array = json_decode(file_get_contents($file_path), true);
        $ar_geo = array();
        foreach ($regions_array as $geos) {
            if (strtolower(trim($geos['countryShortCode'])) == strtolower(trim($country_code))) {
                foreach ($geos['regions'] as $region) {
                    $key = $key_lower ? strtolower($region['shortCode']) : $region['shortCode'];
                    $ar_geo[$key] = $region['name'];
                }
                break;
            }
        }

        Cache::put($cache_key, $ar_geo, 60 * 60 * 24 * 7); //7 days;

        return $ar_geo;
    }

    public static function broker_name($broker): string
    {
        if (!is_array($broker)) {
            $broker = $broker->toArray();
        }

        $name = $broker['token'] ?? 'Unknown';

        // if (Gate::has('custom[broker_name]') && Gate::denies('custom[broker_name]')) {
        //     return $name;
        // }

        $is_only_assigned = Gate::allows('brokers[is_only_assigned=1]');
        $current_user_id = Auth::id();
        if (
            !$is_only_assigned ||
            ($is_only_assigned &&
                ($current_user_id == ($broker['created_by'] ?? '') || $current_user_id == ($broker['account_manager'] ?? ''))
            )
        ) {
            $name = trim(CryptHelper::decrypt_broker_name($broker['partner_name'] ?? ''));
            if (!empty($broker['token'] ?? '')) {
                $name .= ' (' . $broker['token'] . ')';
            }
        }

        // $name = $broker['partner_name'] ?? 'Unknown';
        // if (!empty($broker['token'])) {
        //     $name .= ' (' . $broker['token'] . ')';
        // }
        // if (Gate::has('custom[broker_name]') && Gate::denies('custom[broker_name]')) {
        //     $name = $broker['token'] ?? '';
        // }
        return $name;
    }

    public static function broker_integration_name($broker_integration): string
    {
        if (!is_array($broker_integration)) {
            $broker_integration = $broker_integration->toArray();
        }
        $name = '*****';

        if (Gate::has('custom[broker_integration_name]') && Gate::denies('custom[broker_integration_name]')) {
            return $name;
        }

        $broker_id = $broker_integration['partnerId'] ?? '';
        if (!empty($broker_id)) {
            $broker = [];
            $is_only_assigned = Gate::allows('brokers[is_only_assigned=1]');

            if ($is_only_assigned) {
                $broker = Broker::query()->where('_id', '=', $broker_id)->get(['created_by', 'account_manager']);
                if ($broker != null) {
                    $broker = $broker->toArray();
                } else {
                    $broker = [];
                }
            }

            $current_user_id = Auth::id();
            if (
                !$is_only_assigned ||
                ($is_only_assigned &&
                    ($current_user_id == ($broker['created_by'] ?? '') || $current_user_id == ($broker['account_manager'] ?? ''))
                )
            ) {
                $name = $broker_integration['name'] ?? '';
            }
        }

        // $name = $broker_integration['name'] ?? '';

        return $name;
    }
}
