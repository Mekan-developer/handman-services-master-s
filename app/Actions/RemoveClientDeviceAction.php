<?php

namespace App\Actions;

use App\Models\Client;
use App\Repositories\ClientDeviceRepository;

/**
 * Called by the app right before logout, so the phone stops receiving pushes
 * meant for the account that just left it. Only the caller's own token can be
 * removed; an unknown token is silently ignored.
 */
class RemoveClientDeviceAction
{
    public function __construct(private readonly ClientDeviceRepository $devices) {}

    public function handle(Client $client, string $token): void
    {
        $this->devices->deleteForClient($client, $token);
    }
}
