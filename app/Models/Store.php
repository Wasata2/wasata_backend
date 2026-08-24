<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'status',
    ];

    protected $casts = [
        'accepts_whatsapp_orders' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
