<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The auto-search reached the maximum radius without anyone responding. Not
 * broadcast — this is an internal hand-off to the administrators.
 */
class OrderSearchExhausted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
