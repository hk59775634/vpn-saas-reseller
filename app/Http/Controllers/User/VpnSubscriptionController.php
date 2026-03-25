<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserVpnSubscription;
use App\Services\Epay\EpayService;
use App\Services\VpnAValidateService;
use App\Support\OrderBilling;
use App\Support\PaymentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VpnSubscriptionController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA
    ) {
        $this->middleware('auth');
    }

    /**
     * 已购 VPN 产品（与订单流水分离）
     */
    public function index(): View
    {
        $subscriptions = UserVpnSubscription::query()
            ->where('user_id', Auth::id())
            ->with('resellerProduct')
            ->orderByDesc('id')
            ->get();

        return view('user.subscriptions', ['subscriptions' => $subscriptions]);
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

    private function apiError(string $code, string $message, int $status = 422, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * 已购产品详情（SSL VPN 连接信息 / WireGuard 配置），按 A 站产品 enable_radius、enable_wireguard 返回。
     */
    public function detailJson(int $id): JsonResponse
    {
        $user = Auth::user();
        $sub = UserVpnSubscription::query()
            ->where('user_id', $user->id)
            ->with([
                'resellerProduct',
                'orders' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->findOrFail($id);

        $product = collect($this->vpnA->getPublicProducts())->firstWhere('id', $sub->resellerProduct?->source_product_id);
        $enableRadius = (bool) ($product['enable_radius'] ?? true);
        $enableWg = (bool) ($product['enable_wireguard'] ?? true);

        $out = [
            'subscription' => [
                'id' => $sub->id,
                'product_name' => $sub->resellerProduct?->name ?? '—',
                'region' => $sub->region,
                'a_order_id' => $sub->a_order_id,
                'expires_at' => $sub->expires_at?->toIso8601String(),
            ],
            'enable_radius' => $enableRadius,
            'enable_wireguard' => $enableWg,
            'sslvpn' => null,
            'wireguard' => null,
            'wireguard_error' => null,
        ];

        $sslStatus = $enableRadius ? 'missing' : 'disabled';
        $wireguardStatus = $enableWg ? 'missing' : 'disabled';

        if ($enableRadius) {
            // 优先使用 subscription 表；若历史数据未落库，则回退使用订单中的 SSL VPN 信息。
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
                $out['sslvpn'] = [
                    'login' => $login,
                    'password' => $password,
                    'gateway' => (string) (config('services.vpn_a.sslvpn_gateway') ?? ''),
                    'hint' => '登录名为「下单时填写的用户名@分销商ID」，与 A 站 RADIUS 一致。',
                ];
                $sslStatus = 'synced';
            }
        }

        if ($enableWg && $sub->a_order_id) {
            $wg = $this->vpnA->getWireguardConfig($user->email, (int) $sub->a_order_id);
            if (!is_array($wg) || empty($wg['config'] ?? null)) {
                $out['wireguard_error'] = '暂未生成 WireGuard 配置（可能产品未含 WireGuard 或 A 站尚未分配 Peer）';
                $wireguardStatus = !empty($wg) ? 'error' : 'missing';
            } else {
                $out['wireguard'] = $wg;
                $wireguardStatus = 'synced';
            }
        } elseif ($enableWg && !$sub->a_order_id) {
            $wireguardStatus = 'missing';
        }

        Log::info('user subscription detail viewed', [
            'subscription_id' => $id,
            'a_order_id' => $sub->a_order_id,
            'enable_radius' => $enableRadius,
            'sslvpn_synced' => $sslStatus === 'synced',
            'enable_wireguard' => $enableWg,
            'wireguard_synced' => $wireguardStatus === 'synced',
        ]);

        return $this->apiOk('SUBSCRIPTION_DETAIL', '获取成功', $out);
    }

    /**
     * 当检测到 SSL VPN 信息缺失时：向 A 站重新取回 vpn_user（包含 radius_username），并同步到本地。
     */
    public function syncSslVpn(int $subscriptionId): JsonResponse
    {
        $user = Auth::user();

        $sub = UserVpnSubscription::query()
            ->where('user_id', $user->id)
            ->with([
                'resellerProduct',
                'orders' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->findOrFail($subscriptionId);

        $product = collect($this->vpnA->getPublicProducts())->firstWhere(
            'id',
            $sub->resellerProduct?->source_product_id
        );

        $enableRadius = (bool) ($product['enable_radius'] ?? true);
        if (!$enableRadius) {
            return $this->apiError('RADIUS_DISABLED', '当前产品未启用 SSL VPN', 422);
        }

        $order = $sub->orders->first(fn ($o) => $o->status === 'paid' && filled($o->sslvpn_username) && filled($o->sslvpn_password));
        if (!$order) {
            return $this->apiError('MISSING_SSL_CREDS', '未找到可用于同步的订单 SSL VPN 凭据（可能为旧订单/数据异常）', 422);
        }

        if ((int) ($product['duration_days'] ?? 0) <= 0) {
            return $this->apiError('PRODUCT_INVALID', '无法获取 A 站产品周期信息', 422);
        }

        $durationDays = OrderBilling::totalProvisionDaysFromOrder($order);
        $region = $sub->region ?: (string) (config('services.vpn_a.default_region') ?? '');

        $payload = [
            'external_order_id' => (string) ($order->biz_order_no ?? ''),
            'user_email' => $user->email,
            'user_name' => $user->name,
            'product_id' => (int) ($sub->resellerProduct?->source_product_id),
            'duration_days' => $durationDays,
            'region' => $region ?: null,
            'sslvpn_username' => $order->sslvpn_username,
            'sslvpn_password' => $order->sslvpn_password,
        ];

        $result = $this->vpnA->provisionOrderResult($payload);
        if (!is_array($result)) {
            return $this->apiError('PROVISION_FAILED', '无法从 A 站同步 SSL VPN 信息，请稍后重试或联系客服', 502);
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

        return $this->apiOk('SYNC_SSLVPN_OK', '同步成功', [
            'subscription_id' => $sub->id,
            'sslvpn' => [
                'login' => $sub->radius_login,
                'password' => $sub->sslvpn_password,
                'gateway' => (string) (config('services.vpn_a.sslvpn_gateway') ?? ''),
                'hint' => '登录名为「下单时填写的用户名@分销商ID」，与 A 站 RADIUS 一致。',
            ],
        ]);
    }

    /**
     * 续费表单页（选择时长后再提交创建订单）。
     */
    public function showRenewForm(int $subscriptionId): View|RedirectResponse
    {
        $subscription = UserVpnSubscription::query()
            ->where('user_id', Auth::id())
            ->with('resellerProduct')
            ->findOrFail($subscriptionId);

        if (!$subscription->a_order_id) {
            return redirect()->route('user.subscriptions')->with('error', '该产品尚未在 A 站完成开通，无法续费');
        }

        $product = $subscription->resellerProduct;
        if (!$product || $product->status !== 'active') {
            return redirect()->route('user.subscriptions')->with('error', '该产品当前不可续费');
        }

        $renewUnitDays = max(1, (int) $product->duration_days);

        return view('user.subscription_renew', [
            'subscription' => $subscription,
            'product' => $product,
            'renewUnitDays' => $renewUnitDays,
        ]);
    }

    /**
     * 续费：先创建待支付订单；易支付就绪则跳转收银台，支付成功后由回调开通 A 站续期。
     * 时长单位与产品计费周期一致：每周期 1 天则选 N 天；每周期大于 1 天则选 N 个周期（不可按天续费）。
     */
    public function renew(Request $request, int $subscriptionId): RedirectResponse
    {
        $subscription = UserVpnSubscription::query()
            ->where('user_id', Auth::id())
            ->with('resellerProduct')
            ->findOrFail($subscriptionId);

        if (!$subscription->a_order_id) {
            return redirect()->route('user.subscriptions')->with('error', '该产品尚未在 A 站完成开通，无法续费');
        }

        $product = $subscription->resellerProduct;
        if (!$product || $product->status !== 'active') {
            return redirect()->route('user.subscriptions')->with('error', '该产品当前不可续费');
        }

        $unitDays = max(1, (int) $product->duration_days);
        if ($unitDays === 1) {
            $request->validate([
                'renew_days' => 'required|integer|min:1|max:365',
            ]);
            $periodCount = (int) $request->input('renew_days');
        } else {
            $request->validate([
                'renew_months' => 'required|integer|min:1|max:12',
            ]);
            $periodCount = (int) $request->input('renew_months');
        }

        $user = Auth::user();
        $amountCents = $periodCount * (int) $product->price_cents;

        $incomeOrder = Order::create([
            'user_id' => $user->id,
            'reseller_product_id' => $subscription->reseller_product_id,
            'biz_order_no' => (string) Str::ulid(),
            'user_vpn_subscription_id' => $subscription->id,
            'duration_months' => $periodCount,
            'amount_cents' => $amountCents,
            'currency' => $product->currency,
            'region' => $subscription->region,
            'status' => 'pending',
        ]);

        if (PaymentConfig::enabled()) {
            $svc = EpayService::fromConfig();
            if ($svc !== null && $svc->isConfigured()) {
                return redirect()->route('user.orders.pay.epay', $incomeOrder->id)
                    ->with('message', '正在跳转易支付完成续费…');
            }
        }

        return redirect()->route('user.orders')->with('message', '续费订单已创建，请在订单流水中完成支付');
    }
}
