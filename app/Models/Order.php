<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const EARTH_RADIUS_KM = 6371.0;

    /** One degree of latitude in kilometres — the constant side of the bounding box. */
    public const KM_PER_LAT_DEGREE = 111.32;

    /** @var array<int, string> */
    protected $fillable = [
        'city_id',
        'category_id',
        'master_id',
        'client_id',
        'status',
        'client_name',
        'client_phone',
        'description',
        'client_address',
        'client_lat',
        'client_lng',
        'final_price',
        'assigned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'master_change_reason',
        'search_started_at',
        'search_radius_km',
        'search_expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'client_lat' => 'decimal:7',
            'client_lng' => 'decimal:7',
            'final_price' => 'decimal:2',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'search_started_at' => 'datetime',
            'search_radius_km' => 'integer',
            'search_expired_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Master::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OrderTask::class);
    }

    public function masterLocations(): HasMany
    {
        return $this->hasMany(MasterLocation::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(OrderReview::class);
    }

    public function declines(): HasMany
    {
        return $this->hasMany(OrderMasterDecline::class);
    }

    /** The auto-search gave up — an administrator has to assign a master by hand. */
    public function needsManualAssignment(): bool
    {
        return $this->search_expired_at !== null && $this->master_id === null;
    }

    /**
     * Great-circle distance in kilometres between this order's client location
     * and an arbitrary point (haversine, mean Earth radius).
     *
     * Kept in PHP rather than SQL on purpose: MySQL, PostgreSQL and the SQLite
     * build used by the test suite disagree on which trigonometric functions
     * exist, so queries pre-filter with a plain-arithmetic bounding box and the
     * exact distance is settled here.
     */
    public function distanceKmTo(float $latitude, float $longitude): float
    {
        $latFrom = deg2rad((float) $this->client_lat);
        $latTo = deg2rad($latitude);
        $latDelta = $latTo - $latFrom;
        $lngDelta = deg2rad($longitude) - deg2rad((float) $this->client_lng);

        $angle = 2 * asin(min(1.0, sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2
        )));

        return $angle * self::EARTH_RADIUS_KM;
    }
}
