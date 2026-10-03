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
        // FIX: was 'delivery_area' — the column and the controller were both
        // renamed to delivery_region, but this file never got updated, so this
        // field has been silently dropped on every create() since the rename.
        'delivery_region',
        // NEW — price-approval workflow fields.
        'pickup_location', 'awaiting_customer_approval_at', 'price_declined_at',
        'price_deadline',
    ];

    // Without these, the *_at columns would come back as raw strings instead
    // of Carbon instances, and toISOString() in OrderController would fail.
    protected $casts = [
        'ordered_from_shein_at'          => 'datetime',
        'shipped_at'                     => 'datetime',
        'arrived_at'                     => 'datetime',
        'inspected_at'                   => 'datetime',
        'received_at'                    => 'datetime',
        'rejected_at'                    => 'datetime',
        'cancelled_at'                   => 'datetime',
        'awaiting_customer_approval_at'  => 'datetime',
        'price_declined_at'              => 'datetime',
        'price_deadline'                 => 'datetime',
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
