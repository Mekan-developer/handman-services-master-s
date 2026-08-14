<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A master explicitly dismissed an order, so it is hidden from that master's
 * available-orders feed for good — other masters still see it.
 */
class OrderMasterDecline extends Model
{
    public const UPDATED_AT = null;

    /** @var array<int, string> */
    protected $fillable = [
        'order_id',
        'master_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Master::class);
    }
}
