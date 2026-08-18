<?php

namespace App\Models;

use App\Enums\MasterStatus;
use Database\Factories\MasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Master extends Authenticatable
{
    /** @use HasFactory<MasterFactory> */
    use HasApiTokens, HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'client_id',
        'city_id',
        'name',
        'phone',
        'status',
        'experience_years',
        'about',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'access_expires_at',
        'is_active',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'status' => MasterStatus::class,
            'experience_years' => 'integer',
            'reviewed_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /** The account the master signed in with — a master profile never exists on its own. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Administrator who approved or rejected the application. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MasterLocation::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(MasterLocation::class)->latestOfMany('recorded_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(OrderReview::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MasterSubscription::class);
    }

    public function hasActiveAccess(): bool
    {
        return $this->access_expires_at === null || $this->access_expires_at->isFuture();
    }

    /** Waiting for an administrator to review the application. */
    public function isPending(): bool
    {
        return $this->status === MasterStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === MasterStatus::Approved;
    }
}
