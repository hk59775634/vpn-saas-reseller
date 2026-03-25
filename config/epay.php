<?php

return [

    'enabled' => (bool) env('EPAY_ENABLED', false),

    'gateway' => rtrim((string) env('EPAY_GATEWAY', ''), '/'),

    'pid' => env('EPAY_PID', ''),

    'key' => env('EPAY_KEY', ''),

    'return_url' => env('EPAY_RETURN_URL'),

    'notify_url' => env('EPAY_NOTIFY_URL'),

    /** 用户订单页「模拟支付」按钮（生产环境请关；仅开发/内测在 .env 设为 true） */
    'allow_simulated_payment' => (bool) env('EPAY_ALLOW_SIMULATED_PAYMENT', false),

];
