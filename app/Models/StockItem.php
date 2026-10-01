p<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StockItem extends Model
{
    protected $fillable = [
        'store_id', 'customer_id', 'name', 'category', 'price', 'status',
        'size', 'color', 'image_path',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Always include the ready-to-use image URL in JSON output — same pattern as
    // Store::image_url and OrderItem::product_image_url.
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
