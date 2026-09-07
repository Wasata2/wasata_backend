<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceListing extends Model
{
    // The fixed set of icons shown in "أيقونة الخدمة" — keep this in sync with the frontend
    public const ICONS = ['photo', 'truck', 'pin', 'refresh', 'chat', 'tag', 'gift', 'scissors', 'diamond', 'search'];

    protected $fillable = [
        'store_id', 'title', 'icon', 'description', 'fee_type', 'fee_amount', 'notes', 'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'fee_amount'   => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
