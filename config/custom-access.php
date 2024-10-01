<?php

use Illuminate\Support\Str;

return [

    '*' => [
        'deposit_disapproved' => ['only' => ['lyudmila.b@roibees.com', 'lena@roibees.com', 'qa22@roibees.com']],//не показывать шейв некоторым людам
        'CRG' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','andrey@roibees.com','finance@roibees.com', 'dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],//логи в лидах
        'broker_name' => ['except' => ['qa@roibees.com']],
        'broker_integration_name' => ['except' => ['qa2@roibees.com']],
        'send_test_lead' => [],
        'marketing_menu' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']]
    ],

    'billing' => [
        '__visible__' => false,
        'adjustment_bi' => ['only_traffic_endpoint' => ['619bd24159752e50c2363c12', '6292ff428a0bec36264338b2', '6128ac2625987538c9412372']],
    ],

    'reports' => [
        'adjustment' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']],
        'account_manager' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']],

        'pivots' => [

            'first_name' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],
            'last_name' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],
            'phone' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],
            'ip' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],
			'hit_the_redirect' => ['only' => ['vo@ppcnation.media', 'jacob@roibees.com', 'pasha@roibees.com','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
			
			'crg_percentage_id' => ['only' => ['vo@ppcnation.media','vasilij@roibees.com']],
			'broker_crg_percentage_id' => ['only' => ['vo@ppcnation.media', 'vasilij@roibees.com']],
            
			/*хочу понять нужно ли оно вообще
			'crg_leads' => ['only' => ['vo@ppcnation.media']],
            'broker_crg_leads' => ['only' => ['vo@ppcnation.media']],
            'test_lead' => ['only' => [ 'vo@ppcnation.media']],
            'crg_already_paid_ftd' => ['only' => ['vo@ppcnation.media']],
            'broker_crg_already_paid_ftd' => ['only' => ['vo@ppcnation.media']],*/
        ],

        'metrics' => [
            'crg_leads' => ['only' => ['qa@roibees.com', 'finance@roibees.com', 'jacob@roibees.com', 'vo@ppcnation.media', 'pasha@roibees.com','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
            'broker_crg_leads' => ['only' => ['finance@roibees.com', 'jacob@roibees.com', 'vo@ppcnation.media', 'pasha@roibees.com','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
            'crg_already_paid_ftd' => ['only' => ['finance@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
            'broker_crg_already_paid_ftd' => ['only' => ['finance@roibees.com', 'vo@ppcnation.media', 'dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
            'test_lead' => ['only' => ['vo@ppcnation.media', 'jacob@roibees.com', 'pasha@roibees.com', 'finance@roibees.com','manager@roibees.com', 'dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
            'approved_ftds' => ['except' => ['lyudmila.b@roibees.com']], //всем кроме одного!!!!!!!
        ]
    ],

    'quality_report' => [
        'disable_test_lead' => ['only' => ['maria@roibees.com']],//в отчете для нее все % не включали тестовые лиды
    ],

    'brokers' => [
        'billing_entities' => ['only' => ['qa@roibees.com']],//создание  контактов брокера так же могут все финасисты
        'download_crg_deals' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
        'download_price' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
        'download_caps_country' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com','andrey@roibees.com']],
        'billing_leave_running' =>  ['except' => ['jacob@roibees.com', 'pasha@roibees.com']], //это в билинге дизабл кнопки 'leave_running'
    ],

    'traffic_endpoint' => [
        'download_crg_deals' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
        'download_price' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','maria@roibees.com','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
        'market_suit_dashboard_links' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']],
        'report_deposits_manually' => ['only' => ['qa@markertech.ai']]
    ],

    'gravity' => [
        'btn_reject_deposit'  => ['except' => [ 'qa@roibees.com']],//'jacob@roibees.com',
    ],	

    'crm' => [
        'lead_email' => ['except' => ['vo1111@ppcnation.media']],
		'show_crg_deal_id' => ['only' => ['qa@roibees.com','vo@ppcnation.media','vasilij@roibees.com']],
        'mark_test' => [],//только админу и сапорту, это дополнительные настройки, удалять нельзя
        'mark_crg' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media', 'vasilij@roibees.com']],//'mark_crg' => ['except' => ['dima@roibees.com']],
		'mark_ftd' => [],//только админу и сапорту, это дополнительные настройки, удалять нельзя
        'mark_fire_ftd' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media', 'vasilij@roibees.com']],
        'change_revenue_cost' => [],//только админу и сапорту, это дополнительные настройки, удалять нельзя
		'change_crg_deal_ftd' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media', 'vasilij@roibees.com']],//только админу и сапорту, это дополнительные настройки, удалять нельзя		
        'show_broker_statuses' => ['except' => ['qa1@roibees.com','lyudmila.b@roibees.com']],
		'deposit_disapproved_bg' =>  ['only' => ['mike@ppcnation.media', 'vo@ppcnation.media']],
		'deposit_reapprove' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','mike@ppcnation.media']],
        'resync' => ['only' => ['qa@roibees.com', 'vo@ppcnation.media','mike@ppcnation.media']], 
        'test_lead_bg'=> ['only' => ['qa@roibees.com', 'vo@ppcnation.media', 'dima@roibees.com', 'vasilij@roibees.com', 'bogdan@roibees.com']], 
        'crm_depositors' => ['except' => ['qa@roibees.com']],
    ],

   /*'support' => [
        'link_related_application_link' => ['only' => ['vo@ppcnation.media']],//еще не сделано
    ],*/

];
