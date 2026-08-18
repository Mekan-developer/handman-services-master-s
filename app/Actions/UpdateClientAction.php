<?php

namespace App\Actions;

use App\Models\Client;
use App\Repositories\ClientRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateClientAction
{
    public function __construct(
        private readonly ClientRepository $repository,
        private readonly StoreClientPhotoAction $storePhoto,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Client $client, array $data): Client
    {
        if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
            if ($client->photo) {
                Storage::disk('public')->delete($client->photo);
            }

            $data['photo'] = $this->storePhoto->handle($data['photo']);
        } else {
            // A request without a file must never blank out the stored avatar.
            unset($data['photo']);
        }

        return $this->repository->update($client, $data);
    }
}
