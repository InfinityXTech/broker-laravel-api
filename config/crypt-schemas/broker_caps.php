<?php
return [
    'collection' => 'm10',
    'exclude_crypt_fields' => [
        "language_code",
        "endpoint_livecaps",
        "blocked_schedule",
        "endpoint_dailycaps",
        "restrict_endpoints",
        "endpoint_priorities"
        // "country_code",
    ],
    'fields' => [
        "clientId" => 'a1',
        "broker" => 'a2',
        "country_code" => 'a3',
        "language_code" => 'a4',
        "integration" => 'a5',
        "cap_type" => 'a6',
        "period_type" => 'a7',
        "daily_cap" => 'a8',
        "enable_traffic" => 'a9',
        "note" => 'a10',
        "blocked_schedule" => 'a11',
        "endpoint_dailycaps" => 'a12',
        "restrict_type" => 'a13',
        "restrict_endpoints" => 'a14',
        "endpoint_livecaps" => 'a15',
        "live_caps" => 'a16',
        "blocked_funnels" => 'a17',
        "blocked_funnels_type" => 'a18',
        "created_at" => 'a19',
        "updated_at" => 'a20',
        "endpoint_priorities" => 'a21',
        "priority" =>  'a22',
    ]
];
