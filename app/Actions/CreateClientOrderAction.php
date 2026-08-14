<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Events\OrderSearchStarted;
use App\Jobs\ConvertOrderPhotoJob;
use App\Models\Client;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SettingRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateClientOrderAction
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly SettingRepository $settingRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Client $client, array $data, array $photos = []): Order
    {
        $radii = $this->settingRepository->searchRadii();

        return DB::transaction(function () use ($client, $data, $photos, $radii) {
            $order = $this->repository->create([
                'city_id' => $data['city_id'],
                'category_id' => $data['category_id'],
                'client_id' => $client->id,
                'client_name' => $client->name ?? $client->phone,
                'client_phone' => $data['client_phone'] ?? $client->phone,
                'client_address' => $data['client_address'] ?? null,
                'client_lat' => $data['client_lat'],
                'client_lng' => $data['client_lng'],
                'description' => $data['description'],
                'status' => OrderStatus::Pending,
                // The master auto-search starts the moment the order exists; the
                // scheduler grows the radius from here, minute by minute.
                'search_started_at' => now(),
                'search_radius_km' => $radii['initial'],
            ]);

            foreach ($photos as $photo) {
                $path = $photo->store("orders/{$order->id}/problem", 'public');

                $record = $order->photos()->create([
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'status' => 'pending',
                ]);

                ConvertOrderPhotoJob::dispatch($record->id);
            }

            $order->load(['city', 'category', 'photos']);

            OrderCreated::dispatch($order);
            OrderSearchStarted::dispatch($order);

            return $order;
        });
    }
}
