<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Master;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Http\FormRequest;

class RestoreDeclinedOrderRequest extends FormRequest
{
    /**
     * A master may only take back their own decline. When the order was declined
     * by other masters but not by this one, the request targets someone else's
     * decline and is forbidden; with no decline at all the action answers with
     * a plain "not declined".
     */
    public function authorize(OrderRepository $orders): bool
    {
        $master = $this->user();

        if (! $master instanceof Master) {
            return false;
        }

        return ! $orders->isDeclinedOnlyByOtherMasters((int) $this->route('order'), $master->id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
