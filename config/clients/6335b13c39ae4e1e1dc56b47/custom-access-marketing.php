<?php

//promo

return [

    '*' => [
        // 'deposit_disapproved' => ['only' => ['lyudmila.b@roibees.com', 'lena@roibees.com', 'qa22@roibees.com']],//не показывать шейв некоторым людам
        'marketing_advertiser_name' => ['except' => ['qa@roibees.com']],
    ],

    'marketing_reports' => [
        'adjustment' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']],
        'account_manager' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']],

        'pivots' => [
            'IP' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],
        ],

        'metrics' => [
        ]
    ],

    'marketing_advertisers' => [
        'billing_entities' => ['only' => ['qa@roibees.com']],//создание  контактов брокера так же могут все финасисты
        'billing_leave_running' =>  ['only' => ['jacob@roibees.com', 'pasha@roibees.com']], //это в билинге дизабл кнопки 'leave_running'
    ],

    'marketing_affiliates' => [
    ],

    'marketing_gravity' => [
        'btn_reject_deposit'  => ['except' => [ 'qa@roibees.com']],//'jacob@roibees.com',
    ],	

];
