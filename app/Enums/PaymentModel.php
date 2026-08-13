<?php

namespace App\Enums;

enum PaymentModel: string
{
    case Percentage = 'percentage';
    case FixedPerJob = 'fixed_per_job';
    case Salary = 'salary';
    case SalaryPercentage = 'salary_percentage';

    public function label(): string
    {
        return __('masters.payment_models.'.$this->value);
    }

    /**
     * Whether the per-order earning depends on the order's final price.
     */
    public function requiresFinalPrice(): bool
    {
        return match ($this) {
            self::Percentage, self::SalaryPercentage => true,
            default => false,
        };
    }
}
