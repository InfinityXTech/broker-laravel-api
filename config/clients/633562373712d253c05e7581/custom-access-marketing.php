<?php

//roi bees

return [

    '*' => [
        // 'deposit_disapproved' => ['only' => ['lyudmila.b@roibees.com', 'lena@roibees.com', 'qa22@roibees.com']],//не показывать шейв некоторым людам
        'marketing_advertiser_name' => ['except' => ['qa@roibees.com']],//пока еще не сделано на беке
    ],

    'marketing_reports' => [        
        'account_manager' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com', 'vo@ppcnation.media']],

        'pivots' => [
            'IP' => ['only' => ['mike@ppcnation.media', 'qa@roibees.com']],//пока еще не сделано на беке
        ],

        'metrics' => [
        ]
    ],

    'marketing_advertisers' => [
        'billing_entities' => ['only' => ['qa@roibees.com']],//создание  контактов брокера так же могут все финасисты
        'billing_leave_running' =>  ['only' => ['jacob@roibees.com', 'pasha@roibees.com', 'vo@ppcnation.media']], //это в билинге дизабл кнопки 'leave_running'
    ],

    'marketing_affiliates' => [
    ],

    'marketing_gravity' => [
        'btn_reject_deposit'  => ['except' => [ 'qa@roibees.com']],//'jacob@roibees.com',//так же все админы могут Отклонять
    ],	

];
