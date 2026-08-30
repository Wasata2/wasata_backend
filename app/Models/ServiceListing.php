<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceListing extends Model
{
    protected $fillable = [
        'store_id', 'title', 'photo_path', 'price', 'category', 'estimated_delivery', 'status',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
