<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDeliveryZone extends Model
{
    public const AREAS = ['gaza', 'khan_younis', 'north_gaza', 'middle', 'rafah'];

    protected $fillable = ['store_id', 'area', 'fee'];

    protected $casts = ['fee' => 'decimal:2'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
