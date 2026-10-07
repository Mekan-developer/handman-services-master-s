<?php

namespace App\Notifications\Push;

use App\Models\OrderMasterResponse;

/** To the client: a master responded to their order. */
class MasterRespondedNotification extends PushNotification
{
    public function __construct(public OrderMasterResponse $response) {}

    protected function type(): string
    {
        return 'order.response.created';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_CLIENT;
    }

    protected function title(): string
    {
        return __('push.client.master_responded.title');
    }

    protected function body(): string
    {
        return __('push.client.master_responded.body', [
            'master' => $this->response->master->name,
            'order' => $this->response->order_id,
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return [
            'order_id' => (string) $this->response->order_id,
            'response_id' => (string) $this->response->id,
        ];
    }
}
