<?php

namespace App\Exceptions;

class SubscriptionRequestException extends ApiException
{
    private function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    /** The client already has a request waiting for the administrator. */
    public static function alreadyPending(): self
    {
        return new self((string) __('api.subscription_request.already_pending'), 409);
    }

    /** Admin side: a verdict is final, so only a pending request can be reviewed. */
    public static function notPending(): self
    {
        return new self((string) __('subscription_requests.errors.not_pending'));
    }

    /**
     * Admin side: a subscription only opens anything on an approved master
     * profile — the client has to go through the master application first.
     */
    public static function notAMaster(): self
    {
        return new self((string) __('subscription_requests.errors.not_a_master'));
    }
}
