<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Stream the staff members allowed to work with orders, in chunks.
     *
     * Operators are excluded — they cannot open the orders section, so a
     * notification for them would only ever be a dead database row.
     *
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function eachOrderNotifiable(callable $callback, int $chunkSize = 200): void
    {
        User::query()
            ->whereIn('role', [UserRole::Administrator->value, UserRole::Manager->value])
            ->chunkById($chunkSize, $callback);
    }

    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->when(isset($filters['role']), fn ($q) => $q->where('role', $filters['role']))
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }
}
