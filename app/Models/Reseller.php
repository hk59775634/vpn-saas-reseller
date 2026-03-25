<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    protected $fillable = ['name'];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ResellerApiKey::class, 'reseller_id');
    }
}
