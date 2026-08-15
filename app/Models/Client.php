<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    /** @use HasFactory<ClientFactory> */
    use HasApiTokens, HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'city_id',
        'name',
        'phone',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The master profile this client applied for, in any status. Absent for the
     * vast majority of clients — everyone signs up as a client first.
     */
    public function master(): HasOne
    {
        return $this->hasOne(Master::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
