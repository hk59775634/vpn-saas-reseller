<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Epay\EpayService;
use App\Services\VpnAValidateService;
use App\Support\OrderPaySuccessHandler;
use App\Support\PaymentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA
    ) {}

    /**
     * 创建支付单。当前仅支持易支付、模拟支付。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'provider' => 'required|string|in:epay,simulated',
        ]);

        $order = Order::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->findOrFail($data['order_id']);

        $payment = Payment::create([
            'user_id' => Auth::id(),
            'order_id' => $order->id,
            'provider' => $data['provider'],
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
        ]);

        if ($payment->provider === Payment::PROVIDER_EPAY) {
            $payload = $this->createEpayOrder($payment, $order);
        } elseif ($payment->provider === Payment::PROVIDER_SIMULATED) {
            $payload = $this->createSimulatedOrder($payment, $order);
        } else {
            $payload = [
                'type' => 'unsupported',
                'message' => '暂未启用该支付渠道。',
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'PAYMENT_CREATED',
            'message' => '支付单创建成功',
            'data' => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'payload' => $payload,
            ],
        ], 201);
    }

    /**
     * 查询支付状态（前端可轮询）。
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorizePayment($payment);

        $order = $payment->order()->with('vpnSubscription:id,a_order_id')->first();
        $provisionStatus = 'pending';
        if ($order && $order->status === 'paid') {
            $provisionStatus = $order->user_vpn_subscription_id ? 'ready' : 'provisioning';
        }

        return response()->json([
            'success' => true,
            'code' => 'PAYMENT_STATUS',
            'message' => '支付状态获取成功',
            'data' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'order' => [
                    'id' => $order?->id,
                    'status' => $order?->status,
                    'user_vpn_subscription_id' => $order?->user_vpn_subscription_id,
                    'a_order_id' => $order?->vpnSubscription?->a_order_id,
                    'provision_status' => $provisionStatus,
                ],
            ],
        ]);
    }

    /**
     * 彩虹易支付异步通知（MD5 签名校验）
     */
    public function webhookEpay(Request $request): Response
    {
        if (!PaymentConfig::enabled()) {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $service = EpayService::fromConfig();
        if ($service === null || !$service->isConfigured()) {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $params = array_merge($request->query->all(), $request->request->all());

        if (!$service->verifySign($params)) {
            Log::warning('B epay notify: bad sign');

            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$service->pidMatches($params['pid'] ?? '')) {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $outTradeNo = (string) ($params['out_trade_no'] ?? '');
        if ($outTradeNo === '') {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $tradeStatus = strtoupper((string) ($params['trade_status'] ?? ''));
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $moneyStr = (string) ($params['money'] ?? '');
        if ($moneyStr === '' || !is_numeric($moneyStr)) {
            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $moneyCents = (int) round((float) $moneyStr * 100);
        $tradeNo = (string) ($params['trade_no'] ?? '');

        $payment = Payment::query()
            ->where('provider', Payment::PROVIDER_EPAY)
            ->where('status', Payment::STATUS_PENDING)
            ->where('meta->out_trade_no', $outTradeNo)
            ->first();

        if (!$payment) {
            Log::warning('B epay notify: payment not found', ['out_trade_no' => $outTradeNo]);

            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ((int) $payment->amount_cents !== $moneyCents) {
            Log::warning('B epay notify: amount mismatch', [
                'out_trade_no' => $outTradeNo,
                'expected' => $payment->amount_cents,
                'got' => $moneyCents,
            ]);

            return response('fail', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ($payment->status === Payment::STATUS_SUCCEED) {
            return response('success', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $payment->update([
            'status' => Payment::STATUS_SUCCEED,
            'provider_payment_id' => $tradeNo !== '' ? $tradeNo : null,
            'meta' => array_merge($payment->meta ?? [], [
                'notify_raw' => $params,
            ]),
        ]);

        $this->markOrderPaidAndProvision($payment->order);

        return response('success', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function createEpayOrder(Payment $payment, Order $order): array
    {
        if (!PaymentConfig::enabled()) {
            return ['type' => 'error', 'message' => '易支付未开启。'];
        }

        $svc = EpayService::fromConfig();
        if ($svc === null || !$svc->isConfigured()) {
            return ['type' => 'error', 'message' => '易支付未配置完整（网关 / 商户 ID / 密钥）。'];
        }

        $outTradeNo = 'B'.$payment->id.'_'.bin2hex(random_bytes(5));
        if (strlen($outTradeNo) > 64) {
            $outTradeNo = substr($outTradeNo, 0, 64);
        }

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], ['out_trade_no' => $outTradeNo]),
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

        return [
            'type' => 'redirect',
            'url' => $payUrl,
        ];
    }

    private function createSimulatedOrder(Payment $payment, Order $order): array
    {
        if (!PaymentConfig::allowSimulatedPayment()) {
            return ['type' => 'error', 'message' => '模拟支付已关闭。'];
        }

        $payment->update([
            'status' => Payment::STATUS_SUCCEED,
            'provider_payment_id' => 'simulated',
            'meta' => array_merge($payment->meta ?? [], ['channel' => 'simulated']),
        ]);

        $this->markOrderPaidAndProvision($order);

        return [
            'type' => 'ok',
            'message' => '模拟支付完成',
        ];
    }

    private function authorizePayment(Payment $payment): void
    {
        if ($payment->user_id !== Auth::id()) {
            abort(403, '无权访问该支付单');
        }
    }

    private function markOrderPaidAndProvision(Order $order): void
    {
        if ($order->status === 'paid') {
            return;
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $order->refresh();

        OrderPaySuccessHandler::provisionOnly($order, $this->vpnA);
    }
}
