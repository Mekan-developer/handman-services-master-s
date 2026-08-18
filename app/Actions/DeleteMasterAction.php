<?php

namespace App\Actions;

use App\Exceptions\MasterException;
use App\Models\Master;
use App\Repositories\MasterRepository;

/**
 * Removes the master role. The client account stays untouched — everyone signs
 * up as a client first, and losing the master profile must not cost someone
 * their orders, their login or their phone number.
 */
class DeleteMasterAction
{
    public function __construct(private readonly MasterRepository $repository) {}

    public function handle(Master $master): void
    {
        $completed = $this->repository->completedOrdersCount($master);

        if ($completed > 0) {
            throw MasterException::hasCompletedOrders($completed);
        }

        $active = $this->repository->activeOrdersCount($master);

        if ($active > 0) {
            throw MasterException::hasActiveOrders($active);
        }

        $this->repository->delete($master);
    }
}
