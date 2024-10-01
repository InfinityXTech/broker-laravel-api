<?php

namespace App\Classes\MarketingReport;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;

class MarketingReportMeta
{

    public static $pivot_titles = [
        'AdvertiserId' => ['title' => 'Advertiser', 'visible' => true, 'selected' => false],
        'account_manager' => ['title' => 'Account Manager', 'visible' => true, 'selected' => false],
        'AffiliateId' => ['title' => 'Affiliate', 'visible' => true, 'selected' => true],
        'CampaignId' => ['title' => 'Campaign', 'visible' => true, 'selected' => true],
        'EventType' => ['title' => 'Event Type', 'visible' => true, 'selected' => false],
        'EventTypeSchema' => ['title' => 'Event Schema', 'visible' => true, 'selected' => false],
        'ClickID' => ['title' => 'ClickID', 'visible' => true, 'selected' => false],
        'GeoCountryName' => ['title' => 'Country', 'visible' => true, 'selected' => false],
        'GeoRegionName' => ['title' => 'Region', 'visible' => true, 'selected' => false],
        // 'region_code' => ['title' => 'Region Code', 'visible' => true, 'selected' => false],
        'GeoCityName' => ['title' => 'City', 'visible' => true, 'selected' => false],
        // 'zip_code' => ['title' => 'Zip Code', 'visible' => true, 'selected' => false],
        'revenue' => ['title' => 'Revenue', 'visible' => false, 'selected' => false, 'total' => true],
        'cost' => ['title' => 'Cost', 'visible' => false, 'selected' => false, 'total' => true],
        // 'deposit_revenue' => ['title' => 'Revenue', 'visible' => false, 'selected' => false, 'total' => true],
        'profit' => ['title' => 'Profit', 'visible' => false, 'selected' => false, 'total' => true],
        'Leads' => ['title' => 'Leads', 'visible' => false, 'selected' => false, 'total' => true],
        'blocked_leads' => ['title' => 'Blocked Clicks', 'visible' => false, 'selected' => false, 'total' => true],
        'conversion' => ['title' => "Conversions", 'visible' => false, 'selected' => false, 'total' => true],
        'ApprovedConversions' => ['title' => 'Approved Conversions', 'visible' => false, 'selected' => false,'total' => true],
        // 'test_FTD' => ['title' => "Test FTD", 'visible' => false, 'selected' => false, 'total' => true],
        // 'fake_FTD' => ['title' => "Fake FTD", 'visible' => false, 'selected' => false, 'total' => true],	   
        // 'status' => ['title' => "Status", 'visible' => true, 'selected' => false],
        // 'broker_status' => ['title' => "Broker Status", 'visible' => true, 'selected' => false],
        // 'ApprovedDepositors' => ['title' => 'Approved FTD', 'visible' => false, 'selected' => false, 'total' => true],
        
        'cpl' => ['title' => 'CPL', 'visible' => false, 'selected' => false],
        'rpl' => ['title' => 'RPL', 'visible' => false, 'selected' => false],
        'pm' => ['title' => 'PM', 'visible' => false, 'selected' => false, 'total' => true, 'total_formula' => true],
        // 'pcr' => ['title' => 'PCR', 'visible' => false, 'selected' => false],
        'cr' => ['title' => 'CR', 'visible' => false, 'selected' => false, 'total' => true, 'total_formula' => true],
        
        // 'email' => ['title' => 'Email', 'visible' => true, 'selected' => false],
        // 'first_name' => ['title' => 'First Name', 'visible' => true, 'selected' => false, 'allow' => false],
        // 'last_name' => ['title' => 'Last Name', 'visible' => true, 'selected' => false, 'allow' => false],
        // 'phone' => ['title' => 'Phone', 'visible' => true, 'selected' => false, 'allow' => false],
        'IP' => ['title' => 'IP', 'visible' => true, 'selected' => false, 'allow' => false],
        // 'broker_lead_id' => ['title' => 'Broker Lead ID', 'visible' => true, 'selected' => false],
        // 'gender' => ['title' => 'Gender', 'visible' => true, 'selected' => false],
        // 'age' => ['title' => 'Age', 'visible' => true, 'selected' => false],
        // 'hour' => ['title' => 'Hour', 'visible' => true, 'selected' => false],
        // 'day' => ['title' => 'Day Of Month', 'visible' => true, 'selected' => false],
        // 'dayofweek' => ['title' => 'Day Of Week', 'visible' => true, 'selected' => false],
        // 'month' => ['title' => 'Month', 'visible' => true, 'selected' => false],
        // 'media_account_id' => ['title' => 'Media Account ID', 'visible' => true, 'selected' => false],
        '_id' => ['title' => 'ID', 'visible' => true, 'selected' => false],
        
        'DeviceOs' => ['title' => 'OS', 'visible' => true, 'selected' => false],
        'OSVersion' => ['title' => 'OS Version', 'visible' => true, 'selected' => false],

        'Browser' => ['title' => 'Browser', 'visible' => true, 'selected' => false],
        'OSBrowser' => ['title' => 'OS Browser', 'visible' => true, 'selected' => false],
        // 'browser_version' => ['title' => 'Browser Version', 'visible' => true, 'selected' => false],
        'DeviceType' => ['title' => 'Device Type', 'visible' => true, 'selected' => false],
        'DeviceBrand' => ['title' => 'Device Brand', 'visible' => true, 'selected' => false],
        // 'device' => ['title' => 'Device', 'visible' => true, 'selected' => false],

        'Dynamic1' => ['title' => 'Dynamic1', 'visible' => true, 'selected' => false],
        'Dynamic2' => ['title' => 'Dynamic2', 'visible' => true, 'selected' => false],
        'Dynamic3' => ['title' => 'Dynamic3', 'visible' => true, 'selected' => false],
        'Dynamic4' => ['title' => 'Dynamic4', 'visible' => true, 'selected' => false],
        'Dynamic5' => ['title' => 'Dynamic5', 'visible' => true, 'selected' => false],
        'Dynamic6' => ['title' => 'Dynamic6', 'visible' => true, 'selected' => false],
        // 'Dynamic7' => ['title' => 'Dynamic7', 'visible' => true, 'selected' => false],
        // 'Dynamic8' => ['title' => 'Dynamic8', 'visible' => true, 'selected' => false],
        // 'Dynamic9' => ['title' => 'Dynamic9', 'visible' => true, 'selected' => false],
        // 'Dynamic10' => ['title' => 'Dynamic10', 'visible' => true, 'selected' => false],

        // 'language' => ['title' => 'Language', 'visible' => true, 'selected' => false],
        'UserLanguage' => ['title' => 'User Language', 'visible' => true, 'selected' => false],

        // 'connection_type' => ['title' => 'Connection Type', 'visible' => true, 'selected' => false],
        'GeoLatitude' => ['title' => 'Latitude', 'visible' => true, 'selected' => false],
        'GeoLongitude' => ['title' => 'Longitude', 'visible' => true, 'selected' => false],
        // 'isp' => ['title' => 'ISP', 'visible' => true, 'selected' => false],
        'EventTimeStamp' => ['title' => 'Timestamp', 'visible' => true, 'selected' => false],
        // 'MasterAffiliate' => ['title' => 'Master Affiliate', 'visible' => true, 'selected' => false],
        // 'master_affiliate_payout' => ['title' => 'Master Affiliate Cost', 'visible' => false, 'selected' => false, 'total' => true],
        // 'master_brand' => ['title' => 'Master Brand', 'visible' => true, 'selected' => false],
        // 'master_brand_payout' => ['title' => 'Master Brand Cost', 'visible' => false, 'selected' => false, 'total' => true],
        // 'affiliate_cost' => ['title' => 'Affiliate Cost', 'visible' => false, 'selected' => false, 'total' => true],

        // 'test_lead' => ['title' => 'Test leads', 'visible' => false, 'allow' => false, 'selected' => false, 'total' => true],
				
    ];

