<?php

namespace App\Actions;

use App\Models\MasterSubscription;
use App\Repositories\MasterSubscriptionRepository;

class UpdateMasterSubscriptionAction
{
    public function __construct(private readonly MasterSubscriptionRepository $repository) {}

    /**
     * Correct a data-entry mistake on a sold subscription. Only the amount taken
     * and the note are editable — dates and duration stay as sold, otherwise the
     * snapshot would stop being a record of what actually happened.
     *
     * @param  array{price_paid?: float|string, note?: string|null}  $data
     */
    public function handle(MasterSubscription $subscription, array $data): MasterSubscription
    {
        return $this->repository->update($subscription, [
            'price_paid' => $data['price_paid'] ?? $subscription->price_paid,
            'note' => $data['note'] ?? null,
        ]);
    }
}
