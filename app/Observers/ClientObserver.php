<?php

namespace App\Observers;

use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class ClientObserver
{
    /**
     * Take the master profile and the client's own orders down through Eloquent
     * rather than leaving them to the database.
     *
     * `masters.client_id` and `orders.client_id` are both ON DELETE CASCADE, and
     * a database-level cascade never fires model events — the rows would vanish
     * while their uploaded files stayed on disk forever. Deleting them here runs
     * MasterObserver and OrderObserver first; the foreign keys stay as the
     * backstop for anything that bypasses the model (raw SQL, bulk deletes).
     */
    public function deleting(Client $client): void
    {
        $client->master()->first()?->delete();

        $client->orders()->get()->each->delete();
    }

    /**
     * `masters.name`, `masters.phone` and `masters.city_id` are copied from the
     * client at application time and never read live from the relation elsewhere
     * (see MasterResource's top-level `name`) — a master profile is the same
     * person as the client, not a separate record, so all three must stay in
     * lockstep whenever the client edits any of them.
     *
     * The city matters operationally: MasterRepository::eligibleForOrder()
     * matches it against the order's city, so a master who moved and only
     * updated their client profile would otherwise stay invisible to the
     * administrator in their new city.
     */
    public function updated(Client $client): void
    {
        if (! $client->wasChanged(['name', 'phone', 'city_id'])) {
            return;
        }

        $payload = [
            'name' => $client->name,
            'phone' => $client->phone,
        ];

        // `clients.city_id` is nullable while `masters.city_id` is not — an
        // account left without a city keeps the master's last known one.
        if ($client->city_id !== null) {
            $payload['city_id'] = $client->city_id;
        }

        $client->master()->first()?->update($payload);
    }

    /**
     * The avatar goes only once the row is actually gone — a delete rejected by
     * the database must not leave the client behind without their photo.
     */
    public function deleted(Client $client): void
    {
        if ($client->photo) {
            Storage::disk('public')->delete($client->photo);
        }
    }
}
