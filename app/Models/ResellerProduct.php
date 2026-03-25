<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerProduct extends Model
{
    protected $fillable = [
        'reseller_id',
        'source_product_id',
        'cost_cents',
        'name',
        'description',
        'price_cents',
        'currency',
        'duration_days',
        'status',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'reseller_product_id');
    }
}
