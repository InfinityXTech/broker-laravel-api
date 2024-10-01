<?php

namespace App\Classes;

use Exception;
use App\Models\User;
use App\Helpers\BucketHelper;
use App\Helpers\ClientHelper;
use App\Helpers\SystemHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Log;
use App\Classes\Performance\General;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Access
{

    private static $default_permissions = [
        'brokers',
        'traffic_endpoint',
        'masters',
        'planning',
        'gravity',
        'crm',
        'quality_reports',
        'reports',
        'click_reports',
        'billings',
        'performance',
    ];

    private static function check_permission(array &$arr, &$user)
    {
        if (!array_key_exists('only_traffic_endpoint', $arr)) {
            foreach ($arr as $kk => &$vv) {
                if (isset($vv) && is_array($vv)) {
                    if (count($vv) == 0) {
                        $vv = true;
                    } else
                    if (isset($vv['only']) || isset($vv['except'])) {
                        $access = true;
                        if (isset($vv['only']) && is_array($vv['only']) && count($vv['only']) > 0) {
                            $access = $access && in_array($user->_id, $vv['only']) || in_array($user->account_email, $vv['only']);
                        } else if (isset($vv['except']) && is_array($vv['except']) && count($vv['except']) > 0) {
                            $access = $access && (!(in_array($user->_id, $vv['except']) || in_array($user->account_email, $vv['except'])));
                        }
                        $vv = $access;
                    } else if (is_array($vv)) {
                        self::check_permission($vv, $user);
                    }
                }
            }
        }
    }

    public static function attach_features(string $clientId, &$user): void
    {
        $user->client = [
            'private_features' => [],
            'public_features' => []
        ];

        try {
            $client = ClientHelper::get_bucket_client();
            if (!empty($client)) {
                $user->client = [
                    'private_features' => $client['private_features'] ?? [],
                    'public_features' => $client['public_features'] ?? [],
                ];
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public static function attach_custom_access(&$user, bool $custom = true): void
    {
        if (!isset($user)) {
            return;
        }

        $systemId = SystemHelper::systemId();
        $clientId = ClientHelper::clientId();
        $env = config('app.env');

        self::attach_features($clientId, $user);

        // $custom_access = $custom ? config('custom-access' . ($systemId == 'crm' ? '' : '-' . $systemId)) ?? [] : [];
        if (empty($clientId)) {
            $custom_access = $custom ? config('custom-access') ?? [] : [];
        } else {
            $custom_access = $custom ? config('clients.' . $clientId . '.custom-access') ?? [] : [];
        }

        // GeneralHelper::PrintR(['clients.' . $clientId . '.custom-access']);die();
        // $custom_access = $custom ? config('custom-access') ?? [] : [];
        // if (empty($clientId)) {
        //     $custom_access = $custom ? config('custom-access' . ($systemId == 'crm' ? '' : '-' . $systemId)) ?? [] : [];
        // }
        if ($systemId != 'crm') {
            if (empty($clientId)) {
                $ext_custom_access = $custom ? config('custom-access-' . $systemId) ?? [] : [];
            } else {
                $ext_custom_access = $custom ? config('clients.' . $clientId . '.custom-access-' . $systemId) ?? [] : [];
            }
            foreach ($ext_custom_access as $k => $v) {
                if ($k == '*') {
                    foreach ($custom_access[$k] as $_k => $_v) {
                        $custom_access[$k][$_k] = $_v;
                    }
                } else {
                    $custom_access[$k] = $v;
                }
            }
        }

        $permissions = [];

        $field = 'permissions'; // . ($systemId == 'crm' ? '' : '_' . $systemId);
        $_permissions = $user->{$field} ?? [];

        // check default
        foreach (self::$default_permissions as $permission_name) {
            if (!array_key_exists($permission_name, $_permissions)) {
                $_permissions[$permission_name] = [
                    'active' => false,
                    'access' => ''
                ];
            }
        }

        if ($custom) {
            $_no_user_access = array_filter($custom_access ?? [], function ($v, $k) use ($_permissions) {
                if ($k != '*' && !array_key_exists($k, $_permissions)) {
                    return true;
                }
            }, ARRAY_FILTER_USE_BOTH);
            foreach ($_no_user_access as $k => $vv) {
                $permissions[$k] ??= [];
                foreach ($vv as $s => $d) {
                    if (strpos($s, '__') !== 0) {
                        $permissions[$k][$s] = $d;
                    }
                }
            }
        }

        foreach ($_permissions as $permission_name => $user_permissions) {

            $user_custom_access = ($custom_access[$permission_name] ?? []); // + ($custom_access['*'] ?? []);
            foreach ($user_custom_access as $key => $v) {
                if (strpos($key, '__') !== 0) {
                    $permissions[$permission_name][$key] = $v;
                }
            }

            $permissions[$permission_name] = ['custom' => ($user_custom_access ?? [])] + ($user_permissions ?? []);
            if (!$custom) {
                if (isset($permissions[$permission_name]['custom'])) {
                    unset($permissions[$permission_name]['custom']);
                }
            }

            $permissions['*'] = [];
            foreach (($custom_access['*'] ?? []) as $key => $val) {
                if (strpos($key, '__') !== 0) {
                    $permissions['*'][$key] = $val;
                }
            }
        }

        foreach ($permissions as $permission_name => &$permission_value) {
            if ($permission_name == '*') {
                self::check_permission($permission_value, $user);
            }
            foreach ($permission_value as $key => &$v) {
                switch ($key) {
                    case 'is_only_assigned': {
                            $v = boolval($v);
                            break;
                        }
                    case 'active': {
                            $v = boolval($v);
                            if (!$v) {
                                $permissions[$permission_name]['access'] = null;
                            }
                            break;
                        }
                    case 'access': {
                            break;
                        }
                    default: {
                            if (is_array($v)) {
                                self::check_permission($v, $user);
                            }
                            break;
                        }
                }

                // if (
                //     $key == 'is_scrub_permission' &&
                //     $clientId != '633c07530b1a55629a3b0a1d' &&  // roibees
                //     $env != 'local'
                // ) {
                //     $v = false;
                // }

                // if ($key == 'is_only_assigned') {
                //     $v = boolval($v);
                // } else
                // if ($key == 'active') {
                //     $v = boolval($v);
                //     if (!$v) {
                //         $permissions[$permission_name]['access'] = null;
                //     }
                // }
                // if ($key == 'custom') {
                //     self::check_permission($v, $user);
                // }
            }
        }

        $user->permissions = $permissions;
    }

    public static function get_custom_access($user = null): array
    {
        if ($user == null) {
            $user = Auth::user();
        }
        if ($user == null) {
            $user_id = Auth::id();
            if (!empty($user_id)) {
                $user = User::query()->find($user_id, ['_id', 'account_email'])->get();
            }
        }
        if ($user) {
            self::attach_custom_access($user);
            return $user->permissions;
        }
        return [];
    }
}
