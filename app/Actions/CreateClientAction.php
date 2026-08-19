<?php

namespace App\Actions;

use App\Events\ClientCreated;
use App\Models\Client;
use App\Repositories\ClientRepository;
use Illuminate\Http\UploadedFile;

class CreateClientAction
{
    public function __construct(
        private readonly ClientRepository $repository,
        private readonly StoreClientPhotoAction $storePhoto,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): Client
    {
        if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $data['photo'] = $this->storePhoto->handle($data['photo']);
        } else {
            unset($data['photo']);
        }

        $client = $this->repository->create($data);

        ClientCreated::dispatch($client);

        return $client;
    }
}
