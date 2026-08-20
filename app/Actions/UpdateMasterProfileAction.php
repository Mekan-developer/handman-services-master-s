<?php

namespace App\Actions;

use App\Models\Master;
use App\Repositories\MasterRepository;

/**
 * A master editing their own trade details — categories, experience, about.
 * Distinct from {@see UpdateMasterAction}, which is the administrator's form
 * and may also flip `is_active` and the city.
 *
 * The verdict is deliberately left untouched: changing categories does not send
 * an approved master back to review, they keep working while the administrator
 * sees the new data in the list.
 */
class UpdateMasterProfileAction
{
    public function __construct(private readonly MasterRepository $repository) {}

    /**
     * @param  array{category_ids?: array<int, int>, experience_years?: int, about?: string|null}  $data
     */
    public function handle(Master $master, array $data): Master
    {
        return $this->repository->update($master, $data);
    }
}
