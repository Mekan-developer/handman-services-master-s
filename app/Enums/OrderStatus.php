<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('orders.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Assigned => 'blue',
            self::InProgress => 'indigo',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Final statuses — order cannot be transitioned away. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * The client may follow the master on the map: a master is on the job and
     * has not finished it yet. The single source of truth for when live
     * tracking starts and stops — the GPS pings stop reaching the client's
     * private channel the moment this turns false.
     */
    public function isTrackable(): bool
    {
        return in_array($this, [self::Assigned, self::InProgress], true);
    }
}
