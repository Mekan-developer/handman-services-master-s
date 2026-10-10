<?php

namespace App\Models;

use App\Enums\SubscriptionRequestStatus;
use App\Repositories\ClientDeviceRepository;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    /** @use HasFactory<ClientFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var array<int, string> */
    protected $fillable = [
        'city_id',
        'name',
        'phone',
        'photo',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Public URL of the account avatar, or null when none was uploaded.
     *
     * The one avatar in the system: a `Master` profile on this account shows
     * this very file, so every consumer — the mobile API, the admin clients
     * table, the masters table — reads it from here instead of rebuilding the
     * path. Resolved with `asset()` so the URL carries the host that served the
     * request, not a hard-coded `APP_URL`.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->photo !== null ? asset("storage/{$this->photo}") : null
        );
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

    /**
     * The plan request still waiting for a verdict — at most one per client,
     * see SubmitSubscriptionRequestAction. For an applicant this is the plan
     * they picked in the app alongside the "become a master" form.
     */
    public function pendingSubscriptionRequest(): HasOne
    {
        return $this->hasOne(SubscriptionRequest::class)
            ->where('status', SubscriptionRequestStatus::Pending);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ClientDevice::class);
    }

    /**
     * FCM registration tokens of every phone this account is signed in on,
     * grouped by app language — read by `FcmChannel`.
     *
     * @return array<string, list<string>>
     */
    public function routeNotificationForFcm(): array
    {
        return app(ClientDeviceRepository::class)->tokensByLocale($this);
    }
}
