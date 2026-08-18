<?php

namespace App\Actions;

use App\Models\Master;
use App\Repositories\MasterRepository;

class UpdateMasterAction
{
    public function __construct(private readonly MasterRepository $repository) {}

    /** @param array<string, mixed> $data */
    public function handle(Master $master, array $data): Master
    {
        return $this->repository->update($master, $data);
    }
}
