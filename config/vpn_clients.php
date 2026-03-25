<?php

/**
 * 用户前台「下载」页：官方客户端下载链接（外站）。
 * 链接可能随厂商更新而变化，若失效请自行替换。
 */
return [

    'wireguard' => [
        [
            'platform' => 'Windows',
            'hint' => 'Windows 10 / 11',
            'links' => [
                ['label' => '官方安装包 (.exe)', 'url' => 'https://download.wireguard.com/windows-client/wireguard-installer.exe'],
                ['label' => '安装说明（官网）', 'url' => 'https://www.wireguard.com/install/'],
            ],
        ],
        [
            'platform' => 'macOS',
            'hint' => 'App Store',
            'links' => [
                ['label' => 'Mac App Store', 'url' => 'https://apps.apple.com/app/wireguard/id1451685025'],
                ['label' => '安装说明（官网）', 'url' => 'https://www.wireguard.com/install/'],
            ],
        ],
        [
            'platform' => 'Linux',
            'hint' => '各发行版',
            'links' => [
                ['label' => '官方安装指引', 'url' => 'https://www.wireguard.com/install/'],
                ['label' => '内核模块与工具（源码）', 'url' => 'https://git.zx2c4.com/wireguard-linux/'],
            ],
        ],
        [
            'platform' => 'Android',
            'hint' => '手机 / 平板',
            'links' => [
                ['label' => 'Google Play', 'url' => 'https://play.google.com/store/apps/details?id=com.wireguard.android'],
                ['label' => 'F-Droid', 'url' => 'https://f-droid.org/packages/com.wireguard.android/'],
            ],
        ],
        [
            'platform' => 'iPhone / iPad',
            'hint' => 'iOS / iPadOS',
            'links' => [
                ['label' => 'App Store', 'url' => 'https://apps.apple.com/app/wireguard/id1441195209'],
            ],
        ],
    ],

    /*
     * Cisco Secure Client（原 AnyConnect）—— 桌面端安装包常由企业/IT 分发；
     * 此处提供官网入口与应用商店客户端；Linux 场景多使用 OpenConnect。
     */
    'anyconnect' => [
        [
            'platform' => 'Windows',
            'hint' => 'Cisco Secure Client',
            'links' => [
                ['label' => 'Cisco 软件下载（需账号）', 'url' => 'https://software.cisco.com/download/home'],
                ['label' => '产品说明', 'url' => 'https://www.cisco.com/c/en/us/products/security/anyconnect-secure-mobility-client/index.html'],
            ],
        ],
        [
            'platform' => 'macOS',
            'hint' => 'Cisco Secure Client',
            'links' => [
                ['label' => 'Mac App Store（客户端）', 'url' => 'https://apps.apple.com/app/cisco-secure-client/id1133560590'],
                ['label' => 'Cisco 软件下载（需账号）', 'url' => 'https://software.cisco.com/download/home'],
            ],
        ],
        [
            'platform' => 'Linux',
            'hint' => '常用：OpenConnect（兼容 SSL VPN）',
            'links' => [
                ['label' => 'OpenConnect 项目', 'url' => 'https://www.infradead.org/openconnect/'],
                ['label' => '各发行版软件包说明', 'url' => 'https://gitlab.com/openconnect/openconnect/-/wikis/Building-on-GNU/Linux'],
            ],
        ],
        [
            'platform' => 'Android',
            'hint' => 'Cisco Secure Client',
            'links' => [
                ['label' => 'Google Play', 'url' => 'https://play.google.com/store/apps/details?id=com.cisco.anyconnect.vpn.android.avf'],
            ],
        ],
        [
            'platform' => 'iPhone / iPad',
            'hint' => 'Cisco Secure Client',
            'links' => [
                ['label' => 'App Store', 'url' => 'https://apps.apple.com/app/cisco-secure-client/id1133560590'],
            ],
        ],
    ],

];
