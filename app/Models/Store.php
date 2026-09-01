<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'bio',
        'image_path',
        'phone',
        'city',
        'accepts_whatsapp_orders',
        'commission_rate',
        'status',
    ];

    protected $casts = [
        'accepts_whatsapp_orders' => 'boolean',
    ];

    // Always include this computed field in JSON output, alongside the raw columns
    protected $appends = ['image_url'];

    // Turns the stored relative path (e.g. "stores/xyz.jpg") into a full,
    // permanent, directly-usable URL — e.g. http://127.0.0.1:8000/storage/stores/xyz.jpg
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function serviceListings(): HasMany
    {
        return $this->hasMany(ServiceListing::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
