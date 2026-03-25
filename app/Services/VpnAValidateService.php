<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 通过 A 站 API 校验 API Key 并获取分销商信息（可选缓存）
 */
class VpnAValidateService
{
    private string $baseUrl;

    /** 校验结果缓存时间（秒），0 表示不缓存 */
    private int $cacheTtl;

    /** 用于调用 A 站分销商开通接口的 API Key（服务端自持） */
    private ?string $resellerApiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.vpn_a.url', ''), '/');
        $this->cacheTtl = (int) config('services.vpn_a.cache_ttl', 300);
        $this->resellerApiKey = config('services.vpn_a.reseller_api_key') ?: null;
    }

    /**
     * A 站公开产品 JSON 中 enable_radius / enable_wireguard 多为 0/1（整数），不能用 === true 判断。
     */
    public static function flagEnabled(mixed $value, bool $default = true): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));

            return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
        }

        return (bool) $value;
    }

    /**
     * 校验 API Key，成功返回 ['id' => int, 'name' => string]，失败返回 null
     */
    public function validate(string $apiKey): ?array
    {
        if ($this->cacheTtl > 0) {
            $cached = Cache::get('vpn_a_reseller:' . $apiKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        if (!$this->baseUrl) {
            return null;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->post($this->baseUrl . '/api/v1/reseller/validate', [
                'api_key' => $apiKey,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $this->unwrapEnvelope($response->json());
        $reseller = $data['reseller'] ?? null;
        if (!$reseller || !isset($reseller['id'], $reseller['name'])) {
            return null;
        }

        if ($this->cacheTtl > 0) {
            Cache::put('vpn_a_reseller:' . $apiKey, $reseller, $this->cacheTtl);
        }

        return $reseller;
    }

    /**
     * 调用 A 站接口（带 Bearer api_key），返回解码后的 JSON 或 null
     */
    public function get(string $apiKey, string $path): array|null
    {
        if (!$this->baseUrl) {
            return null;
        }
        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($apiKey)
            ->get($this->baseUrl . $path);
        if (!$response->successful()) {
            return null;
        }
        $data = $this->unwrapEnvelope($response->json());
        return is_array($data) ? $data : null;
    }

    /**
     * 拉取 A 站公开产品列表（无需 API Key），供分销商组合为自有产品
     */
    public function getPublicProducts(): array
    {
        if (!$this->baseUrl) {
            return [];
        }
        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl . '/api/v1/products/public');
        if (!$response->successful()) {
            return [];
        }
        $data = $this->unwrapEnvelope($response->json());
        return is_array($data) ? $data : [];
    }

    /**
     * 拉取 A 站公开线路/区域列表（无需 API Key），供用户下拉选择
     */
    public function getPublicRegions(): array
    {
        if (!$this->baseUrl) {
            return [];
        }
        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl . '/api/v1/regions/public');
        if (!$response->successful()) {
            return [];
        }
        $data = $this->unwrapEnvelope($response->json());
        return is_array($data) ? array_values(array_filter($data, fn ($v) => is_string($v) && $v !== '')) : [];
    }

    /**
     * 调用 A 站 GET /api/v1/reseller/me（Bearer {@see $this->resellerApiKey}，即 .env VPN_A_RESELLER_API_KEY），
     * 返回当前 API Key 对应分销商主键 id。失败返回 null。
     *
     * 结果按 cache_ttl 缓存；SSL 登录名后缀仅应使用本方法返回值，不再回退 .env VPN_A_RESELLER_ID。
     */
    public function fetchResellerIdFromMe(): ?int
    {
        if (!$this->baseUrl || !$this->resellerApiKey) {
            return null;
        }
        $ttl = max(60, $this->cacheTtl);
        $cacheKey = 'vpn_a_reseller_me_id:' . sha1($this->baseUrl . '|' . $this->resellerApiKey);

        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_int($cached) && $cached > 0) {
            return $cached;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($this->resellerApiKey)
            ->get($this->baseUrl . '/api/v1/reseller/me');

        if (!$response->successful()) {
            return null;
        }
        $data = $this->unwrapEnvelope($response->json());
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return null;
        }
        Cache::put($cacheKey, $id, $ttl);

        return $id;
    }

    /**
     * SSL 登录名：「用户填写@分销商ID」；分销商 ID **仅**来自 GET /api/v1/reseller/me，与 A 站写入 FreeRADIUS 一致。
     * 不再使用 VPN_A_RESELLER_ID 作为后缀来源。
     *
     * @return array{ok:bool, reseller_id:?int, reseller_id_source:'api'|'unavailable', sslvpn_username_suffix:string, message?:string}
     */
    public function getSiteVpnIdentityForSsl(): array
    {
        $id = $this->fetchResellerIdFromMe();

        if ($id === null || $id <= 0) {
            return [
                'ok' => false,
                'reseller_id' => null,
                'reseller_id_source' => 'unavailable',
                'sslvpn_username_suffix' => '',
                'message' => '无法从 A 站获取分销商信息：请配置 VPN_A_URL、VPN_A_RESELLER_API_KEY，并确认 GET /api/v1/reseller/me 可访问。',
            ];
        }

        return [
            'ok' => true,
            'reseller_id' => $id,
            'reseller_id_source' => 'api',
            'sslvpn_username_suffix' => (string) $id,
        ];
    }

    /**
     * 在用户支付成功后调用 A 站分销商订单开通接口。
     * 返回 true 表示调用成功（2xx），false 表示失败。
     */
    public function provisionOrder(array $payload): bool
    {
        return $this->provisionOrderResult($payload) !== null;
    }

    /**
     * 在用户注册后，将用户同步到 A 站（仅创建/更新用户，不创建订单）。
     * 失败时返回 false，不影响 B 站本地注册流程。
     */
    public function syncUser(string $email, ?string $name = null, ?string $region = null): bool
    {
        if (!$this->baseUrl || !$this->resellerApiKey) {
            return false;
        }

        $payload = [
            'user_email' => $email,
            'user_name' => $name,
        ];
        if ($region) {
            $payload['region'] = $region;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($this->resellerApiKey)
            ->post($this->baseUrl . '/api/v1/reseller/users/sync', $payload);

        return $response->successful();
    }

    /**
     * 支付成功后调用 A 站开通接口（支持 region / wireguard_public_key）。
     * 返回 true 表示调用成功（2xx），false 表示失败。
     */
    public function provisionOrderWithMeta(array $payload): bool
    {
        if (!$this->baseUrl || !$this->resellerApiKey) {
            return false;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($this->resellerApiKey)
            ->post($this->baseUrl . '/api/v1/reseller/orders', $payload);

        return $response->successful();
    }

    /**
     * 调用 A 站开通接口，返回成功标志、JSON 体与错误信息（补开通失败时可展示 A 站 message）。
     */
    public function provisionOrderResponse(array $payload): array
    {
        if (!$this->baseUrl || !$this->resellerApiKey) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'A 站 API 未配置（请检查 VPN_A_URL / VPN_A_RESELLER_API_KEY）',
                'status' => 0,
            ];
        }

        $response = Http::timeout(60)
            ->acceptJson()
            ->withToken($this->resellerApiKey)
            ->post($this->baseUrl . '/api/v1/reseller/orders', $payload);

        $json = $response->json();
        $jsonData = $this->unwrapEnvelope($json);
        $message = null;
        if (is_array($json)) {
            $message = $json['message'] ?? null;
            if ($message === null && isset($json['error'])) {
                $message = is_string($json['error']) ? $json['error'] : json_encode($json['error'], JSON_UNESCAPED_UNICODE);
            }
        }
        if ($message === null && !$response->successful()) {
            $message = 'HTTP ' . $response->status();
            $body = $response->body();
            if (is_string($body) && strlen($body) > 0 && strlen($body) < 800) {
                $message .= '：' . $body;
            }
        }

        return [
            'success' => $response->successful(),
            'data' => is_array($jsonData) ? $jsonData : null,
            'message' => $message ?? '请求失败',
            'status' => $response->status(),
        ];
    }

    /**
     * 支付/续费后调用 A 站开通接口，返回完整响应（含 order 生命周期信息）。
     */
    public function provisionOrderResult(array $payload): ?array
    {
        $r = $this->provisionOrderResponse($payload);

        return ($r['success'] && is_array($r['data'])) ? $r['data'] : null;
    }

    /**
     * @param  int|null  $aOrderId  A 站订单 ID，与 B 站订阅 a_order_id 一致；传入则每已购产品独立配置
     */
    public function getWireguardConfig(string $userEmail, ?int $aOrderId = null): ?array
    {
        if (!$this->baseUrl || !$this->resellerApiKey) {
            return null;
        }
        $query = [
            'user_email' => $userEmail,
        ];
        if ($aOrderId !== null && $aOrderId > 0) {
            $query['a_order_id'] = $aOrderId;
        }
        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($this->resellerApiKey)
            ->get($this->baseUrl . '/api/v1/reseller/wireguard/config', $query);
        if (!$response->successful()) {
            return null;
        }
        $data = $this->unwrapEnvelope($response->json());
        return is_array($data) ? $data : null;
    }

    /**
     * 兼容 A 站新老响应：
     * - 新：{success, code, message, data}
     * - 旧：直接业务 JSON
     */
    private function unwrapEnvelope(mixed $payload): mixed
    {
        if (!is_array($payload)) {
            return $payload;
        }
        if (
            array_key_exists('success', $payload)
            && array_key_exists('code', $payload)
            && array_key_exists('message', $payload)
            && array_key_exists('data', $payload)
        ) {
            return $payload['data'];
        }

        return $payload;
    }
}
