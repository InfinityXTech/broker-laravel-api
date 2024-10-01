<?php
return [

    // default
    'key' => env('CRYPT_KEY', ''),
    'iv' => env('CRYPT_IV', ''),

    // client manager
    'cm_key' => env('CM_CRYPT_KEY', ''),
    'cm_iv' => env('CM_CRYPT_IV', ''),

    'database' => [
        'enable' => env('DB_DATABASE_CRYPT', false),
        'rename_meta' => env('DB_DATABASE_RENAME_META', false),
        'scheme' => [

            // m1
            'users' => include_once __DIR__ . '/crypt-schemas/users.php',
            // m2
            'cm_users' => include_once __DIR__ . '/crypt-schemas/cm_users.php',
            // m3
            'clients' => include_once __DIR__ . '/crypt-schemas/clients.php',

            // m4 - broker
            'partner' => include_once __DIR__ . '/crypt-schemas/partner.php',
            // m5
            'broker_billing_adjustments' => include_once __DIR__ . '/crypt-schemas/broker_billing_adjustments.php',
            // m6
            'broker_billing_chargebacks' => include_once __DIR__ . '/crypt-schemas/broker_billing_chargebacks.php',
            // m7
            'broker_billing_entities' => include_once __DIR__ . '/crypt-schemas/broker_billing_entities.php',
            // m8
            'broker_billing_payment_methods' => include_once __DIR__ . '/crypt-schemas/broker_billing_payment_methods.php',
            // m9
            'broker_billing_payment_requests' => include_once __DIR__ . '/crypt-schemas/broker_billing_payment_requests.php',
            // m10
            'broker_caps' => include_once __DIR__ . '/crypt-schemas/broker_caps.php',
            // m11
            'broker_caps_log' => include_once __DIR__ . '/crypt-schemas/broker_caps_log.php',
            // m12
            'broker_crg' => include_once __DIR__ . '/crypt-schemas/broker_crg.php',
            // m13
            'broker_crg_history' => include_once __DIR__ . '/crypt-schemas/broker_crg_history.php',
            // m14
            'broker_daily_cap' => include_once __DIR__ . '/crypt-schemas/broker_daily_cap.php',
            // m15
            'broker_integrations' => include_once __DIR__ . '/crypt-schemas/broker_integrations.php',
            // m16
            'broker_payments' => include_once __DIR__ . '/crypt-schemas/broker_payments.php',
            // m17
            'broker_payouts' => include_once __DIR__ . '/crypt-schemas/broker_payouts.php',
            // m18
            'broker_payouts_log' => include_once __DIR__ . '/crypt-schemas/broker_payouts_log.php',
            // m19
            'broker_performance_statuses' => include_once __DIR__ . '/crypt-schemas/broker_performance_statuses.php',
            // m20
            'broker_requests_stats' => include_once __DIR__ . '/crypt-schemas/broker_requests_stats.php',
            // m21
            'broker_statuses' => include_once __DIR__ . '/crypt-schemas/broker_statuses.php',

            // m22
            'TrafficEndpoints' => include_once __DIR__ . '/crypt-schemas/TrafficEndpoints.php',
            // m23
            'endpoint_billing_adjustments' => include_once __DIR__ . '/crypt-schemas/endpoint_billing_adjustments.php',
            // m24
            'endpoint_billing_chargebacks' => include_once __DIR__ . '/crypt-schemas/endpoint_billing_chargebacks.php',
            // m25
            'endpoint_billing_entities' => include_once __DIR__ . '/crypt-schemas/endpoint_billing_entities.php',
            // m26
            'endpoint_billing_payment_methods' => include_once __DIR__ . '/crypt-schemas/endpoint_billing_payment_methods.php',
            // m27
            'endpoint_billing_payment_requests' => include_once __DIR__ . '/crypt-schemas/endpoint_billing_payment_requests.php',
            // m28
            'endpoint_crg' => include_once __DIR__ . '/crypt-schemas/endpoint_crg.php',
            // m29
            'endpoint_crg_history' => include_once __DIR__ . '/crypt-schemas/endpoint_crg_history.php',
            // m30
            'endpoint_payments' => include_once __DIR__ . '/crypt-schemas/endpoint_payments.php',
            // m31
            'endpoint_payouts' => include_once __DIR__ . '/crypt-schemas/endpoint_payouts.php',
            // m32
            'endpoint_payouts_log' => include_once __DIR__ . '/crypt-schemas/endpoint_payouts_log.php',
            // m33
            'endpoint_scrub' => include_once __DIR__ . '/crypt-schemas/endpoint_scrub.php',
            // m34
            'endpoint_sub_publisher_tokens' => include_once __DIR__ . '/crypt-schemas/endpoint_sub_publisher_tokens.php',

            // m35
            'Masters' => include_once __DIR__ . '/crypt-schemas/Masters.php',
            // m36
            'Master_payments' => include_once __DIR__ . '/crypt-schemas/Master_payments.php',
            // m37
            'Master_payouts' => include_once __DIR__ . '/crypt-schemas/Master_payouts.php',
            // m38
            'masters_billing_adjustments' => include_once __DIR__ . '/crypt-schemas/masters_billing_adjustments.php',
            // m39
            'masters_billing_payment_methods' => include_once __DIR__ . '/crypt-schemas/masters_billing_payment_methods.php',

            // m40
            'integration' => include_once __DIR__ . '/crypt-schemas/integration.php',
            // m41
            'integration_comments' => include_once __DIR__ . '/crypt-schemas/integration_comments.php',
            // m42
            'Integrations' => include_once __DIR__ . '/crypt-schemas/Integrations.php',

            // m43
            'billing_payment_companies' => include_once __DIR__ . '/crypt-schemas/billing_payment_companies.php',
            // m44
            'billing_payment_methods' => include_once __DIR__ . '/crypt-schemas/billing_payment_methods.php',
            // m45
            'billings_log' => include_once __DIR__ . '/crypt-schemas/billings_log.php',

            // m46
            'marketing_advertiser_post_events' => include_once __DIR__ . '/crypt-schemas/marketing_advertiser_post_events.php',
            // m47
            'marketing_advertisers' => include_once __DIR__ . '/crypt-schemas/marketing_advertisers.php',
            // m48
            'marketing_affiliates' => include_once __DIR__ . '/crypt-schemas/marketing_affiliates.php',
            // m49
            'marketing_billings_log' => include_once __DIR__ . '/crypt-schemas/marketing_billings_log.php',
            // m50
            'marketing_campaign_payouts' => include_once __DIR__ . '/crypt-schemas/marketing_campaign_payouts.php',
            // m51
            'marketing_campaign_private_deals' => include_once __DIR__ . '/crypt-schemas/marketing_campaign_private_deals.php',
            // m52
            'marketing_campaigns' => include_once __DIR__ . '/crypt-schemas/marketing_campaigns.php',

            // m53
            'CampaignRules' => include_once __DIR__ . '/crypt-schemas/CampaignRules.php',
            // m54
            'campaigns' => include_once __DIR__ . '/crypt-schemas/campaigns.php',

            // m55
            'leads' => include_once __DIR__ . '/crypt-schemas/leads.php',
            // m56
            'logs_serving' => include_once __DIR__ . '/crypt-schemas/logs_serving.php',
            // m57
            'lead_requests' => include_once __DIR__ . '/crypt-schemas/lead_requests.php',
            // m58
            'invalid_leads' => include_once __DIR__ . '/crypt-schemas/invalid_leads.php',

            // m59
            'mleads' => include_once __DIR__ . '/crypt-schemas/mleads.php',
            // m60
            'mleads_event' => include_once __DIR__ . '/crypt-schemas/mleads_event.php',

            // m61
            'history' => include_once __DIR__ . '/crypt-schemas/history.php',
            // m62
            'logs' => include_once __DIR__ . '/crypt-schemas/logs.php',

            // m63
            'invoices' => include_once __DIR__ . '/crypt-schemas/invoices.php',

            // m64
            'offers' => include_once __DIR__ . '/crypt-schemas/offers.php',
            // m65
            'traffic_analysis' => include_once __DIR__ . '/crypt-schemas/traffic_analysis.php',

            // m66
            'storage' => include_once __DIR__ . '/crypt-schemas/storage.php',
            // m67
            'Tasks' => include_once __DIR__ . '/crypt-schemas/Tasks.php',

            // m68
            'metrics' => include_once __DIR__ . '/crypt-schemas/metrics.php',
            // m69
            'notifications' => include_once __DIR__ . '/crypt-schemas/notifications.php',
            // m70
            'PlatformAccounts' => include_once __DIR__ . '/crypt-schemas/PlatformAccounts.php',
            // m71
            'report_api_log' => include_once __DIR__ . '/crypt-schemas/report_api_log.php',
            // m72
            'settings' => include_once __DIR__ . '/crypt-schemas/settings.php',
            // m73
            'stats' => include_once __DIR__ . '/crypt-schemas/stats.php',
            // m74
            'userDataSet' => include_once __DIR__ . '/crypt-schemas/userDataSet.php',
            // m75
            'whitelisted_ip' => include_once __DIR__ . '/crypt-schemas/whitelisted_ip.php',
            // m76
            'endpoint_dynamic_integration_ids' => include_once __DIR__ . '/crypt-schemas/endpoint_dynamic_integration_ids.php',
            // m77
            'tag_management' => include_once __DIR__ . '/crypt-schemas/tag_management.php',
        ]
    ]
];
