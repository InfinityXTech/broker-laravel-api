<?php

namespace App\Classes\ClickReport;

use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Auth;

class ClickReportMeta
{

    public static $pivot_titles = [
        'TrafficEndpoint' => ['title' => 'Traffic Endpoint', 'visible' => true, 'selected' => true],
        'funnel_lp' => ['title' => 'Funnel', 'visible' => true, 'selected' => false],
        'sub_publisher' => ['title' => 'Sub Publisher', 'visible' => true, 'selected' => false],
        'real_country' => ['title' => 'Country', 'visible' => true, 'selected' => false],
        'city' => ['title' => 'City', 'visible' => true, 'selected' => false],
        'connection_type' => ['title' => 'Connection Type', 'visible' => true, 'selected' => false],
        'dayofweek' => ['title' => 'Day Of Week', 'visible' => true, 'selected' => false],
        'day' => ['title' => 'Day Of Month', 'visible' => true, 'selected' => false],
        'hour' => ['title' => 'Hour', 'visible' => true, 'selected' => false],
        'minute' => ['title' => 'Minute', 'visible' => true, 'selected' => false],
        'isp' => ['title' => 'ISP', 'visible' => true, 'selected' => false],
        'DeviceBrand' => ['title' => 'Device Brand', 'visible' => true, 'selected' => false],
        'UserLanguage' => ['title' => 'User Language', 'visible' => true, 'selected' => false],
        'OS' => ['title' => 'OS', 'visible' => true, 'selected' => false],
        'OSVersion' => ['title' => 'OS Version', 'visible' => true, 'selected' => false],
        'Browser' => ['title' => 'Browser', 'visible' => true, 'selected' => false],
        'OSBrowser' => ['title' => 'OS Browser', 'visible' => true, 'selected' => false],
        'DeviceType' => ['title' => 'Device Type', 'visible' => true, 'selected' => false],
        'p1' => ['title' => 'Dynamic1', 'visible' => true, 'selected' => false],
        'p2' => ['title' => 'Dynamic2', 'visible' => true, 'selected' => false],
        'p3' => ['title' => 'Dynamic3', 'visible' => true, 'selected' => false],
        'p4' => ['title' => 'Dynamic4', 'visible' => true, 'selected' => false],
        'p5' => ['title' => 'Dynamic5', 'visible' => true, 'selected' => false],
        'p6' => ['title' => 'Dynamic6', 'visible' => true, 'selected' => false],
        'p7' => ['title' => 'Dynamic7', 'visible' => true, 'selected' => false],
        'p8' => ['title' => 'Dynamic8', 'visible' => true, 'selected' => false],
        'p9' => ['title' => 'Dynamic9', 'visible' => true, 'selected' => false],
        'p10' => ['title' => 'Dynamic10', 'visible' => true, 'selected' => false],
        'publisher_click' => ['title' => 'Сlicktoken', 'visible' => true, 'selected' => false],
    ];

    public static $pivot_metrics = [
        'visitors' => ['title' => 'Visitors', 'selected' => true],
        'from_pre_lander' => ['title' => 'FromPreLander', 'selected' => true],
        'leads' => ['title' => 'Leads', 'selected' => true],
        'ftd' => ['title' => 'FTD', 'selected' => true],
        'cffp' => ['title' => 'CFFP', 'selected' => true],
        'rcr' => ['title' => 'RCR', 'selected' => true],
        'pvp' => ['title' => 'PVP', 'selected' => true],
    ];

    public function __construct()
    {
    }

    public function render_with_permission($is_deny, $value, $mask = '*****')
    {
        return ($is_deny ? $mask : $value);
    }

    public static function get_titles()
    {
        $array = array();
        foreach (self::$pivot_titles as $pivot_key => $pivot) {
            $array[$pivot_key] = $pivot['title'];
        }
        return $array;
    }

    public static function get_brokers_list()
    {
        $find = [];
        $access = false;
        $where = ['partner_type' => '1'];
        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('click_reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('click_reports[is_only_assigned=1]')) {
            $where['user_id'] =  Auth::id(); //$_SESSION['user_token'];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('partner', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    public static function get_campaigns_list()
    {
        $find = [];
        $access = false;
        $where = [];
        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('click_reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('click_reports[is_only_assigned=1]')) {
            $where['user_id'] = Auth::id(); //$_SESSION['user_token'];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('campaigns', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    public static function get_traffic_endpoint_list()
    {
        $find = [];
        $access = false;
        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('click_reports');
        $where = [];
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('click_reports[is_only_assigned=1]')) {
            $where['user_id'] = Auth::id(); //$_SESSION['user_token'];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }
}
