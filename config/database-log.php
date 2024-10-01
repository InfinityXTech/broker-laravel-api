<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'mongodb' => [
            'enable' => true,
            'collections' => env('DB_LOG_COLLECTIONS', [
                'leads' => ['fields' => ['deposit_disapproved', 'status', 'depositTimestamp', 'endpointDepositTimestamp', 'deposit_reject']],

                'broker_crg' => ['main_foreign_field' => 'broker'],
                'broker_billing_entities' => ['main_foreign_field' => 'broker'],
                'broker_billing_chargebacks' => ['main_foreign_field' => 'broker'],
                'broker_billing_adjustments' => ['main_foreign_field' => 'broker'],
                'broker_billing_payment_methods' => ['main_foreign_field' => 'broker'],
                'broker_billing_payment_requests' => ['main_foreign_field' => 'broker'],
                'broker_daily_cap' => ['main_foreign_field' => 'broker'],
                'broker_caps' => ['main_foreign_field' => 'broker'],

                'partner' => [],
                'TrafficEndpoints' => [],

                'broker_payouts' => ['main_foreign_field' => 'broker'],
                'endpoint_payouts' => ['main_foreign_field' => 'TrafficEndpoint'],
                'endpoint_crg' => ['main_foreign_field' => 'TrafficEndpoint'],

                'endpoint_billing_entities' => ['main_foreign_field' => 'endpoint'],
                'endpoint_billing_chargebacks' => ['main_foreign_field' => 'endpoint'],
                'endpoint_billing_adjustments' => ['main_foreign_field' => 'endpoint'],
                'endpoint_billing_payment_methods' => ['main_foreign_field' => 'endpoint'],
                'endpoint_billing_payment_requests' => ['main_foreign_field' => 'endpoint'],

                'masters_billing_entities' => ['main_foreign_field' => 'master'],
                'masters_billing_chargebacks' => ['main_foreign_field' => 'master'],
                'masters_billing_adjustments' => ['main_foreign_field' => 'master'],
                'masters_billing_payment_methods' => ['main_foreign_field' => 'master'],
                'masters_billing_payment_requests' => ['main_foreign_field' => 'master'],

                'marketing_advertisers' => [],
                'marketing_advertiser_billing_entities' => ['main_foreign_field' => 'advertiser'],
                'marketing_advertiser_billing_payment_methods' => ['main_foreign_field' => 'advertiser'],
                'marketing_advertiser_billing_payment_requests' => ['main_foreign_field' => 'advertiser'],
                'marketing_advertiser_billing_adjustments' => ['main_foreign_field' => 'advertiser'],
                'marketing_advertiser_billing_chargebacks' => ['main_foreign_field' => 'advertiser'],
                
                'marketing_affiliates' => [],
                'marketing_affiliate_billing_entities' => ['main_foreign_field' => 'affiliate'],
                'marketing_affiliate_billing_payment_methods' => ['main_foreign_field' => 'affiliate'],
                'marketing_affiliate_billing_payment_requests' => ['main_foreign_field' => 'affiliate'],
                'marketing_affiliate_billing_adjustments' => ['main_foreign_field' => 'affiliate'],
                'marketing_affiliate_billing_chargebacks' => ['main_foreign_field' => 'affiliate'],

                'marketing_campaigns' => [],
                'marketing_campaign_endpoint_allocations' => ['main_foreign_field' => 'campaign'],
                'marketing_campaign_limitation_endpoints' => ['main_foreign_field' => 'campaign'],
                'marketing_campaign_payouts' => ['main_foreign_field' => 'campaign'],
                'marketing_campaign_private_deals' => ['main_foreign_field' => 'campaign'],
                'marketing_campaign_targeting_locations' => ['main_foreign_field' => 'campaign'],

                'campaigns' => ['fields' => ['CampaignDistribution', 'countryJson', 'inherit_setup_campaign_id', 'traffic_endpoint_id', 'name', 'rules', 'status', 'waterfall']],
                'CampaignRules' => ['main_foreign_field' => 'CampaignToken', 'fields' => ['integrationId', 'DailyCap', 'GeoCountryName', 'Status', 'payout']],
            ]),
        ],

        'sqlite' => [
            'enable' => false,
            'collections' => env('DB_LOG_COLLECTIONS', [])
        ],

        'mysql' => [
            'enable' => false,
            'collections' => env('DB_LOG_COLLECTIONS', [])
        ],

        'pgsql' => [
            'enable' => false,
            'collections' => env('DB_LOG_COLLECTIONS', [])
        ],

        'sqlsrv' => [
            'enable' => false,
            'collections' => env('DB_LOG_COLLECTIONS', [])
        ],

    ]

];
