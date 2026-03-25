<?php

namespace App\Support;

use App\Models\Order;

/**
 * 订单时长与金额：与产品「每周期天数」及订单字段 duration_months 对齐。
 * 每周期 1 天：duration_months 存「天数」1～365。
 * 每周期大于 1 天：duration_months 存「周期数」1～12，总天数 = 周期数 × 每周期天数。
 */
class OrderBilling
{
    public static function totalProvisionDaysFromOrder(Order $order): int
    {
        $order->loadMissing('resellerProduct');
        $product = $order->resellerProduct;
        $unitDays = max(1, (int) ($product?->duration_days ?? 30));
        $count = (int) ($order->duration_months ?? 1);
        if ($unitDays === 1) {
            return max(1, min(365, $count >= 1 ? $count : 1));
        }
        $periods = max(1, min(12, $count >= 1 ? $count : 1));

        return $periods * $unitDays;
    }
}
