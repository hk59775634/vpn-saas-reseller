<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PaymentConfig;
use App\Support\RateLimitSettings;
use App\Support\RuntimeStackConfig;
use App\Support\SiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResellerViewController extends Controller
{
    public function login(): View
    {
        return view('auth.reseller-login');
    }

    public function dashboard(): View
    {
        // 概览页：统计 + 我的信息
        return view('reseller.dashboard.overview');
    }

    public function products(): View
    {
        return view('reseller.dashboard.products');
    }

    public function users(): View
    {
        return view('reseller.dashboard.users');
    }

    public function orders(): View
    {
        return view('reseller.dashboard.orders');
    }

    public function subscriptions(): View
    {
        return view('reseller.dashboard.subscriptions');
    }

    public function apiKeys(): View
    {
        return view('reseller.dashboard.api_keys', [
            'vpn_a_url' => config('services.vpn_a.url'),
            'vpn_a_key' => config('services.vpn_a.reseller_api_key'),
        ]);
    }

    public function updateApiKeys(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'VPN_A_URL' => 'required|string',
            'VPN_A_RESELLER_API_KEY' => 'required|string',
        ]);

        $vpnAUrl = rtrim(trim((string) $data['VPN_A_URL']), '/');
        $apiKey = trim((string) $data['VPN_A_RESELLER_API_KEY']);

        if (!filter_var($vpnAUrl, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'VPN_A_URL' => ['A 站 URL 格式不正确'],
            ]);
        }

        $resellerId = $this->resolveResellerIdFromA($vpnAUrl, $apiKey);

        $this->updateEnv([
            'VPN_A_URL' => $vpnAUrl,
            'VPN_A_RESELLER_API_KEY' => $apiKey,
            // 自动同步：不再手工维护
            'VPN_A_RESELLER_ID' => (string) $resellerId,
        ]);

        Artisan::call('config:clear');

        return back()->with('message', 'A 站 URL 与 API Key 已更新，并自动同步 VPN_A_RESELLER_ID=' . $resellerId . '。若未生效，请重启 PHP 进程。');
    }

    public function siteSettings(): View
    {
        return view('reseller.dashboard.site_settings', [
            'site_name' => SiteSetting::getValue(SiteConfig::K_NAME) ?? '',
            'site_tagline' => SiteSetting::getValue(SiteConfig::K_TAGLINE) ?? '',
            'support_email' => SiteSetting::getValue(SiteConfig::K_SUPPORT_EMAIL) ?? '',
            'icp' => SiteSetting::getValue(SiteConfig::K_ICP) ?? '',
            'meta_description' => SiteSetting::getValue(SiteConfig::K_META_DESCRIPTION) ?? '',
        ]);
    }

    public function updateSiteSettings(Request $request): RedirectResponse
    {
        $rawEmail = $request->input('support_email');
        $request->merge([
            'support_email' => ($rawEmail !== null && trim((string) $rawEmail) !== '') ? trim((string) $rawEmail) : null,
        ]);

        $data = $request->validate([
            'site_name' => 'nullable|string|max:64',
            'site_tagline' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'icp' => 'nullable|string|max:128',
            'meta_description' => 'nullable|string|max:512',
        ]);

        $this->persistOptionalSetting(SiteConfig::K_NAME, trim((string) ($data['site_name'] ?? '')));
        $this->persistOptionalSetting(SiteConfig::K_TAGLINE, trim((string) ($data['site_tagline'] ?? '')));
        $this->persistOptionalSetting(SiteConfig::K_SUPPORT_EMAIL, trim((string) ($data['support_email'] ?? '')));
        $this->persistOptionalSetting(SiteConfig::K_ICP, trim((string) ($data['icp'] ?? '')));
        $this->persistOptionalSetting(SiteConfig::K_META_DESCRIPTION, trim((string) ($data['meta_description'] ?? '')));

        Artisan::call('config:clear');

        return back()->with('message', '站点信息已保存。');
    }

    /** 空字符串则删除键，回退到默认 */
    private function persistOptionalSetting(string $key, string $value): void
    {
        if ($value === '') {
            SiteSetting::deleteKey($key);
        } else {
            SiteSetting::setValue($key, $value);
        }
    }

    public function payment(): View
    {
        $keySet = PaymentConfig::keyIsSetInDatabase() || (string) config('epay.key', '') !== '';
        $plainKey = PaymentConfig::key();
        $keyHint = '';
        if ($plainKey !== '') {
            $keyHint = strlen($plainKey) <= 8 ? '********' : ('…'.substr($plainKey, -4));
        }

        return view('reseller.dashboard.payment', [
            'epay_notify_url_effective' => PaymentConfig::notifyUrl(),
            'epay_return_url_effective' => PaymentConfig::returnUrl(),
            'epay_key_set' => $keySet,
            'epay_key_hint' => $keyHint,
            'epay_notify_url_raw' => $this->epayNotifyUrlRaw(),
            'epay_return_url_raw' => $this->epayReturnUrlRaw(),
        ]);
    }

    /** 表单展示：库中未单独配置则空字符串（表示用默认 / .env） */
    private function epayNotifyUrlRaw(): string
    {
        $db = SiteSetting::getValue(PaymentConfig::K_NOTIFY_URL);

        return $db !== null ? $db : '';
    }

    private function epayReturnUrlRaw(): string
    {
        $db = SiteSetting::getValue(PaymentConfig::K_RETURN_URL);

        return $db !== null ? $db : '';
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'epay_enabled' => ['required', 'in:0,1'],
            'epay_gateway' => ['nullable', 'string', 'max:512'],
            'epay_pid' => ['nullable', 'string', 'max:64'],
            'epay_key' => ['nullable', 'string', 'max:512'],
            'epay_notify_url' => ['nullable', 'string', 'max:1024'],
            'epay_return_url' => ['nullable', 'string', 'max:1024'],
            'epay_allow_simulated_payment' => ['required', 'in:0,1'],
        ]);

        $gwIn = trim((string) ($data['epay_gateway'] ?? ''));
        $pidIn = trim((string) ($data['epay_pid'] ?? ''));
        if ($gwIn !== '' && !filter_var($gwIn, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'epay_gateway' => ['API 地址应为合法 URL（例如 https://pay.example.com）'],
            ]);
        }

        $epayOn = ($data['epay_enabled'] ?? '0') === '1';
        if ($epayOn) {
            if ($gwIn === '' || $pidIn === '') {
                throw ValidationException::withMessages([
                    'epay_gateway' => ['开启易支付时，请填写 API 地址与商户 ID'],
                ]);
            }
            $keyIn = trim((string) ($data['epay_key'] ?? ''));
            $hasKey = $keyIn !== '' || PaymentConfig::keyIsSetInDatabase() || (string) config('epay.key', '') !== '';
            if (!$hasKey) {
                throw ValidationException::withMessages([
                    'epay_key' => ['开启易支付时，请填写 MD5 密钥（或已在数据库 / .env 中配置过）'],
                ]);
            }
        }

        $notify = trim((string) ($data['epay_notify_url'] ?? ''));
        if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'epay_notify_url' => ['异步通知地址应为合法 URL'],
            ]);
        }

        $ret = trim((string) ($data['epay_return_url'] ?? ''));
        if ($ret !== '' && !filter_var($ret, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'epay_return_url' => ['同步跳转地址应为合法 URL'],
            ]);
        }

        SiteSetting::setValue(PaymentConfig::K_ENABLED, $epayOn ? '1' : '0');
        SiteSetting::setValue(
            PaymentConfig::K_ALLOW_SIMULATED_PAYMENT,
            (($data['epay_allow_simulated_payment'] ?? '0') === '1') ? '1' : '0'
        );

        if ($gwIn === '') {
            SiteSetting::deleteKey(PaymentConfig::K_GATEWAY);
        } else {
            SiteSetting::setValue(PaymentConfig::K_GATEWAY, rtrim($gwIn, '/'));
        }
        if ($pidIn === '') {
            SiteSetting::deleteKey(PaymentConfig::K_PID);
        } else {
            SiteSetting::setValue(PaymentConfig::K_PID, $pidIn);
        }

        if (!empty(trim((string) ($data['epay_key'] ?? '')))) {
            SiteSetting::setValue(PaymentConfig::K_KEY, Crypt::encryptString(trim($data['epay_key'])));
        }

        if ($notify === '') {
            SiteSetting::deleteKey(PaymentConfig::K_NOTIFY_URL);
        } else {
            SiteSetting::setValue(PaymentConfig::K_NOTIFY_URL, $notify);
        }

        if ($ret === '') {
            SiteSetting::deleteKey(PaymentConfig::K_RETURN_URL);
        } else {
            SiteSetting::setValue(PaymentConfig::K_RETURN_URL, $ret);
        }

        Artisan::call('config:clear');

        return back()->with('message', '易支付配置已保存（写入站点数据库）。若未生效，请重启 PHP 进程。');
    }

    public function runtimeSettings(): View
    {
        $envOn = RuntimeStackConfig::redisExplicitlyConfiguredInEnv();
        $pingOk = $envOn && RuntimeStackConfig::redisPingSucceeded();

        return view('reseller.dashboard.runtime_settings', [
            'redis_env_configured' => $envOn,
            'redis_connection_ok' => $pingOk,
            'rate_limits' => RateLimitSettings::allForAdmin(),
        ]);
    }

    public function updateRuntimeSettings(Request $request): RedirectResponse
    {
        $defaults = RateLimitSettings::defaults();
        $rules = [];
        foreach (array_keys($defaults) as $key) {
            $rules[$key] = ['nullable', 'integer', 'min:1', 'max:100000'];
        }

        $data = $request->validate($rules);

        $out = [];
        foreach ($defaults as $k => $def) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                $out[$k] = max(1, min(100000, (int) $data[$k]));
            } else {
                $out[$k] = $def;
            }
        }
        SiteSetting::setValue(RateLimitSettings::K_RATE_LIMITS, json_encode($out, JSON_UNESCAPED_UNICODE));

        RateLimitSettings::resetCache();
        Artisan::call('config:clear');

        return back()->with('message', '限流设置已保存。');
    }

    private function updateEnv(array $pairs): void
    {
        $path = base_path('.env');
        if (!File::exists($path)) {
            return;
        }

        $env = File::get($path);

        foreach ($pairs as $key => $value) {
            if ($value === null) {
                continue;
            }
            $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $pattern = "/^{$key}=.*$/m";
            $line = $key . '=' . $valueStr;

            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, $line, $env);
            } else {
                $env .= PHP_EOL . $line;
            }
        }

        File::put($path, $env);
    }

    private function resolveResellerIdFromA(string $vpnAUrl, string $apiKey): int
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($apiKey)
                ->get($vpnAUrl . '/api/v1/reseller/me');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'VPN_A_RESELLER_API_KEY' => ['无法连接 A 站校验 API Key：' . $e->getMessage()],
            ]);
        }

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'VPN_A_RESELLER_API_KEY' => ['API Key 校验失败（A 站返回 HTTP ' . $response->status() . '）'],
            ]);
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw ValidationException::withMessages([
                'VPN_A_RESELLER_API_KEY' => ['A 站返回格式异常（非 JSON 对象）'],
            ]);
        }

        $data = (
            array_key_exists('success', $payload)
            && array_key_exists('code', $payload)
            && array_key_exists('message', $payload)
            && array_key_exists('data', $payload)
        ) ? $payload['data'] : $payload;

        $id = is_array($data) ? (int) ($data['id'] ?? 0) : 0;
        if ($id <= 0) {
            throw ValidationException::withMessages([
                'VPN_A_RESELLER_API_KEY' => ['A 站未返回有效分销商 ID'],
            ]);
        }

        return $id;
    }
}
