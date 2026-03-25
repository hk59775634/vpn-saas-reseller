<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * 站点展示信息（入库 site_settings，未配置时回退 config / 默认值）
 */
class SiteConfig
{
    public const K_NAME = 'site.name';

    public const K_TAGLINE = 'site.tagline';

    public const K_SUPPORT_EMAIL = 'site.support_email';

    public const K_ICP = 'site.icp';

    public const K_META_DESCRIPTION = 'site.meta_description';

    public static function siteName(): string
    {
        $v = SiteSetting::getValue(self::K_NAME);

        return ($v !== null && trim($v) !== '') ? trim($v) : (string) config('app.name', 'VPN 服务');
    }

    public static function tagline(): string
    {
        $v = SiteSetting::getValue(self::K_TAGLINE);

        return ($v !== null) ? trim((string) $v) : '';
    }

    public static function supportEmail(): string
    {
        $v = SiteSetting::getValue(self::K_SUPPORT_EMAIL);

        return ($v !== null) ? trim((string) $v) : '';
    }

    public static function icp(): string
    {
        $v = SiteSetting::getValue(self::K_ICP);

        return ($v !== null) ? trim((string) $v) : '';
    }

    public static function metaDescription(): string
    {
        $v = SiteSetting::getValue(self::K_META_DESCRIPTION);

        return ($v !== null) ? trim((string) $v) : '';
    }
}
