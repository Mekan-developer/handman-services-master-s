<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\MasterSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterSubscription extends Model
{
    /** @use HasFactory<MasterSubscriptionFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'master_id',
        'subscription_plan_id',
        'plan_name',
        'price_paid',
        'duration_days',
        'status',
        'starts_at',
        'expires_at',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'duration_days' => 'integer',
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Master::class);
    }

    /** The plan this was bought from — null once the plan is force-deleted. */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Currently running — started and not yet past its end date. */
    public function isRunning(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
