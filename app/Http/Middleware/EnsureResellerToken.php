<?php

namespace App\Http\Middleware;

use App\Services\VpnAValidateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerToken
{
    private function apiError(string $code, string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => [],
        ], $status);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return $this->apiError('UNAUTHENTICATED', '未登录', 401);
        }

        $apiKey = (string) (config('services.vpn_a.reseller_api_key') ?? '');
        if ($apiKey === '') {
            return $this->apiError('VPN_A_API_KEY_MISSING', '未配置 VPN_A_RESELLER_API_KEY', 503);
        }

        // 通过 A 站接口按 APIKEY 获取分销商身份，并使用服务内缓存；缓存缺失/失效时自动重新请求。
        $reseller = app(VpnAValidateService::class)->validate($apiKey);
        if (!is_array($reseller) || empty($reseller['id'])) {
            return $this->apiError('RESELLER_IDENTITY_UNAVAILABLE', '无法从 A 站获取分销商信息', 503);
        }

        $request->attributes->set('reseller', [
            'id' => (int) $reseller['id'],
            'name' => (string) ($reseller['name'] ?? 'reseller_' . (int) $reseller['id']),
        ]);

        return $next($request);
    }
}
