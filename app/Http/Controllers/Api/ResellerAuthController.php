<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResellerAuthController extends Controller
{
    private function apiError(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => [],
        ], $status);
    }

    private function apiOk(string $code, string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * POST /api/v1/reseller/auth
     * 使用本地账号密码登录分销商后台，不再用 API Key 作为登录凭证。
     * 请求体: { "username": "admin", "password": "admin123" }
     * 返回: { "token": "...", "reseller": { "name": "admin" } }
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $envUser = env('RESELLER_ADMIN_USERNAME', 'admin');
        $envHash = env('RESELLER_ADMIN_PASSWORD_HASH');

        if ($data['username'] !== $envUser) {
            return $this->apiError('INVALID_CREDENTIALS', '账号或密码错误', 401);
        }

        if ($envHash && !Hash::check($data['password'], $envHash)) {
            return $this->apiError('INVALID_CREDENTIALS', '账号或密码错误', 401);
        }

        // 简单 token：基于用户名和时间戳生成（前端仅作会话标记）
        $token = base64_encode($envUser . '|' . time());

        return $this->apiOk('LOGIN_SUCCESS', '登录成功', [
            'token' => $token,
            'reseller' => [
                'name' => $envUser,
            ],
        ]);
    }
}
