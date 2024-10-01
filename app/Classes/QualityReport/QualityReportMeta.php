<?php

namespace App\Classes\QualityReport;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;

class QualityReportMeta
{

    public static $pivot_titles = [
        'TrafficEndpoint' => ['title' => 'Traffic Endpoint', 'visible' => false, 'default_selected' => false],
        'account_manager' => ['title' => 'Account Manager', 'visible' => true, 'selected' => false],
        'brokerId' => ['title' => 'Broker', 'visible' => true, 'default_selected' => false],
        'sub_publisher' => ['title' => 'Sub Channel', 'visible' => true, 'default_selected' => false],
        'CampaignId' => ['title' => 'Campaign', 'visible' => false, 'default_selected' => false],
        'creative_id' => ['title' => 'Creative', 'visible' => true, 'default_selected' => false],
        'integrationId' => ['title' => 'Integration ID', 'visible' => false, 'default_selected' => false],
        'integration' => ['title' => 'Integration', 'visible' => true, 'default_selected' => false],
        'country' => ['title' => 'Country', 'visible' => true, 'default_selected' => false],
        'region' => ['title' => 'Region', 'visible' => true, 'default_selected' => false],
        'region_code' => ['title' => 'Region Code', 'visible' => true, 'default_selected' => false],
        'city' => ['title' => 'City', 'visible' => true, 'default_selected' => false],
        'zip_code' => ['title' => 'Zip Code', 'visible' => true, 'default_selected' => false],
        'funnel_lp' => ['title' => 'Funnel', 'visible' => true, 'default_selected' => false],
        'deposit_revenue' => ['title' => 'Revenue', 'visible' => false, 'default_selected' => false, 'total' => true],
        'cost' => ['title' => 'Cost', 'visible' => false, 'default_selected' => false, 'total' => true],
        'Leads' => ['title' => 'Leads', 'visible' => false, 'default_selected' => false, 'total' => true],
        'BlockedLeads' => ['title' => 'Blocked Leads', 'visible' => false, 'default_selected' => false, 'total' => true],
        'ftd' => ['title' => "FTD", 'visible' => true, 'default_selected' => false, 'total' => true],
        'Depositors' => ['title' => "CR", 'visible' => false, 'default_selected' => false, 'total' => true],
        'status' => ['title' => "Status", 'visible' => false, 'default_selected' => false],
        'ApprovedDepositors' => ['title' => 'Approved FTD', 'visible' => false, 'default_selected' => false, 'total' => true],
        'cpl' => ['title' => 'CPL', 'visible' => false, 'default_selected' => false, 'total' => true],
        'rpl' => ['title' => 'RPL', 'visible' => false, 'default_selected' => false, 'total' => true],
        'pm' => ['title' => 'PM', 'visible' => false, 'default_selected' => false, 'total' => true],
        'pcr' => ['title' => 'PCR', 'visible' => false, 'default_selected' => false, 'total' => true],
        'cr' => ['title' => 'CR', 'name' => 'cr', 'visible' => false, 'default_selected' => false, 'total' => true],
        'email' => ['title' => 'Email', 'visible' => true, 'default_selected' => false],
        'first_name' => ['title' => 'First Name', 'visible' => false, 'default_selected' => false],
        'last_name' => ['title' => 'Last Name', 'visible' => false, 'default_selected' => false],
        'phone' => ['title' => 'Phone', 'visible' => false, 'default_selected' => false],
        'ip' => ['title' => 'IP', 'visible' => false, 'default_selected' => false],
        'broker_lead_id' => ['title' => 'Broker Lead ID', 'visible' => true, 'default_selected' => false],
        'wrong_number' => ['title' => 'Wrong Number', 'visible' => false, 'default_selected' => false, 'total' => true],
        'do_not_call' => ['title' => 'Do Not Call', 'visible' => false, 'default_selected' => false, 'total' => true],
        'new' => ['title' => 'New', 'visible' => false, 'default_selected' => false, 'total' => true],
        'calling' => ['title' => 'Calling', 'visible' => false, 'default_selected' => false, 'total' => true],
        'test' => ['title' => 'Test Leads', 'visible' => false, 'default_selected' => false, 'total' => true],
        'payment_decline' => ['title' => 'Payment Decline', 'visible' => false, 'default_selected' => false, 'total' => true],
        'callback' => ['title' => 'Callback - General', 'visible' => false, 'default_selected' => false, 'total' => true],
        'not_interested' => ['title' => 'Not Interested', 'visible' => false, 'default_selected' => false, 'total' => true],
        'no_answer' => ['title' => 'No Answer', 'visible' => false, 'default_selected' => false, 'total' => true],
        'potential' => ['title' => 'Potential', 'visible' => false, 'default_selected' => false, 'total' => true],
        'under_age' => ['title' => 'Under Age', 'visible' => false, 'default_selected' => false, 'total' => true],
        'low_quality' => ['title' => 'Low Quality', 'visible' => false, 'default_selected' => false, 'total' => true],
        'language_barrier' => ['title' => 'Language Barrier', 'visible' => false, 'default_selected' => false, 'total' => true],
        'gender' => ['title' => 'Gender', 'visible' => true, 'default_selected' => false],
        'age' => ['title' => 'Age', 'visible' => true, 'default_selected' => false],
        'hour' => ['title' => 'Hour', 'visible' => true, 'default_selected' => false],
        'day' => ['title' => 'Day Of Month', 'visible' => true, 'default_selected' => false],
        'dayofweek' => ['title' => 'Day Of Week', 'visible' => true, 'default_selected' => false],
        'month' => ['title' => 'Month', 'visible' => true, 'default_selected' => false],
        'media_account_id' => ['title' => 'Media Account ID', 'visible' => true, 'default_selected' => false],
        '_id' => ['title' => 'Lead ID', 'visible' => true, 'default_selected' => false],
        'OS' => ['title' => 'OS', 'visible' => true, 'default_selected' => false],
        'OSVersion' => ['title' => 'OS Version', 'visible' => true, 'default_selected' => false],
        'Browser' => ['title' => 'Browser', 'visible' => true, 'default_selected' => false],
        'OSBrowser' => ['title' => 'OS Browser', 'visible' => true, 'default_selected' => false],
        'browser_version' => ['title' => 'Browser Version', 'visible' => true, 'default_selected' => false],
        'DeviceType' => ['title' => 'Device Type', 'visible' => true, 'default_selected' => false],
        'd1' => ['title' => 'Dynamic1', 'visible' => true, 'selected' => false],
        'd2' => ['title' => 'Dynamic2', 'visible' => true, 'selected' => false],
        'd3' => ['title' => 'Dynamic3', 'visible' => true, 'selected' => false],
        'd4' => ['title' => 'Dynamic4', 'visible' => true, 'selected' => false],
        'd5' => ['title' => 'Dynamic5', 'visible' => true, 'selected' => false],
        'd6' => ['title' => 'Dynamic6', 'visible' => true, 'selected' => false],
        'd7' => ['title' => 'Dynamic7', 'visible' => true, 'selected' => false],
        'd8' => ['title' => 'Dynamic8', 'visible' => true, 'selected' => false],
        'd9' => ['title' => 'Dynamic9', 'visible' => true, 'selected' => false],
        'd10' => ['title' => 'Dynamic10', 'visible' => true, 'selected' => false],

        'DeviceBrand' => ['title' => 'Device Brand', 'visible' => true, 'default_selected' => false],
        'device' => ['title' => 'Device', 'visible' => true, 'default_selected' => false],
        'language' => ['title' => 'Language', 'visible' => true, 'default_selected' => false],
        'UserLanguage' => ['title' => 'User Language', 'visible' => true, 'default_selected' => false],
        'connection_type' => ['title' => 'Connection Type', 'visible' => true, 'default_selected' => false],
        'latitude' => ['title' => 'Latitude', 'visible' => true, 'default_selected' => false],
        'longitude' => ['title' => 'Longitude', 'visible' => true, 'default_selected' => false],
        'isp' => ['title' => 'ISP', 'visible' => true, 'default_selected' => false],
        'Timestamp' => ['title' => 'Timestamp', 'visible' => true, 'default_selected' => false],

        'mismatch' => ['title' => 'Mismatch', 'name' => 'mismatch', 'visible' => false, 'default_selected' => false, 'total' => false],
        'redirect' => ['title' => 'Redirect', 'name' => 'redirect', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudHighRisk' => ['title' => 'Fraud High Risk', 'name' => 'fraudHighRisk', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudMediumRisk' => ['title' => 'Fraud Medium Risk', 'name' => 'fraudMediumRisk', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudLowRisk' => ['title' => 'Fraud Low Risk', 'name' => 'fraudLowRisk', 'visible' => false, 'default_selected' => false, 'total' => false],
    ];

    public static $pivot_metrics = [

        'wrong_number' => ['title' => 'Wrong Number', 'visible' => true, 'selected' => true, 'total' => true],

        'leads' => ['title' => 'Leads', 'name' => 'Leads', 'selected' => true, 'total' => true],
        'test' => ['title' => 'Test leads', 'name' => 'test', 'allow' => true, 'selected' => true, 'total' => true],

        // 'cpl' => ['title' => 'CPL', 'visible' => false, 'selected' => true, 'total' => true],
        // 'rpl' => ['title' => 'RPL', 'visible' => false, 'selected' => true, 'total' => true],
        // 'pm' => ['title' => 'PM', 'visible' => false, 'selected' => true, 'total' => true],
        // 'pcr' => ['title' => 'PCR', 'visible' => false, 'selected' => true, 'total' => true],
        'cr' => ['title' => 'CR', 'name' => 'cr', 'visible' => true, 'selected' => true, 'total' => true],

        'low_quality' => ['title' => 'Low Quality', 'visible' => false, 'selected' => true, 'total' => true],
        'new' => ['title' => 'New', 'visible' => true, 'selected' => true, 'total' => true],
        'payment_decline' => ['title' => 'Payment Decline', 'visible' => false, 'selected' => true, 'total' => true],
        'calling' => ['title' => 'Calling', 'visible' => true, 'selected' => true, 'total' => true],

        'language_barrier' => ['title' => 'Language Barrier', 'visible' => true, 'selected' => true, 'total' => true],

        'do_not_call' => ['title' => 'Do Not Call', 'visible' => true, 'selected' => false, 'total' => true],
        'callback' => ['title' => 'Callback - General', 'visible' => true, 'selected' => true, 'total' => true],
        'not_interested' => ['title' => 'Not Interested', 'visible' => true, 'selected' => true, 'total' => true],
        'no_answer' => ['title' => 'No Answer', 'visible' => true, 'selected' => true, 'total' => true],
        'potential' => ['title' => 'Potential', 'visible' => true, 'selected' => true, 'total' => true],
        'under_age' => ['title' => 'Under Age', 'visible' => true, 'selected' => true, 'total' => true],
        'invalid' => ['title' => 'Invalid', 'visible' => true, 'selected' => true, 'total' => true],

        'mismatch' => ['title' => 'Mismatch', 'visible' => true, 'name' => 'mismatch', 'total' => true, 'selected' => false, 'aggregate' => 'avg'],
        'redirect' => ['title' => 'Redirect', 'visible' => true, 'name' => 'redirect', 'total' => true, 'selected' => false, 'aggregate' => 'avg'],
        'fraudHighRisk' => ['title' => 'Fraud High Risk', 'visible' => true, 'name' => 'fraudHighRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],
        'fraudMediumRisk' => ['title' => 'Fraud Medium Risk', 'visible' => true, 'name' => 'fraudMediumRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],
        'fraudLowRisk' => ['title' => 'Fraud Low Risk', 'visible' => true, 'name' => 'fraudLowRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],

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
        // $permissions = permissionsManagement::get_user_permissions('quality_reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('quality_reports[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('partner', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    // public static function get_campaigns_list(){
    //     $find = [];
    //     $access = false;
    //     $permissions = permissionsManagement::get_user_permissions('quality_reports');
    //     $where = [];
    //     if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
    //         $user_token = auth::get_current_user_token();
    //         $where['$or'] = [
    //             ['user_id' => $user_token],
    //             ['account_manager' => $user_token],
    //         ];
    //     } else $access = true;
    //     $mongo = new MongoDBObjects('campaigns',$where);
    //     $find = $mongo->findMany();
    //     return [$find, $access];
    // }

    public static function get_traffic_endpoint_list()
    {
        $find = [];
        $access = false;
        $where = [];

        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('quality_reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('quality_reports[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    public static function get_account_manager_list()
    {
        list($find, $access) = self::get_traffic_endpoint_list();
        $result = [];
        foreach ($find as $supply) {
            $account_manager = $supply['account_manager'] ?? null;
            if ($account_manager && !isset($result[$account_manager])) {
                $result[$account_manager] = [
                    '_id' => $account_manager,
                    'name' => auth::get_user($account_manager)['name']
                ];
            }
        }
        return [array_values($result), $access];
    }
}
