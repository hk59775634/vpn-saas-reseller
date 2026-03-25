<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 用户已购 VPN 产品（与 A 站一条订阅订单对应，续费只更新本记录）
 */
class UserVpnSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'reseller_product_id',
        'a_order_id',
        'region',
        'activated_at',
        'last_renewed_at',
        'expires_at',
        'status',
        'radius_login',
        'sslvpn_password',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_vpn_subscription_id');
    }

    /** 是否已有有效服务（未过期且 active） */
    public static function hasActiveForProduct(int $userId, int $resellerProductId): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('reseller_product_id', $resellerProductId)
            ->whereNotNull('a_order_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * 是否已有该产品的已购记录（含已过期）。同用户同产品只允许一条订阅，禁止重复新购。
     */
    public static function hasSubscriptionForProduct(int $userId, int $resellerProductId): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('reseller_product_id', $resellerProductId)
            ->exists();
    }

    public static function createFromFirstProvision(Order $order, array $aOrder, ?array $provisionResult = null): self
    {
        $vpnUser = is_array($provisionResult) ? ($provisionResult['vpn_user'] ?? []) : [];

        $sub = self::create([
            'user_id' => $order->user_id,
            'reseller_product_id' => $order->reseller_product_id,
            'a_order_id' => (int) $aOrder['id'],
            'region' => $order->region,
            'activated_at' => now(),
            'expires_at' => !empty($aOrder['expires_at']) ? $aOrder['expires_at'] : null,
            'status' => 'active',
            'radius_login' => $vpnUser['radius_username'] ?? null,
            'sslvpn_password' => $order->sslvpn_password,
        ]);
        $order->update(['user_vpn_subscription_id' => $sub->id]);

        return $sub;
    }

    public function applyRenewFromProvision(array $aOrder): void
    {
        $this->update([
            'last_renewed_at' => now(),
            'expires_at' => !empty($aOrder['expires_at']) ? $aOrder['expires_at'] : $this->expires_at,
            'activated_at' => $this->activated_at ?: now(),
            'status' => 'active',
        ]);
    }
}
