<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ResellerProduct;
use App\Models\UserVpnSubscription;
use App\Services\Epay\EpayService;
use App\Services\VpnAValidateService;
use App\Support\OrderBilling;
use App\Support\OrderPaySuccessHandler;
use App\Support\PaymentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA
    ) {
        $this->middleware('auth')->only(['index', 'showConfirm', 'store', 'pay', 'payEpay', 'retryProvision', 'vpnIdentity']);
    }

    /**
     * 购买确认页：选择线路；含 SSL VPN 时在此填写账号密码，确认后 POST 创建订单并进入支付。
     */
    public function showConfirm(Request $request, int $reseller_product): View|RedirectResponse
    {
        $product = ResellerProduct::where('status', 'active')->findOrFail($reseller_product);

        if (UserVpnSubscription::hasSubscriptionForProduct(Auth::id(), $product->id)) {
            return redirect()->route('user.products')->with('error', '您已有该产品的订阅，请前往「已购产品」续费，无需重复下单。');
        }

        $public = collect($this->vpnA->getPublicProducts())->keyBy('id');
        $src = $public->get($product->source_product_id);
        if ($src === null) {
            return redirect()->route('user.products')->with('error', '无法获取产品信息，请稍后重试');
        }

        $enableRadius = VpnAValidateService::flagEnabled($src['enable_radius'] ?? null, true);
        $enableWireguard = VpnAValidateService::flagEnabled($src['enable_wireguard'] ?? null, true);

        $vpnIdentity = null;
        if ($enableRadius) {
            $vpnIdentity = $this->vpnA->getSiteVpnIdentityForSsl();
            if (!$vpnIdentity['ok']) {
                return redirect()->route('user.products')->with(
                    'error',
                    $vpnIdentity['message'] ?? '无法从 A 站获取分销商信息（GET /api/v1/reseller/me）'
                );
            }
            if ((int) $product->reseller_id !== (int) $vpnIdentity['reseller_id']) {
                abort(403, '该产品归属与当前 API Key 在 A 站对应的分销商（GET /api/v1/reseller/me）不一致。');
            }
        } else {
            // 与 ProductController 一致：分销商 ID 以 A 站 GET /reseller/me 为准，未取到时再读 VPN_A_RESELLER_ID
            if ((int) $product->reseller_id !== $this->effectiveResellerIdForPurchase()) {
                abort(403, '该产品不可购买');
            }
        }

        $regions = $this->vpnA->getPublicRegions();
        $defaultRegion = $request->query('region');
        if ($defaultRegion === null || $defaultRegion === '') {
            $defaultRegion = (string) (config('services.vpn_a.default_region') ?? '');
        }

        return view('user.order_confirm', [
            'product' => $product,
            'regions' => $regions,
            'defaultRegion' => (string) $defaultRegion,
            'enable_radius' => $enableRadius,
            'enable_wireguard' => $enableWireguard,
            'vpn_identity' => $vpnIdentity,
        ]);
    }

    /**
     * 当前站点解析出的 SSL 登录名后缀（与开通时一致），供前端展示或调试。
     */
    public function vpnIdentity(): JsonResponse
    {
        $payload = $this->vpnA->getSiteVpnIdentityForSsl();

        return response()->json($payload, ($payload['ok'] ?? false) ? 200 : 503);
    }

    /**
     * 订单流水（收入记录）
     */
    public function index(): View
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['resellerProduct', 'vpnSubscription:id,a_order_id'])
            ->orderByDesc('id')
            ->get();

        $epaySvc = EpayService::fromConfig();

        return view('user.orders', [
            'orders' => $orders,
            'epay_enabled' => PaymentConfig::enabled(),
            'epay_ready' => $epaySvc !== null && $epaySvc->isConfigured(),
            'allow_simulated_payment' => PaymentConfig::allowSimulatedPayment(),
        ]);
    }

    /**
     * 创建订单（购买产品），状态为 pending
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'reseller_product_id' => 'required|exists:reseller_products,id',
            'region' => 'nullable|string|max:64',
        ]);
        $product = ResellerProduct::where('status', 'active')->findOrFail($data['reseller_product_id']);

        $unitDays = max(1, (int) $product->duration_days);
        $durationRule = $unitDays === 1
            ? 'required|integer|min:1|max:365'
            : 'required|integer|min:1|max:12';
        $data = array_merge($data, $request->validate([
            'duration_months' => $durationRule,
        ]));

        $public = collect($this->vpnA->getPublicProducts())->keyBy('id');
        $src = $public->get($product->source_product_id);
        if ($src === null) {
            if ($request->expectsJson()) {
                return $this->apiError('PRODUCT_SYNC_FAILED', '无法获取产品信息，请稍后重试');
            }
            return redirect()->route('user.products')->with('error', '无法获取产品信息，请稍后重试');
        }
        $needSsl = VpnAValidateService::flagEnabled($src['enable_radius'] ?? null, true);

        if ($needSsl) {
            $vpnIdentity = $this->vpnA->getSiteVpnIdentityForSsl();
            if (!$vpnIdentity['ok']) {
                if ($request->expectsJson()) {
                    return $this->apiError(
                        'VPN_IDENTITY_UNAVAILABLE',
                        $vpnIdentity['message'] ?? '无法从 A 站获取分销商信息（GET /api/v1/reseller/me）',
                        503
                    );
                }
                return redirect()->route('user.products')->with(
                    'error',
                    $vpnIdentity['message'] ?? '无法从 A 站获取分销商信息（GET /api/v1/reseller/me）'
                );
            }
            if ((int) $product->reseller_id !== (int) $vpnIdentity['reseller_id']) {
                if ($request->expectsJson()) {
                    return $this->apiError('FORBIDDEN_PRODUCT', '该产品不可购买', 403);
                }
                abort(403, '该产品不可购买');
            }
        } else {
            if ((int) $product->reseller_id !== $this->effectiveResellerIdForPurchase()) {
                if ($request->expectsJson()) {
                    return $this->apiError('FORBIDDEN_PRODUCT', '该产品不可购买', 403);
                }
                abort(403, '该产品不可购买');
            }
        }

        if ($needSsl) {
            $data = array_merge($data, $request->validate([
                'sslvpn_username' => 'required|string|max:64|regex:/^[a-zA-Z0-9._-]+$/',
                'sslvpn_password' => 'required|string|min:8|max:128',
            ]));
        } else {
            $data['sslvpn_username'] = null;
            $data['sslvpn_password'] = null;
        }

        if (UserVpnSubscription::hasSubscriptionForProduct(Auth::id(), $product->id)) {
            if ($request->expectsJson()) {
                return $this->apiError('SUBSCRIPTION_EXISTS', '您已有该产品的订阅，请前往「已购产品」续费，无需重复下单。', 409);
            }
            return redirect()->route('user.products')->with('error', '您已有该产品的订阅，请前往「已购产品」续费，无需重复下单。');
        }

        $rawCount = (int) $data['duration_months'];
        if ($unitDays === 1) {
            $periodOrDayCount = max(1, min(365, $rawCount));
        } else {
            $periodOrDayCount = max(1, min(12, $rawCount));
        }
        $amountCents = (int) $product->price_cents * $periodOrDayCount;

        $order = Order::create([
            'user_id' => Auth::id(),
            'reseller_product_id' => $product->id,
            'biz_order_no' => (string) Str::ulid(),
            'amount_cents' => $amountCents,
            'currency' => $product->currency,
            'region' => ($data['region'] ?? null) ?: null,
            'duration_months' => $periodOrDayCount,
            'sslvpn_username' => $data['sslvpn_username'] ?? null,
            'sslvpn_password' => $data['sslvpn_password'] ?? null,
            'status' => 'pending',
        ]);

        // 易支付已就绪时直接进入收银台，避免用户误点「模拟支付」
        if (PaymentConfig::enabled()) {
            $svc = EpayService::fromConfig();
            if ($svc !== null && $svc->isConfigured()) {
                if ($request->expectsJson()) {
                    return $this->apiOk('ORDER_CREATED', '订单已创建，正在跳转易支付', [
                        'order_id' => $order->id,
                        'status' => $order->status,
                        'biz_order_no' => $order->biz_order_no,
                        'next' => [
                            'type' => 'redirect',
                            'url' => route('user.orders.pay.epay', $order->id),
                        ],
                    ], 201);
                }
                return redirect()->route('user.orders.pay.epay', $order->id)
                    ->with('message', '正在跳转易支付…');
            }
        }

        if ($request->expectsJson()) {
            return $this->apiOk('ORDER_CREATED', '订单已创建，请完成支付', [
                'order_id' => $order->id,
                'status' => $order->status,
                'biz_order_no' => $order->biz_order_no,
            ], 201);
        }

        return redirect()->route('user.orders')->with('message', '订单已创建，请完成支付');
    }

    /**
     * 支付成功 → 开通 A 站订阅 → 创建「已购产品」记录（与订单分离）
     */
    public function pay(Request $request, int $id): RedirectResponse
    {
        if (!PaymentConfig::allowSimulatedPayment()) {
            return redirect()->route('user.orders')->with('error', '模拟支付已关闭，请使用易支付在线付款。');
        }

        $order = Order::where('user_id', Auth::id())->with('resellerProduct')->findOrFail($id);
        if ($redirect = $this->redirectUnlessOrderPayable($order)) {
            return $redirect;
        }

        $order->update(['status' => 'paid', 'paid_at' => now()]);
        $order->refresh();

        $success = OrderPaySuccessHandler::provisionOnly($order, $this->vpnA);

        $message = $success
            ? ($order->user_vpn_subscription_id
                ? '支付成功，续费已生效。请前往「已购产品」查看到期时间。'
                : '支付成功，服务已开通。请前往「已购产品」查看，「下载」页获取配置。')
            : '支付成功，自动开通暂时失败，可在订单页尝试「补开通」或联系客服。';

        return redirect()->route('user.orders')->with('message', $message);
    }

    /**
     * 跳转彩虹易支付收银台（创建 Payment 记录后 redirect）
     */
    public function payEpay(Request $request, int $id): RedirectResponse
    {
        if (!PaymentConfig::enabled()) {
            return redirect()->route('user.orders')->with('error', '易支付未开启');
        }

        $svc = EpayService::fromConfig();
        if ($svc === null || !$svc->isConfigured()) {
            return redirect()->route('user.orders')->with('error', '易支付未配置完整，请联系分销商管理员。');
        }

        $order = Order::where('user_id', Auth::id())->with('resellerProduct')->findOrFail($id);
        if ($redirect = $this->redirectUnlessOrderPayable($order)) {
            return $redirect;
        }

        Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', Payment::PROVIDER_EPAY)
            ->where('status', Payment::STATUS_PENDING)
            ->delete();

        $payment = Payment::create([
            'user_id' => Auth::id(),
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_EPAY,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
        ]);

        $outTradeNo = 'B'.$payment->id.'_'.bin2hex(random_bytes(5));
        if (strlen($outTradeNo) > 64) {
            $outTradeNo = substr($outTradeNo, 0, 64);
        }

        $payment->update([
            'meta' => ['out_trade_no' => $outTradeNo],
        ]);

        $money = number_format($payment->amount_cents / 100, 2, '.', '');
        $name = 'VPN 订单 #'.$order->id;
        $payUrl = $svc->buildPayUrl(
            $outTradeNo,
            $name,
            $money,
            PaymentConfig::notifyUrl(),
            PaymentConfig::returnUrl(),
        );

        return redirect()->away($payUrl);
    }

    /**
     * 已支付但未生成「已购产品」时，按订单业务号向 A 站补开通。
     */
    public function retryProvision(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $order = Order::where('user_id', Auth::id())
            ->where('status', 'paid')
            ->with('resellerProduct')
            ->findOrFail($id);

        if ($order->user_vpn_subscription_id) {
            if ($request->expectsJson()) {
                return $this->apiError('ORDER_ALREADY_LINKED', '该订单已关联已购产品，无需补开通', 409);
            }

            return redirect()->route('user.orders')->with('error', '该订单已关联已购产品，无需补开通');
        }

        if (UserVpnSubscription::hasSubscriptionForProduct(Auth::id(), $order->reseller_product_id)) {
            if ($request->expectsJson()) {
                return $this->apiError('SUBSCRIPTION_EXISTS', '您已有该产品的订阅，请前往「已购产品」续费', 409);
            }
            return redirect()->route('user.orders')->with('error', '您已有该产品的订阅，请前往「已购产品」续费');
        }

        $product = $order->resellerProduct;
        $user = Auth::user();
        if (empty($order->biz_order_no)) {
            $order->update(['biz_order_no' => (string) Str::ulid()]);
            $order->refresh();
        }

        $durationDays = OrderBilling::totalProvisionDaysFromOrder($order);

        $public = collect($this->vpnA->getPublicProducts())->keyBy('id');
        $src = $public->get($product?->source_product_id);
        $needSsl = $src && VpnAValidateService::flagEnabled($src['enable_radius'] ?? null, true);

        /** 含 SSL 的产品补开通必须带上确认页保存的账号密码；订单里若为空则无法调用 A 站（否则会报 sslvpn username required） */
        if ($needSsl) {
            $hasUser = filled($order->sslvpn_username);
            $hasPass = filled($order->sslvpn_password);
            if (!$hasUser || !$hasPass) {
                if ($request->expectsJson()) {
                    return $this->apiError(
                        'SSL_CREDENTIALS_MISSING',
                        '无法补开通：该订单未保存 SSL VPN 用户名或密码（可能为旧订单、数据异常或密钥变更导致无法解密）。',
                        422
                    );
                }
                return redirect()->route('user.orders')->with(
                    'error',
                    '无法补开通：该订单未保存 SSL VPN 用户名或密码（可能为旧订单、数据异常或密钥变更导致无法解密）。请「联系客服」或重新下单；若仍要补开通，需人工在后台核对订单与 A 站。'
                );
            }
        }

        $payload = [
            'external_order_id' => (string) $order->biz_order_no,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'product_id' => $product?->source_product_id,
            'duration_days' => $durationDays,
        ];
        $region = $order->region ?: config('services.vpn_a.default_region');
        if ($region) {
            $payload['region'] = $region;
        }
        if (filled($order->sslvpn_username)) {
            $payload['sslvpn_username'] = $order->sslvpn_username;
        }
        if (filled($order->sslvpn_password)) {
            $payload['sslvpn_password'] = $order->sslvpn_password;
        }

        $resp = $product && $product->source_product_id ? $this->vpnA->provisionOrderResponse($payload) : null;
        if ($resp === null || !$resp['success'] || !is_array($resp['data'])) {
            $msg = is_array($resp) ? ($resp['message'] ?? '未知错误') : '无法请求 A 站';
            if ($request->expectsJson()) {
                return $this->apiError('A_PROVISION_FAILED', '补开通失败：' . $msg, 502);
            }

            return redirect()->route('user.orders')->with('error', '补开通失败：' . $msg);
        }
        $result = $resp['data'];
        $aOrder = $result['order'] ?? [];
        if (empty($aOrder['id'])) {
            if ($request->expectsJson()) {
                return $this->apiError('A_ORDER_ID_MISSING', '补开通失败：A 站未返回订单 ID（' . ($resp['message'] ?? '') . '）', 502);
            }
            return redirect()->route('user.orders')->with('error', '补开通失败：A 站未返回订单 ID（' . ($resp['message'] ?? '') . '）');
        }

        $sub = UserVpnSubscription::createFromFirstProvision($order, $aOrder, $result);

        if ($request->expectsJson()) {
            return $this->apiOk('PROVISION_RETRIED', '补开通成功，已生成已购产品', [
                'subscription_id' => $sub->id,
                'a_order_id' => $sub->a_order_id,
            ]);
        }

        return redirect()->route('user.subscriptions')->with('message', '补开通成功，已生成已购产品');
    }

    /**
     * 新购：若已有同产品已购记录（含已过期）则不可再付；续费订单（已关联 user_vpn_subscription_id）允许支付。
     */
    private function redirectUnlessOrderPayable(Order $order): ?RedirectResponse
    {
        if ($order->status !== 'pending') {
            return redirect()->route('user.orders')->with('error', '订单状态不允许支付');
        }

        if ($order->user_vpn_subscription_id) {
            $sub = UserVpnSubscription::query()
                ->where('user_id', Auth::id())
                ->where('id', $order->user_vpn_subscription_id)
                ->first();
            if (!$sub || (int) $sub->reseller_product_id !== (int) $order->reseller_product_id) {
                return redirect()->route('user.orders')->with('error', '订单无效');
            }

            return null;
        }

        if (UserVpnSubscription::hasSubscriptionForProduct(Auth::id(), $order->reseller_product_id)) {
            return redirect()->route('user.orders')->with('error', '您已有该产品的订阅，请前往「已购产品」续费。');
        }

        return null;
    }

    /**
     * 本 B 站当前 API Key 在 A 站对应的分销商 ID（与前台列表筛选一致）。
     */
    private function effectiveResellerIdForPurchase(): int
    {
        return $this->vpnA->fetchResellerIdFromMe() ?? (int) config('services.vpn_a.reseller_id', 1);
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

    private function apiError(string $code, string $message, int $status = 422, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
