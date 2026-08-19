<?php

namespace App\Models;

use App\Enums\OrderResponseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A master's response to an order still in auto-search — several masters can
 * hold a pending response on the same order at once; the client decides who
 * gets it.
 */
class OrderMasterResponse extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'order_id',
        'master_id',
        'status',
        'rejection_reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderResponseStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Master::class);
    }
}