    public static $pivot_metrics = [
        'revenue' => ['title' => 'Revenue', 'name' => 'revenue', 'selected' => true, 'total' => true],
        'cost' => ['title' => 'Cost', 'name' => 'cost', 'selected' => true, 'total' => true],
        'profit' => ['title' => 'Profit', 'name' => 'profit', 'selected' => true, 'total' => true],
        'leads' => ['title' => 'Leads', 'name' => 'Leads', 'selected' => true, 'total' => true],
        'conversion' => ['title' => 'Conversions', 'name' => 'Conversions', 'selected' => true, 'total' => true],
		'approved_conversion' => ['title' => 'Approved Conversions', 'name' => 'ApprovedConversions', 'selected' => true, 'total' => true],
        // 'test_FTD' => ['title' => 'Test FTD', 'name' => 'test_FTD', 'selected' => true, 'total' => true],
        // 'fake_FTD' => ['title' => 'Fake FTD', 'name' => 'fake_FTD', 'selected' => true, 'total' => true],
        // 'approved_ftds' => ['title' => 'Approved FTD\'s', 'name' => 'ApprovedDepositors', 'selected' => false, 'total' => true],
        'cr' => ['title' => 'CR', 'name' => 'cr', 'selected' => true, 'total' => true, 'aggregate' => 'avg'],
        // 'p_cr' => ['title' => 'P*CR', 'name' => 'p_cr', 'selected' => false, 'total' => true],
        'cpl' => ['title' => 'CPL', 'name' => 'cpl', 'selected' => false, 'total' => true],
        // 'profit_margin' => ['title' => 'Profit Margin', 'name' => 'pm', 'selected' => true, 'total' => true],
        'avg_rpl' => ['title' => 'Avg RPL', 'name' => 'rpl', 'selected' => false, 'total' => true],
        'blocked_leads' => ['title' => 'Blocked Clicks', 'name' => 'blocked_leads', 'selected' => true, 'total' => true],
        // 'affiliate_cost' => ['title' => 'Affiliate Cost', 'name' => 'affiliate_cost', 'selected' => false, 'total' => true],
        // 'master_affiliate_payout' => ['title' => 'Master Affiliate Cost', 'name' => 'master_affiliate_payout', 'selected' => false, 'total' => true],
        // 'master_brand_payout' => ['title' => 'Master Brand Cost', 'name' => 'master_brand_payout', 'selected' => false, 'total' => true],

        // 'test_lead' => ['title' => 'Test leads', 'name' => 'test_lead', 'allow' => false, 'selected' => true, 'total' => true],
        /*'ftd_revenue' => ['title' => 'FTD revenue', 'name' => 'ftd_revenue', 'allow' => false, 'selected' => true, 'total' => true],*/
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
        $user = Auth()->user(); // auth::get_current_user_data();
        $custom_allow = function ($key, $pivot) use ($user) {
            // TODO: Access
            // if (isset($pivot['allow']) && $pivot['allow'] === false) {
            //     $permission = customUserAccess::get_permission('pivots/' . $key);
            //     return ($permission === true);
            // }
            return true;
        };

        $array = array();
        foreach (MarketingReportMeta::$pivot_titles as $pivot_key => $pivot) {
            // if ($custom_allow($pivot_key, $pivot)) {
            {
                $array[$pivot_key] = $pivot['title'];
            }
        }

        return $array;
    }

