<?php

namespace App\Actions;

use App\Enums\MasterStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\MasterRepository;
use App\Repositories\SubscriptionPlanRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateMasterAction
{
    public function __construct(
        private readonly MasterRepository $repository,
        private readonly ClientRepository $clients,
        private readonly StoreMasterPhotoAction $storePhoto,
        private readonly SubscriptionPlanRepository $plans,
        private readonly IssueMasterSubscriptionAction $issueSubscription,
    ) {}

    /**
     * Create a master and, when a plan was picked, sell them their first
     * subscription in the same breath — that subscription is what opens access.
     *
     * A master profile always hangs off a client account, so a master entered by
     * hand here gets the account matching their phone number — reusing the one
     * they already signed up with in the app, or creating it on the spot. The
     * profile skips review: an administrator entering it is the review.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $createdBy = null): Master
    {
        $planId = $data['subscription_plan_id'] ?? null;
        $price = $data['subscription_price'] ?? null;
        $note = $data['subscription_note'] ?? null;

        unset($data['subscription_plan_id'], $data['subscription_price'], $data['subscription_note']);

        if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $data['photo'] = $this->storePhoto->handle($data['photo']);
        } else {
            unset($data['photo']);
        }

        // No subscription means no access — never leave the deadline null, which
        // Master::hasActiveAccess() reads as unlimited.
        $data['access_expires_at'] = now();

        $data['status'] = MasterStatus::Approved;
        $data['reviewed_at'] = now();
        $data['reviewed_by'] = $createdBy?->id;

        return DB::transaction(function () use ($data, $planId, $price, $note, $createdBy): Master {
            $data['client_id'] = $this->resolveClient($data)->id;

            $master = $this->repository->create($data);

            if ($planId !== null) {
                $this->issueSubscription->handle(
                    $master,
                    $this->plans->findOrFail((int) $planId),
                    $createdBy,
                    $price !== null ? (float) $price : null,
                    $note,
                );
            }

            return $master->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(array $data): Client
    {
        $existing = $this->clients->findByPhone($data['phone']);

        if ($existing !== null) {
            return $existing;
        }

        return $this->clients->create([
            'phone' => $data['phone'],
            'name' => $data['name'],
            'city_id' => $data['city_id'],
        ]);
    }
}
