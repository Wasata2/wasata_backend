<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'service_listing_id',
        'quantity',
        'unit_price',
        'product_url',
        'product_image_path',
        'color',
        'size',
        'item_note',
    ];

    // Always include the ready-to-use image URL in JSON output, alongside the raw path
    protected $appends = ['product_image_url'];

    // Turns the stored relative path (e.g. "order-items/xyz.jpg") into a full,
    // permanent, directly-usable URL — same pattern as Store::image_url.
    public function getProductImageUrlAttribute(): ?string
    {
        return $this->product_image_path ? Storage::disk('public')->url($this->product_image_path) : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function serviceListing(): BelongsTo
    {
        return $this->belongsTo(ServiceListing::class);
    }
}
