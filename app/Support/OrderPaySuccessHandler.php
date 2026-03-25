<?php

namespace App\Support;

use App\Models\Order;
use App\Models\UserVpnSubscription;
use App\Services\VpnAValidateService;
use App\Support\OrderBilling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 订单已标记为 paid 后：调用 A 站开通新订阅或续费（与模拟支付 / 易支付回调共用）
 */
class OrderPaySuccessHandler
{
    public static function provisionOnly(Order $order, VpnAValidateService $vpnA): bool
    {
        $order->loadMissing('resellerProduct', 'user');

        if (empty($order->biz_order_no)) {
            $order->update(['biz_order_no' => (string) Str::ulid()]);
            $order->refresh();
        }

        $product = $order->resellerProduct;
        $user = $order->user;
        if (!$product || !$product->source_product_id || !$user) {
            return false;
        }

        $durationDays = OrderBilling::totalProvisionDaysFromOrder($order);

        $payload = [
            'external_order_id' => (string) $order->biz_order_no,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'product_id' => $product->source_product_id,
            'duration_days' => $durationDays,
        ];
        $region = $order->region ?: config('services.vpn_a.default_region');
        if ($region) {
            $payload['region'] = $region;
        }

        // 含 SSL 的产品：新购必须传 sslvpn_*。勿用 if ($order->sslvpn_username) —— 在 PHP 中字符串 "0" 会被当成 false 从而漏传字段。
        // 续费订单（user_vpn_subscription_id）在 B 站创建时不带 sslvpn_*，与 A 站 ResellerProvisionController 一致：续费不要求 sslvpn（沿用原 vpn_user / RADIUS）。
        $public = collect($vpnA->getPublicProducts())->keyBy('id');
        $sid = (int) $product->source_product_id;
        $src = $public->get($sid) ?? $public->firstWhere('id', $sid);
        // A 站 JSON 中 enable_radius 常为 1/0；若公开列表拉取失败但订单上已有 SSL 字段也要下发
        $catalogNeedsSsl = $src && VpnAValidateService::flagEnabled($src['enable_radius'] ?? null, true);
        $orderHasSsl = filled($order->sslvpn_username) && filled($order->sslvpn_password);
        $isRenewOrder = (bool) $order->user_vpn_subscription_id;

        if ((!$isRenewOrder && $catalogNeedsSsl) || $orderHasSsl) {
            if (!filled($order->sslvpn_username) || !filled($order->sslvpn_password)) {
                Log::warning('OrderPaySuccessHandler: 产品需 SSL 但订单缺少账号或密码', [
                    'order_id' => $order->id,
                    'has_username' => filled($order->sslvpn_username),
                    'has_password' => filled($order->sslvpn_password),
                    'catalog_needs_ssl' => $catalogNeedsSsl,
                    'is_renew' => $isRenewOrder,
                ]);

                return false;
            }
            $payload['sslvpn_username'] = $order->sslvpn_username;
            $payload['sslvpn_password'] = $order->sslvpn_password;
        }

        // 续费订单：已关联本地已购产品
        if ($order->user_vpn_subscription_id) {
            $sub = UserVpnSubscription::query()
                ->where('user_id', $order->user_id)
                ->where('id', $order->user_vpn_subscription_id)
                ->first();
            if (!$sub || !$sub->a_order_id) {
                return false;
            }
            $payload['target_a_order_id'] = (int) $sub->a_order_id;
            $result = $vpnA->provisionOrderResult($payload);
            if (!is_array($result)) {
                return false;
            }
            $aOrder = $result['order'] ?? [];
            $sub->applyRenewFromProvision($aOrder);

            return true;
        }

        if (UserVpnSubscription::hasSubscriptionForProduct((int) $order->user_id, (int) $order->reseller_product_id)) {
            return false;
        }

        $result = $vpnA->provisionOrderResult($payload);
        if (!is_array($result)) {
            return false;
        }
        $aOrder = $result['order'] ?? [];
        if (empty($aOrder['id'])) {
            return false;
        }
        UserVpnSubscription::createFromFirstProvision($order, $aOrder, $result);

        return true;
    }
}
