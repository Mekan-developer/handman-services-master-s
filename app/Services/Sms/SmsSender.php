<?php

namespace App\Services\Sms;

use App\Exceptions\OtpException;

/**
 * Delivers an OTP code to a phone number. Picked by `sms.driver`.
 */
interface SmsSender
{
    /**
     * @throws OtpException when the code could not be handed over
     * @throws \RuntimeException when the driver is misconfigured
     */
    public function send(string $phone, string $code): void;

    /**
     * @return array{reachable: bool, connected: bool, clients: int}
     */
    public function status(): array;
}