    // public static function get_brokers_list()
    // {
    //     $find = [];
    //     $access = false;
    //     $where = ['partner_type' => '1'];

    //     // TODO: Access
    //     // $permissions = permissionsManagement::get_user_permissions('reports');
    //     // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
    //     if (Gate::allows('reports[is_only_assigned=1]')) {
    //         $user_token = Auth::id();
    //         $where['$or'] = [
    //             ['user_id' => $user_token],
    //             ['account_manager' => $user_token],
    //         ];
    //     } else {
    //         $access = true;
    //     }
    //     $mongo = new MongoDBObjects('partner', $where);
    //     $find = $mongo->findMany();
    //     return [$find, $access];
    // }

    // public static function get_campaigns_list()
    // {
    //     $find = [];
    //     $access = false;
    //     $permissions = permissionsManagement::get_user_permissions('reports');
    //     $where = [];
    //     if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
    //         $user_token = auth::get_current_user_token();
    //         $where['$or'] = [
    //             ['user_id' => $user_token],
    //             ['account_manager' => $user_token],
    //         ];
    //     } else $access = true;
    //     $mongo = new MongoDBObjects('campaigns', $where);
    //     $find = $mongo->findMany();
    //     return [$find, $access];
    // }

    // public static function get_traffic_endpoint_list()
    // {
    //     $find = [];
    //     $access = false;
    //     $where = [];

    //     // TODO: access
    //     // $permissions = permissionsManagement::get_user_permissions('reports');
    //     // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
    //     if (Gate::allows('marketing_reports[is_only_assigned=1]')) {
    //         $user_token = Auth::id();
    //         $where['$or'] = [
    //             ['user_id' => $user_token],
    //             ['account_manager' => $user_token],
    //         ];
    //     } else {
    //         $access = true;
    //     }
    //     $mongo = new MongoDBObjects('TrafficEndpoints', $where);
    //     $find = $mongo->findMany();
    //     return [$find, $access];
    // }

    // public static function get_account_manager_list()
    // {
    //     list($find, $access) = self::get_traffic_endpoint_list();
    //     $result = [];
    //     foreach ($find as $supply) {
    //         $account_manager = $supply['account_manager'] ?? null;
    //         if ($account_manager && !isset($result[$account_manager])) {
    //             $result[$account_manager] = [
    //                 '_id' => $account_manager,
    //                 'name' => auth::get_user($account_manager)['name']
    //             ];
    //         }
    //     }
    //     return [array_values($result), $access];
    // }
}
