<?php

namespace App\Models;

use App\Enums\SubscriptionRequestStatus;
use Database\Factories\SubscriptionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRequest extends Model
{
    /** @use HasFactory<SubscriptionRequestFactory> */
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'client_id',
        'subscription_plan_id',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'master_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The requested plan — kept visible after a soft delete so the request still reads. */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id')->withTrashed();
    }

    /** Administrator who approved or rejected the request. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MasterSubscription::class, 'master_subscription_id');
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionRequestStatus::Pending;
    }
}
