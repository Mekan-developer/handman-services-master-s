<?php

namespace App\Listeners;

use App\Events\SmsCodeRequested;
use App\Services\Sms\SmsSender;

/**
 * Not queued on purpose: a failed send must surface as an exception in the
 * request that asked for the code, which then parks it for manual delivery.
 */
class SendSmsCode
{
    public function __construct(private readonly SmsSender $sender) {}

    public function handle(SmsCodeRequested $event): void
    {
        $this->sender->send($event->phone, $event->code);
    }
}
