<?php 


return [

    'profile_id'   => env('CLICKPAY_PROFILE_ID', 46600),
    'server_key'   => env('CLICKPAY_SERVER_KEY', ''),
    'base_url'     => env('CLICKPAY_BASE_URL', 'https://secure.clickpay.com.sa'),
    'return_url'   => env('CLICKPAY_RETURN_URL', null),
    'callback_url' => env('CLICKPAY_CALLBACK_URL', null),

    // allowed tran_class values
    'classes' => ['ecom','moto','cont'],

    // allowed follow-up types
    'follow_up_types' => ['refund','void'],

    // PayPage display options
    'page_options' => ['framed','hide_shipping'],

];
