<?php

namespace App\Actions;

use App\Exceptions\CategoryException;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Support\CategoryIcon;

class DeleteCategoryAction
{
    public function __construct(private readonly CategoryRepository $repository) {}

    public function handle(Category $category): void
    {
        $ordersCount = $this->repository->ordersCount($category);

        if ($ordersCount > 0) {
            throw CategoryException::usedByOrders($ordersCount);
        }

        // The icon file is purged only after the row is gone: a delete rejected by
        // the database must not leave the category behind without its image.
        $this->repository->delete($category);
        CategoryIcon::purge($category);
    }
}
