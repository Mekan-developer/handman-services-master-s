<?php

namespace App\Repositories;

use App\Enums\DevicePlatform;
use App\Models\Client;
use App\Models\ClientDevice;

class ClientDeviceRepository
{
    /**
     * Binds the token to the given account. A token that was registered by
     * another account on the same phone moves over, so a push never reaches
     * someone who has already signed out of that phone.
     */
    public function attachToClient(Client $client, string $token, DevicePlatform $platform, string $locale): ClientDevice
    {
        return ClientDevice::updateOrCreate(
            ['token' => $token],
            ['client_id' => $client->id, 'platform' => $platform, 'locale' => $locale],
        );
    }

    /**
     * Tokens of every phone the account is signed in on, grouped by app language.
     *
     * @return array<string, list<string>>
     */
    public function tokensByLocale(Client $client): array
    {
        return ClientDevice::where('client_id', $client->id)
            ->get(['token', 'locale'])
            ->groupBy('locale')
            ->map(fn ($devices) => $devices->pluck('token')->all())
            ->all();
    }

    public function deleteForClient(Client $client, string $token): void
    {
        ClientDevice::where('client_id', $client->id)
            ->where('token', $token)
            ->delete();
    }

    /** @param  list<string>  $tokens */
    public function deleteTokens(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        ClientDevice::whereIn('token', $tokens)->delete();
    }
}
