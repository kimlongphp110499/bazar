<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin email configuration
    |--------------------------------------------------------------------------
    |
    | Set the admin email. This will be used to send email when user contact through contact page.
    |
    */
    'admin_email' => 'kimlongxutede110499@gmail.com',
    'admin_password' => 'udiduxmhyzdsuiyj',
    'shop_url_pakage' => 'http://localhost:3003',
    'shop_url_device' => 'http://localhost:3000',

    /*
    |--------------------------------------------------------------------------
    | Shop url configuration
    |--------------------------------------------------------------------------
    |
    | Shop url is used in order placed template to go to shop order page.
    |
    */
    'shop_url' => env('SHOP_URL'),

    'dashboard_url' => env('DASHBOARD_URL'),

    'media_disk' => env('MEDIA_DISK'),

    'stripe_api_key' => env('STRIPE_API_KEY'),

    'app_notice_domain' => env('APP_NOTICE_DOMAIN', 'MARVEL_'),

    'dummy_data_path' => env('DUMMY_DATA_PATH', 'pickbazar'),

    'default_language' => env('DEFAULT_LANGUAGE', 'en'),

    'translation_enabled' => env('TRANSLATION', true),
];
