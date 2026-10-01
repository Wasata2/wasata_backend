<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
    'store_id', 'customer_id', 'status', 'estimated_amount', 'customer_note',
    'delivery_method', 'delivery_fee', 'address', 'contact_phone',
    'ordered_from_shein_at', 'shipped_at', 'arrived_at',
    'inspected_at', 'received_at', 'rejected_at', 'cancelled_at',
    'rejection_reason',
];

    // Without these, the new *_at columns would come back as raw strings
    // instead of Carbon instances, and toISOString() below would fail.
    protected $casts = [
        'ordered_from_shein_at' => 'datetime',
        'shipped_at'            => 'datetime',
        'arrived_at'            => 'datetime',
        'inspected_at'          => 'datetime',
        'received_at'           => 'datetime',
        'rejected_at'           => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
