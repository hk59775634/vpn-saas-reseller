<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * B 站彩虹易支付：分销商后台「支付设置」入库优先，否则 .env（config/epay.php）
 */
class PaymentConfig
{
    public const K_ENABLED = 'epay.enabled';

    public const K_GATEWAY = 'epay.gateway';

    public const K_PID = 'epay.pid';

    public const K_KEY = 'epay.key';

    public const K_NOTIFY_URL = 'epay.notify_url';

    public const K_RETURN_URL = 'epay.return_url';

    public const K_ALLOW_SIMULATED_PAYMENT = 'epay.allow_simulated_payment';

    public static function enabled(): bool
    {
        $db = SiteSetting::getValue(self::K_ENABLED);
        if ($db !== null) {
            return $db === '1' || $db === 'true';
        }

        return (bool) config('epay.enabled');
    }

    public static function allowSimulatedPayment(): bool
    {
        $db = SiteSetting::getValue(self::K_ALLOW_SIMULATED_PAYMENT);
        if ($db !== null) {
            return $db === '1' || $db === 'true';
        }

        return (bool) config('epay.allow_simulated_payment');
    }

    public static function gateway(): string
    {
        $db = SiteSetting::getValue(self::K_GATEWAY);
        if ($db !== null && trim($db) !== '') {
            return rtrim(trim($db), '/');
        }

        return rtrim((string) config('epay.gateway', ''), '/');
    }

    public static function pid(): string
    {
        $db = SiteSetting::getValue(self::K_PID);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('epay.pid', ''));
    }

    public static function key(): string
    {
        $db = SiteSetting::getValue(self::K_KEY);
        if ($db !== null && $db !== '') {
            try {
                return Crypt::decryptString($db);
            } catch (\Throwable) {
                return $db;
            }
        }

        return (string) config('epay.key', '');
    }

    public static function notifyUrl(): string
    {
        $db = SiteSetting::getValue(self::K_NOTIFY_URL);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }
        $env = config('epay.notify_url');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        return rtrim((string) config('app.url'), '/').'/pay/webhook/epay';
    }

    public static function returnUrl(): string
    {
        $db = SiteSetting::getValue(self::K_RETURN_URL);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }
        $env = config('epay.return_url');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        return rtrim((string) config('app.url'), '/').'/orders?pay_return=1';
    }

    public static function keyIsSetInDatabase(): bool
    {
        $db = SiteSetting::getValue(self::K_KEY);

        return $db !== null && $db !== '';
    }
}
