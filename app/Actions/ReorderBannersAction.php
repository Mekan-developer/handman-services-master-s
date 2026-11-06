<?php

namespace App\Actions;

use App\Repositories\BannerRepository;

class ReorderBannersAction
{
    public function __construct(private readonly BannerRepository $repository) {}

    /**
     * Positions start at 1, so a freshly created banner (sort_order 0) shows up
     * first until it is dragged into place.
     *
     * @param  array<int, int>  $ids  Banner ids in their new display order.
     */
    public function handle(array $ids): void
    {
        $positions = [];

        foreach (array_values($ids) as $index => $id) {
            $positions[$id] = $index + 1;
        }

        $this->repository->reorder($positions);
    }
}
