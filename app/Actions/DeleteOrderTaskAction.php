<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Master;
use App\Models\OrderTask;
use Illuminate\Support\Facades\Storage;

class DeleteOrderTaskAction
{
    public function handle(Master $master, OrderTask $task): void
    {
        if ($task->order->master_id !== $master->id) {
            throw new \DomainException('This task does not belong to you.');
        }

        if ($task->order->status !== OrderStatus::InProgress) {
            throw new \DomainException('Tasks can only be deleted while the order is in progress.');
        }

        foreach ($task->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $task->delete();
    }
}
