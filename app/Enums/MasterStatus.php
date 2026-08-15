<?php

namespace App\Enums;

/**
 * Lifecycle of a master profile. Every profile starts as a client's application
 * and only an administrator can move it to `Approved` — that review, together
 * with the subscription issued in the same step, is what opens master access.
 */
enum MasterStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('masters.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    /** Only an approved profile may reach the master API at all. */
    public function grantsAccess(): bool
    {
        return $this === self::Approved;
    }

    /**
     * A rejected applicant may re-apply, which puts them back in the queue.
     * An approved profile is never sent back for review — revoke access by
     * deactivating the master or letting the subscription lapse instead.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Approved, self::Rejected], true),
            self::Rejected => $to === self::Pending,
            self::Approved => false,
        };
    }
}
