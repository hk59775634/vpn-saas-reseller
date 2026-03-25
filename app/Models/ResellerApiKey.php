<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerApiKey extends Model
{
    protected $table = 'reseller_api_keys';

    public $timestamps = false;

    protected $fillable = ['reseller_id', 'api_key', 'name'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}
