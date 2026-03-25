<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B 站订单 = 收入流水（每笔支付一条，含续费）
 */
class Order extends Model
{
    protected $fillable = [
        'user_id',
        'reseller_product_id',
        'biz_order_no',
        'user_vpn_subscription_id',
        'amount_cents',
        'currency',
        'region',
        'duration_months',
        'sslvpn_username',
        'sslvpn_password',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'sslvpn_password' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resellerProduct(): BelongsTo
    {
        return $this->belongsTo(ResellerProduct::class, 'reseller_product_id');
    }

    public function vpnSubscription(): BelongsTo
    {
        return $this->belongsTo(UserVpnSubscription::class, 'user_vpn_subscription_id');
    }
}
