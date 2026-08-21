<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
