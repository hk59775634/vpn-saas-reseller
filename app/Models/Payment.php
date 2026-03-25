<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PROVIDER_WECHAT = 'wechat';
    public const PROVIDER_ALIPAY = 'alipay';
    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_EPAY = 'epay';
    public const PROVIDER_SIMULATED = 'simulated';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEED = 'succeed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'provider_payment_id',
        'amount_cents',
        'currency',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

