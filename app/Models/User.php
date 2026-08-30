<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // The columns register() is allowed to insert via User::create([...])
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'role_id',
        'account_status',
    ];

    // Never expose these in any json response, even by accident
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'           => 'hashed', // Laravel 10+: auto-hashes on assignment
    ];

    // Every user belongs to exactly one role
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // If this user is a broker: the one store they own (null if they haven't created one yet)
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    // If this user is a customer: every order they've placed, across any store
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
