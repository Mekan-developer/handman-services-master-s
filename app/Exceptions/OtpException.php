<?php

namespace App\Exceptions;

class OtpException extends ApiException
{
    private function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    public static function invalid(): self
    {
        return new self((string) __('api.otp.invalid'));
    }

    public static function sendFailed(): self
    {
        return new self((string) __('api.otp.send_failed'), 503);
    }

    public static function gatewayNotConnected(): self
    {
        return new self((string) __('api.otp.gateway_not_connected'), 503);
    }
}
