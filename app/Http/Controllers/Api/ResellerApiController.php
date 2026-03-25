<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ResellerProduct;
use App\Models\User;
use App\Models\UserVpnSubscription;
use App\Services\VpnAValidateService;
use App\Support\OrderBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ResellerApiController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA
    ) {}

    private function reseller(Request $request): array
    {
        $reseller = $request->attributes->get('reseller');
        if (!$reseller || !is_array($reseller)) {
            abort(401, '未登录');
        }
        return $reseller;
    }

    private function resellerId(Request $request): int
    {
        $r = $this->reseller($request);
        return (int) $r['id'];
    }

    private function apiOk(string $code, string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * 仅允许访问“与本分销商产品发生过订单关系”的用户。
     * 这样可以避免在同一套 B 站用户库中跨分销商看到/操作其他用户数据。
     */
    private function resellerUserQuery(int $resellerId)
    {
        return User::query()->whereHas('orders', function ($q) use ($resellerId) {
            $q->whereHas('resellerProduct', fn ($q2) => $q2->where('reseller_id', $resellerId));
        });
    }

    /**
     * GET /api/v1/reseller/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->apiOk('RESELLER_PROFILE', '获取成功', $this->reseller($request));
    }

    /**
     * GET /api/v1/reseller/stats — 简要统计（用户数、订单数、已支付金额）
     */
    public function stats(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $ordersQuery = Order::whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', $resellerId));
        $usersCount = (clone $ordersQuery)->distinct('user_id')->count('user_id');
        $ordersCount = (clone $ordersQuery)->count();
        $paidAmountCents = (clone $ordersQuery)->where('status', 'paid')->sum('amount_cents');
        return $this->apiOk('RESELLER_STATS', '获取成功', [
            'users_count' => $usersCount,
            'orders_count' => $ordersCount,
            'paid_amount_cents' => (int) $paidAmountCents,
        ]);
    }

    /**
     * GET /api/v1/reseller/me/api_keys（转发 A 站）
     */
    public function apiKeys(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $data = $this->vpnA->get($token, '/api/v1/reseller/me/api_keys');
        return $this->apiOk('RESELLER_API_KEYS', '获取成功', is_array($data) ? $data : []);
    }

    /**
     * GET /api/v1/reseller/a_products — A 站公开产品列表（供组合为分销产品）
     */
    public function aProducts(): JsonResponse
    {
        return $this->apiOk('A_PRODUCTS', '获取成功', $this->vpnA->getPublicProducts());
    }

    /**
     * GET /api/v1/reseller/products_merged — A 站产品列表 + 本分销商已设售价（成本价来自 A 站，仅可编辑售价）
     */
    public function productsMerged(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $aList = $this->vpnA->getPublicProducts();
        $myProducts = ResellerProduct::where('reseller_id', $resellerId)->get()->keyBy('source_product_id');
        $merged = [];
        foreach ($aList as $a) {
            $aId = (int) ($a['id'] ?? 0);
            $my = $myProducts->get($aId);
            $merged[] = [
                'a_product_id' => $aId,
                'a_name' => $a['name'] ?? '',
                'description' => $a['description'] ?? null,
                'cost_cents' => (int) ($a['price_cents'] ?? 0),
                'currency' => $a['currency'] ?? 'CNY',
                'duration_days' => (int) ($a['duration_days'] ?? 30),
                'my_product' => $my ? [
                    'id' => $my->id,
                    'name' => $my->name,
                    'price_cents' => (int) $my->price_cents,
                    'status' => $my->status,
                    'description' => $my->description,
                ] : null,
            ];
        }
        return $this->apiOk('RESELLER_PRODUCTS_MERGED', '获取成功', $merged);
    }

    /**
     * GET /api/v1/reseller/products — 当前分销商在 B 站配置的产品
     */
    public function products(Request $request): JsonResponse
    {
        $id = $this->resellerId($request);
        $list = ResellerProduct::where('reseller_id', $id)->orderBy('id')->get();
        return $this->apiOk('RESELLER_PRODUCTS', '获取成功', $list);
    }

    /**
     * POST /api/v1/reseller/products — 根据 A 站产品设置本地售卖信息（可自定义前台展示名称）
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $v = $request->validate([
            'source_product_id' => 'required|integer|min:1',
            'price_cents' => 'required|integer|min:0',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);
        $aList = $this->vpnA->getPublicProducts();
        $aId = (int) $v['source_product_id'];
        $aProduct = collect($aList)->first(fn ($a) => (int) ($a['id'] ?? 0) === $aId);
        if (!$aProduct) {
            abort(404, 'A 站未找到该产品');
        }
        $existing = ResellerProduct::where('reseller_id', $resellerId)
            ->where('source_product_id', $v['source_product_id'])->first();
        if ($existing) {
            $update = [
                'price_cents' => $v['price_cents'],
                'status' => 'active',
            ];
            if (array_key_exists('name', $v) && $v['name'] !== null && $v['name'] !== '') {
                $update['name'] = $v['name'];
            }
            if (array_key_exists('description', $v)) {
                $update['description'] = $v['description'];
            }
            $existing->update($update);
            return $this->apiOk('RESELLER_PRODUCT_UPSERTED', '保存成功', $existing);
        }
        $p = ResellerProduct::create([
            'reseller_id' => $resellerId,
            'source_product_id' => $v['source_product_id'],
            'cost_cents' => (int) ($aProduct['price_cents'] ?? 0),
            'name' => (array_key_exists('name', $v) && $v['name'] !== null && $v['name'] !== '')
                ? $v['name']
                : ($aProduct['name'] ?? 'Product #' . $v['source_product_id']),
            'description' => $v['description'] ?? ($aProduct['description'] ?? null),
            'price_cents' => $v['price_cents'],
            'currency' => $aProduct['currency'] ?? 'CNY',
            'duration_days' => (int) ($aProduct['duration_days'] ?? 30),
            'status' => 'active',
        ]);
        return $this->apiOk('RESELLER_PRODUCT_CREATED', '创建成功', $p, 201);
    }

    /**
     * PUT /api/v1/reseller/products/{id} — 允许修改名称、售价、状态与描述
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $p = ResellerProduct::where('reseller_id', $resellerId)->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'price_cents' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|in:active,disabled',
            'description' => 'sometimes|nullable|string',
        ]);
        $p->update($v);
        return $this->apiOk('RESELLER_PRODUCT_UPDATED', '更新成功', $p);
    }

    /**
     * DELETE /api/v1/reseller/products/{id}
     */
    public function destroyProduct(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $p = ResellerProduct::where('reseller_id', $resellerId)->findOrFail($id);
        $p->delete();
        return $this->apiOk('RESELLER_PRODUCT_DELETED', '删除成功', null);
    }

    /**
     * GET /api/v1/reseller/users — 在 B 站注册的用户（含本分销商产品订单数）
     */
    public function users(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $list = $this->resellerUserQuery($resellerId)
            ->withCount(['orders as orders_count' => function ($q) use ($resellerId) {
                $q->whereHas('resellerProduct', fn ($q2) => $q2->where('reseller_id', $resellerId));
            }])
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'created_at']);
        return $this->apiOk('RESELLER_USERS', '获取成功', $list);
    }

    /**
     * PUT /api/v1/reseller/users/{id}
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $user = $this->resellerUserQuery($resellerId)->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6|max:255',
        ]);
        if (array_key_exists('password', $v)) {
            $v['password'] = Hash::make($v['password']);
        }
        $user->update($v);
        return $this->apiOk('RESELLER_USER_UPDATED', '更新成功', $user->only(['id', 'name', 'email', 'created_at']));
    }

    /**
     * DELETE /api/v1/reseller/users/{id}
     */
    public function destroyUser(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $user = $this->resellerUserQuery($resellerId)->findOrFail($id);

        // 严格隔离：若该用户还购买过其他分销商的产品，则不允许在本分销商后台删除整条用户记录，
        // 避免级联删除影响其他分销商的数据。
        $hasOtherResellerOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', '!=', $resellerId))
            ->exists();
        if ($hasOtherResellerOrders) {
            return response()->json([
                'success' => false,
                'code' => 'CROSS_RESELLER_ORDERS_EXIST',
                'message' => '该用户存在其他分销商订单，无法在本分销商后台删除用户。请先处理其跨分销商订单。',
                'data' => [],
            ], 409);
        }

        $user->delete();
        return $this->apiOk('RESELLER_USER_DELETED', '删除成功', null);
    }

    /**
     * GET /api/v1/reseller/orders — 本分销商产品的订单列表
     */
    public function orders(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $list = Order::whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', $resellerId))
            ->with('user:id,name,email', 'resellerProduct:id,name,price_cents,currency', 'vpnSubscription:id,a_order_id')
            ->orderByDesc('id')
            ->get();

        return $this->apiOk('RESELLER_ORDERS', '获取成功', $list->map(function (Order $o) {
            $row = $o->toArray();
            $row['a_order_id'] = $o->vpnSubscription?->a_order_id;
            unset($row['vpn_subscription']);

            return $row;
        }));
    }

    /**
     * GET /api/v1/reseller/subscriptions — 本分销商下用户已购 VPN 产品（订阅）列表
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $list = UserVpnSubscription::query()
            ->whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', $resellerId))
            ->with('user:id,name,email', 'resellerProduct:id,name,price_cents,currency,source_product_id')
            ->withCount('orders')
            ->orderByDesc('id')
            ->get();

        return $this->apiOk('RESELLER_SUBSCRIPTIONS', '获取成功', $list);
    }

    /**
     * GET /api/v1/reseller/subscriptions/{id} — 订阅详情（含关联订单）+ A 站 WireGuard 配置
     */
    public function subscriptionShow(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $sub = UserVpnSubscription::query()
            ->whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', $resellerId))
            ->with(['user', 'resellerProduct', 'orders' => fn ($q) => $q->orderByDesc('id')])
            ->findOrFail($id);

        $product = collect($this->vpnA->getPublicProducts())->firstWhere('id', $sub->resellerProduct?->source_product_id);
        $enableRadius = VpnAValidateService::flagEnabled($product['enable_radius'] ?? null, true);
        $enableWg = VpnAValidateService::flagEnabled($product['enable_wireguard'] ?? null, true);

        $sslvpn = null;
        if ($enableRadius) {
            // 优先使用 subscription 表；若历史数据未落库，则回退使用 orders 中的 SSL VPN 信息。
            $login = null;
            $password = null;

            if (filled($sub->radius_login) && filled($sub->sslvpn_password)) {
                $login = $sub->radius_login;
                $password = $sub->sslvpn_password;
            } else {
                $order = $sub->orders->first(fn ($o) => filled($o->sslvpn_username) && filled($o->sslvpn_password));
                if ($order) {
                    $login = $order->sslvpn_username;
                    $password = $order->sslvpn_password;
                }
            }

            if (filled($login) && filled($password)) {
                $sslvpn = [
                    'login' => $login,
                    'password' => $password,
                    'gateway' => (string) (config('services.vpn_a.sslvpn_gateway') ?? ''),
                    'hint' => '完整登录名为「下单时填写的用户名@分销商ID」，与 A 站 FreeRADIUS 一致。',
                ];
            }
        }

        $wireguard = null;
        $wireguard_error = null;
        if ($enableWg) {
            if ($sub->user && $sub->user->email) {
                $wireguard = $this->vpnA->getWireguardConfig(
                    $sub->user->email,
                    $sub->a_order_id ? (int) $sub->a_order_id : null
                );
                if (!$wireguard || empty($wireguard['config'] ?? null)) {
                    $wireguard_error = '无法从 A 站获取 WireGuard 配置（可能未开通、Peer 未创建或密钥未在服务器保存）';
                }
            } else {
                $wireguard_error = '用户邮箱缺失，无法拉取配置';
            }
        }

        $aOrderStatus = $sub->a_order_id ? 'created' : 'missing';
        $sslStatus = $enableRadius ? (($sslvpn && filled($sslvpn['login'] ?? null) && filled($sslvpn['password'] ?? null)) ? 'synced' : 'missing') : 'disabled';
        $wireguardStatus = $enableWg ? (($wireguard && !empty($wireguard['config'] ?? null)) ? 'synced' : ($wireguard_error ? 'error' : 'missing')) : 'disabled';

        $overall = 'ready';
        if ($aOrderStatus !== 'created') {
            $overall = 'pending';
        }
        if ($enableRadius && $sslStatus !== 'synced') {
            $overall = 'pending';
        }
        if ($enableWg && $wireguardStatus !== 'synced') {
            $overall = 'pending';
        }

        Log::info('reseller subscription detail viewed', [
            'reseller_id' => $resellerId,
            'subscription_id' => $id,
            'a_order_id' => $sub->a_order_id,
            'enable_radius' => $enableRadius,
            'sslvpn_synced' => $sslStatus === 'synced',
            'enable_wireguard' => $enableWg,
            'wireguard_synced' => $wireguardStatus === 'synced',
        ]);

        return $this->apiOk('RESELLER_SUBSCRIPTION_DETAIL', '获取成功', [
            'subscription' => $sub,
            'enable_radius' => $enableRadius,
            'enable_wireguard' => $enableWg,
            'sslvpn' => $sslvpn,
            'wireguard' => $wireguard,
            'wireguard_error' => $wireguard_error,
            'provision_progress' => [
                'a_order' => $aOrderStatus,
                'sslvpn' => $sslStatus,
                'wireguard' => $wireguardStatus,
                'overall' => $overall,
            ],
        ]);
    }

    /**
     * POST /api/v1/reseller/subscriptions/{id}/sync-sslvpn
     *
     * 当 SSL VPN 本地同步缺失时：向 A 站重新获取 vpn_user（radius_username），并同步到 subscription 表。
     */
    public function syncSslVpnForSubscription(Request $request, int $id): JsonResponse
    {
        $resellerId = $this->resellerId($request);
        $sub = UserVpnSubscription::query()
            ->whereHas('resellerProduct', fn ($q) => $q->where('reseller_id', $resellerId))
            ->with(['user', 'resellerProduct', 'orders' => fn ($q) => $q->orderByDesc('id')])
            ->findOrFail($id);

        $product = collect($this->vpnA->getPublicProducts())->firstWhere('id', $sub->resellerProduct?->source_product_id);
        $enableRadius = VpnAValidateService::flagEnabled($product['enable_radius'] ?? null, true);
        if (!$enableRadius) {
            return response()->json([
                'success' => false,
                'code' => 'RADIUS_DISABLED',
                'message' => '当前产品未启用 SSL VPN',
                'data' => [],
            ], 422);
        }

        $order = $sub->orders->first(fn ($o) => $o->status === 'paid' && filled($o->sslvpn_username) && filled($o->sslvpn_password));
        if (!$order || !$sub->user) {
            return response()->json([
                'success' => false,
                'code' => 'MISSING_SSL_CREDS',
                'message' => '未找到可用于同步的订单 SSL VPN 凭据（可能为旧订单/数据异常）',
                'data' => [],
            ], 422);
        }

        if ((int) ($product['duration_days'] ?? 0) <= 0) {
            return response()->json([
                'success' => false,
                'code' => 'PRODUCT_INVALID',
                'message' => '无法获取 A 站产品周期信息',
                'data' => [],
            ], 422);
        }

        $durationDays = OrderBilling::totalProvisionDaysFromOrder($order);
        $region = $sub->region ?: (string) (config('services.vpn_a.default_region') ?? '');

        $payload = [
            'external_order_id' => (string) ($order->biz_order_no ?? ''),
            'user_email' => $sub->user->email,
            'user_name' => $sub->user->name,
            'product_id' => (int) ($sub->resellerProduct?->source_product_id),
            'duration_days' => $durationDays,
            'region' => $region ?: null,
            'sslvpn_username' => $order->sslvpn_username,
            'sslvpn_password' => $order->sslvpn_password,
        ];

        $result = $this->vpnA->provisionOrderResult($payload);
        if (!is_array($result)) {
            return response()->json([
                'success' => false,
                'code' => 'PROVISION_FAILED',
                'message' => '无法从 A 站同步 SSL VPN 信息，请稍后重试或联系客服',
                'data' => [],
            ], 502);
        }

        $vpnUser = $result['vpn_user'] ?? [];
        $aOrder = $result['order'] ?? [];

        $updated = false;
        if (empty($sub->a_order_id) && !empty($aOrder['id'])) {
            $sub->a_order_id = (int) $aOrder['id'];
            $updated = true;
        }
        if (empty($sub->radius_login) && !empty($vpnUser['radius_username'])) {
            $sub->radius_login = (string) $vpnUser['radius_username'];
            $updated = true;
        }
        if (empty($sub->sslvpn_password) && filled($order->sslvpn_password)) {
            $sub->sslvpn_password = $order->sslvpn_password;
            $updated = true;
        }

        if ($updated) {
            $sub->save();
        }

        Log::info('reseller sync sslvpn requested', [
            'reseller_id' => $resellerId,
            'subscription_id' => $id,
            'updated' => $updated,
            'sslvpn_synced' => filled($sub->radius_login) && filled($sub->sslvpn_password),
        ]);

        return $this->apiOk('RESELLER_SYNC_SSLVPN_OK', '同步成功', [
            'subscription_id' => $sub->id,
            'sslvpn' => [
                'login' => $sub->radius_login,
                'password' => $sub->sslvpn_password,
                'gateway' => (string) (config('services.vpn_a.sslvpn_gateway') ?? ''),
                'hint' => '完整登录名为「下单时填写的用户名@分销商ID」，与 A 站 FreeRADIUS 一致。',
            ],
        ]);
    }
}
