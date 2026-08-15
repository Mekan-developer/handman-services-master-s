<?php

namespace App\Exceptions;

class MasterApplicationException extends ApiException
{
    private function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    /** An application is already sitting in the review queue. */
    public static function underReview(): self
    {
        return new self((string) __('api.master_application.under_review'));
    }

    public static function alreadyApproved(): self
    {
        return new self((string) __('api.master_application.already_approved'));
    }

    /** The master profile copies the client's name, so the client must have one. */
    public static function nameMissing(): self
    {
        return new self((string) __('api.master_application.name_missing'));
    }

    /**
     * Admin side: only a pending application can be reviewed. A rejected one is
     * reopened by the applicant re-applying, never by the administrator.
     */
    public static function notUnderReview(): self
    {
        return new self((string) __('masters.errors.not_under_review'));
    }
}
