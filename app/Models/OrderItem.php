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
        'product_name',
        'product_url',
        'product_image_path',
        'color',
        'size',
        'item_note',
    ];

    protected $appends = ['product_image_url', 'image_url'];

    public function getProductImageUrlAttribute(): ?string
    {
        return $this->product_image_path ? Storage::disk('public')->url($this->product_image_path) : null;
    }

    // Alias — same value as product_image_url, kept for naming consistency
    // with Store/StockItem/User.
    public function getImageUrlAttribute(): ?string
    {
        return $this->product_image_url;
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
