<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Order;
use App\Repositories\ClientRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Создание заказа админом «от имени клиента».
 *
 * Клиент либо выбирается из существующих (client_id), либо создаётся
 * по телефону, после чего заказ создаётся тем же путём, что и из мобильного
 * приложения — включая запуск авто-поиска мастера.
 */
class CreateOrderForClientAction
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly CreateClientOrderAction $createOrder,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(array $data, array $photos = []): Order
    {
        return DB::transaction(function () use ($data, $photos) {
            $client = $this->resolveClient($data);

            $data['client_phone'] = $client->phone;

            return $this->createOrder->handle($client, $data, $photos);
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            return $this->clients->findOrFail((int) $data['client_id']);
        }

        return $this->clients->findByPhone($data['client_phone'])
            ?? $this->clients->create([
                'name' => $data['client_name'],
                'phone' => $data['client_phone'],
                'city_id' => $data['city_id'],
            ]);
    }
}
