<?php

namespace App\Exceptions;

class CategoryException extends ApiException
{
    /**
     * Orders still reference the category. The `orders.category_id` foreign key is
     * ON DELETE RESTRICT, so the database would reject the delete anyway — this
     * turns that into a readable message instead of a raw SQL error.
     */
    public static function usedByOrders(int $orderCount): self
    {
        return new self((string) __('categories.delete_has_orders', ['count' => $orderCount]));
    }
}
