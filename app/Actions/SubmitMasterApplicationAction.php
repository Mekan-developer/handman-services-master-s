<?php

namespace App\Actions;

use App\Enums\MasterStatus;
use App\Exceptions\MasterApplicationException;
use App\Models\Client;
use App\Models\Master;
use App\Repositories\MasterRepository;

/**
 * Turns a client into a master applicant. The profile is created in `Pending`
 * and grants nothing on its own — an administrator takes the payment in person,
 * approves the application and issues the subscription that opens access.
 */
class SubmitMasterApplicationAction
{
    public function __construct(private readonly MasterRepository $repository) {}

    /**
     * @param  array{city_id: int, category_ids: array<int, int>, experience_years: int, about?: string|null}  $data
     */
    public function handle(Client $client, array $data): Master
    {
        $existing = $this->repository->findByClient($client);

        if ($existing !== null) {
            if ($existing->status === MasterStatus::Approved) {
                throw MasterApplicationException::alreadyApproved();
            }

            if ($existing->status === MasterStatus::Pending) {
                throw MasterApplicationException::underReview();
            }
        }

        if (blank($client->name)) {
            throw MasterApplicationException::nameMissing();
        }

        $payload = [
            'city_id' => $data['city_id'],
            'experience_years' => $data['experience_years'],
            'about' => $data['about'] ?? null,
            'category_ids' => $data['category_ids'],
            'status' => MasterStatus::Pending,
            'name' => $client->name,
            'phone' => $client->phone,
            'is_active' => true,

            // A fresh application carries no access: `null` would read as
            // unlimited in Master::hasActiveAccess().
            'access_expires_at' => now(),

            // Clear the previous verdict when a rejected applicant re-applies.
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
        ];

        // Only a rejected applicant reaches this point with a profile already
        // on file — reuse it so the client keeps a single master record.
        return $existing === null
            ? $this->repository->create($payload + ['client_id' => $client->id])
            : $this->repository->update($existing, $payload);
    }
}
