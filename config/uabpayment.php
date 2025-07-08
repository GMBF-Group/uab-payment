<?php
return [
        'merchant_id' => env('UAB_MERCHANT_ID', ''),
        'merchant_access_key' => env('UAB_ACCESS_KEY', ''),
        'merchant_channel' => env('UAB_MERCHANT_CHANNEL', ''),
        'secret_key' => env('UAB_SECRET_KEY', ''),
        'ins_id' => env('UAB_INS_ID', ''),
        'client_secret' => env('UAB_CLIENT_SECRET', ''),
        'payment_method' => env('UAB_PAYMENT_METHOD', ''),
        'payment_url' => env('UAB_PAYMENT_URL', ''),
        'payment_expire' => env('UAB_PAYMENT_EXPIRE', '300'),
        'payment_callback_url' => env('UAB_PAYMENT_CALLBACK_URL', ''),
        'payment_success_url' => env('UAB_PAYMENT_SUCCESS_URL', ''),
        'payment_failed_url' => env('UAB_PAYMENT_FAILED_URL', ''),
];