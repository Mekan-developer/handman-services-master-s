<?php

namespace App\Actions;

use App\Enums\DevicePlatform;
use App\Models\Client;
use App\Models\ClientDevice;
use App\Repositories\ClientDeviceRepository;

/**
 * Called by the app after sign-in, whenever Firebase rotates the token and
 * after the user switches the app language. Idempotent: re-sending the same
 * token only refreshes its owner, platform and language.
 */
class RegisterClientDeviceAction
{
    public function __construct(private readonly ClientDeviceRepository $devices) {}

    public function handle(Client $client, string $token, DevicePlatform $platform, string $locale): ClientDevice
    {
        return $this->devices->attachToClient($client, $token, $platform, $locale);
    }
}
