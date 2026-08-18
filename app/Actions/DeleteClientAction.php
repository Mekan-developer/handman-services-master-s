<?php

namespace App\Actions;

use App\Exceptions\ClientException;
use App\Models\Client;
use App\Repositories\ClientRepository;
use App\Repositories\MasterRepository;

/**
 * Deletes the account and everything hanging off it: the master profile, the
 * orders this person placed, the work recorded on them and every uploaded file
 * (see ClientObserver / OrderObserver).
 *
 * Orders where the person was the assigned *master* are not touched — those
 * belong to the clients who placed them.
 */
class DeleteClientAction
{
    public function __construct(
        private readonly ClientRepository $repository,
        private readonly MasterRepository $masters,
    ) {}

    public function handle(Client $client): void
    {
        $master = $this->masters->findByClient($client);

        if ($master !== null) {
            $completed = $this->masters->completedOrdersCount($master);

            if ($completed > 0) {
                throw ClientException::masterHasCompletedOrders($completed);
            }

            $active = $this->masters->activeOrdersCount($master);

            if ($active > 0) {
                throw ClientException::masterHasActiveOrders($active);
            }
        }

        $this->repository->delete($client);
    }
}
