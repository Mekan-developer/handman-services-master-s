<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class OrderObserver
{
    /**
     * Every file an order collects lives under one directory keyed by its id —
     * `orders/{id}/problem` for the photos the client attached and
     * `orders/{id}/tasks/{task}/{before|after}` for the work the master
     * recorded. Dropping the directory clears all of them in one call, including
     * uploads whose conversion job never finished.
     *
     * Rows (`order_photos`, `order_tasks`, `order_task_photos`) are removed by
     * the database cascade; only the files need doing by hand.
     */
    public function deleted(Order $order): void
    {
        Storage::disk('public')->deleteDirectory("orders/{$order->id}");
    }
}
