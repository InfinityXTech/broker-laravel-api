<?php

//roi bees  DEV

use Illuminate\Support\Str;

return [

    '*' => [
        'deposit_disapproved' => ['only' => ['lyudmila@markertech.ai', 'lena@markertech.ai', 'qa22@markertech.ai']],//не показывать шейв некоторым людам
        'CRG' => [],//логи в лидах//'only' => ['qa@markertech.ai', 'alex@markertech.ai','andrey@markertech.ai','finance@markertech.ai', 'dima@markertech.ai', 'vasilij@markertech.ai', 'bogdan@markertech.ai']
        'broker_name' => ['except' => ['qa2@markertech.ai']],
        'broker_integration_name' => ['except' => ['qa2@markertech.ai']],
        'send_test_lead' => [],
        'marketing_menu' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai', 'alex@markertech.ai']],
        'show_crg_deal_id' => ['only' => ['qa@markertech.ai','alex@markertech.ai','vasilij@markertech.ai']],
    ],

    'billing' => [
        '__visible__' => false,
        'adjustment_bi' => ['only_traffic_endpoint' => ['619bd24159752e50c2363c12', '6128ac2625987538c9412372', 
														'6292ff428a0bec36264338b2','6053916bb7ca031263482b42']],
    ],

    'reports' => [
        'adjustment' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai', 'alex@markertech.ai']],
        'account_manager' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai', 'alex@markertech.ai']],

        'pivots' => [

            'first_name' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai']],
            'last_name' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai']],
            'phone' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai']],
            'ip' => ['only' => ['mike@markertech.ai', 'qa@markertech.ai']],
			'hit_the_redirect' => [],//'only' => ['alex@markertech.ai', 'jacob@markertech.ai', 'dima@markertech.ai', 'vasilij@markertech.ai', 'bogdan@markertech.ai']
			
			'crg_percentage_id' => [],//'only' => ['alex@markertech.ai','vasilij@markertech.ai']
			'broker_crg_percentage_id' => [],//'only' => ['alex@markertech.ai', 'vasilij@markertech.ai']
			
			'broker_status' => [], 
            
			/*хочу понять нужно ли оно вообще
			'crg_leads' => ['only' => ['alex@markertech.ai']],
            'broker_crg_leads' => ['only' => ['alex@markertech.ai']],
            'test_lead' => ['only' => [ 'alex@markertech.ai']],
            'crg_already_paid_ftd' => ['only' => ['alex@markertech.ai']],
            'broker_crg_already_paid_ftd' => ['only' => ['alex@markertech.ai']],*/
			
			'depositTimestamp' => [],//'only' => ['alex@markertech.ai']
			'endpointDepositTimestamp' => [],//'only' => ['alex@markertech.ai']
			
        ],

        'metrics' => [
            'crg_leads' => [],//'only' => ['qa@markertech.ai', 'finance@markertech.ai', 'jacob@markertech.ai', 'alex@markertech.ai', 'dima@markertech.ai', 'vasilij@markertech.ai','bogdan@markertech.ai','alkis@markertech.ai','keti@markertech.ai','shir@markertech.ai','ohad@markertech.ai','vlad@markertech.ai','joseph@markertech.ai']
            'broker_crg_leads' => [],
			'cpl_leads' => [],
            'broker_cpl_leads' => [],
            'crg_already_paid_ftd' => [],
            'broker_crg_already_paid_ftd' => [],
            'test_lead' => [],
            'approved_ftds' => ['except' => ['lyudmila.b@markertech.ai']], //всем кроме одного!!!!!!!
        ]
    ],

    'quality_report' => [
        'disable_test_lead' => ['only' => ['maria@markertech.ai']],//в отчете для нее все % не включали тестовые лиды
    ],

    'brokers' => [
        'billing_entities' => ['only' => ['qa@markertech.ai']],//создание  контактов брокера так же могут все финасисты
        'download_crg_deals' => [],
        'download_price' => [],
        'download_caps_country' => [],
        'billing_leave_running' =>  ['except' => ['jacob@markertech.ai', 'pasha@markertech.ai']], //это в билинге дизабл кнопки 'leave_running'
    ],

    'traffic_endpoint' => [
        'download_crg_deals' => [],//'only' => ['qa@markertech.ai', 'alex@markertech.ai','dima@markertech.ai', 'vasilij@markertech.ai', 'bogdan@markertech.ai']
        'download_price' => [],
        'market_suit_dashboard_links' => [],
        'report_deposits_manually' => ['only' => ['qa@markertech.ai']]
    ],

    'gravity' => [
        'btn_reject_deposit'  => ['except' => [ 'qa@markertech.ai']],//'jacob@markertech.ai',
    ],	

    'crm' => [
        'lead_email' => ['except' => ['alex111@markertech.ai']],
		'show_crg_deal_id' => ['only' => ['qa@markertech.ai','alex@markertech.ai','vasilij@markertech.ai']],
        'mark_test' => [],//только админу и сапорту, это дополнительные настройки, удалять нельзя
        'change_payout_cpl' => ['only' => ['qa@markertech.ai', 'alex@markertech.ai', 'vasilij@markertech.ai','bogdan@markertech.ai','dima@markertech.ai','igor@markertech.ai','vadim@markertech.ai']],//'mark_crg' => ['except' => ['dima@markertech.ai']],
        'mark_crg' => ['only' => ['mike@markertech.ai','qa@markertech.ai', 'alex@markertech.ai', 'vasilij@markertech.ai','bogdan@markertech.ai','dima@markertech.ai','igor@markertech.ai','vadim@markertech.ai']],//'mark_crg' => ['except' => ['dima@markertech.ai']],
		'mark_ftd' => ['only' => ['mike@markertech.ai','qa@markertech.ai', 'alex@markertech.ai', 'vasilij@markertech.ai','bogdan@markertech.ai','dima@markertech.ai','igor@markertech.ai','vadim@markertech.ai']],//только админу и сапорту, это дополнительные настройки, удалять нельзя
        'mark_fire_ftd' => [],
        'change_revenue_cost' => ['only' => ['mike@markertech.ai','qa@markertech.ai', 'alex@markertech.ai', 'vasilij@markertech.ai','bogdan@markertech.ai','dima@markertech.ai','igor@markertech.ai','vadim@markertech.ai']],//только админу и сапорту, это дополнительные настройки, удалять нельзя
		'change_crg_deal_ftd' => ['only' => ['qa@markertech.ai', 'alex@markertech.ai', 'vasilij@markertech.ai']],//только админу и сапорту, это дополнительные настройки, удалять нельзя		
        'show_broker_statuses' => ['except' => ['qa1@markertech.ai','lyudmila.b@markertech.ai']],
		'deposit_disapproved_bg' =>  ['only' => ['mike@markertech.ai', 'alex@markertech.ai']],
		'deposit_reapprove' => ['only' => ['qa@markertech.ai', 'alex@markertech.ai','mike@markertech.ai']],
        'resync' => ['only' => ['qa@markertech.ai', 'alex@markertech.ai','mike@markertech.ai']], 
        'test_lead_bg'=> [], 
		'show_scrub_source' => ['only' => ['qa@markertech.ai','alex@markertech.ai']],
		'show_resync_source' => ['only' => ['qa@markertech.ai','alex@markertech.ai']],
        'crm_depositors' => ['except' => ['qa2@markertech.ai']],
    ],

   /*'support' => [
        'link_related_application_link' => ['only' => ['alex@markertech.ai']],//еще не сделано
    ],*/

];
