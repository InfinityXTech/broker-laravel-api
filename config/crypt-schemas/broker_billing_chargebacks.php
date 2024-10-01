<?php
return [
    'collection' => 'm6',
    'exclude_crypt_fields' => [
        "screenshots",
        "proof_screenshots",
        "final_approve_files"
    ],
    'fields' => [
        "clientId" => 'a1',
        "broker" => 'a2',
        "payment_method" => 'a3',
        "amount" => 'a4',
        "screenshots" => 'a5',
        "final_status" => 'a6',
        "created_at" => 'a7',
        "updated_at" => 'a8',
        "proof_screenshots" => 'a9',
        "final_approve_files" => 'a10',
        "payment_request" => 'a11',
        'proof_description' => 'a12',
        'final_status_changed_date' => 'a13',
        'final_status_changed_user_id' => 'a14',
        'final_status_changed_user_ip' => 'a15',
        'final_status_changed_user_ua' => 'a16',
        'final_status_date_pay' => 'a17',
        'transaction_id' => 'a18',
    ]
];
