<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | A 站 API 配置（用于校验 API Key、拉取分销商数据及在支付后调用开通接口，末尾勿加斜杠）
    */
    'vpn_a' => [
        'url' => env('VPN_A_URL', 'https://a.ai101.eu.org'),
        'cache_ttl' => (int) env('VPN_A_CACHE_TTL', 300), // 校验结果缓存秒数，0 不缓存
        // 本 B 站在 A 站的分销商主键（用于本地 reseller_products 归属、非 SSL 产品校验）。SSL 登录名后缀仅以 GET /api/v1/reseller/me 为准，不读此值。
        'reseller_id' => (int) env('VPN_A_RESELLER_ID', 1),
        'reseller_api_key' => env('VPN_A_RESELLER_API_KEY'), // 用于服务端调用 A 站分销商开通接口的 API Key
        'default_region' => env('VPN_DEFAULT_REGION', null), // 默认区域（注册同步/开通时使用，可为空）
        /** SSL VPN 网关地址/域名（用户已购产品详情展示，可与实际接入一致） */
        'sslvpn_gateway' => env('VPN_A_SSLVPN_GATEWAY', ''),
    ],

];
