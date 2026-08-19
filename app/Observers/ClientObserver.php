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
     * `masters.name` and `masters.phone` are copied from the client at
     * application time and never read live from the relation elsewhere (see
     * MasterResource's top-level `name`) — a master profile is the same
     * person as the client, not a separate record, so both must stay in
     * lockstep whenever the client edits either field.
     */
    public function updated(Client $client): void
    {
        if (! $client->wasChanged(['name', 'phone'])) {
            return;
        }

        $client->master()->first()?->update([
            'name' => $client->name,
            'phone' => $client->phone,
        ]);
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
