<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Operator = 'operator';

    /**
     * Roles that can be assigned when creating a user through the admin panel.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [
            self::Administrator,
            self::Manager,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => __('users.roles.administrator'),
            self::Manager => __('users.roles.manager'),
            self::Operator => __('users.roles.operator'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Administrator => 'indigo',
            self::Manager => 'blue',
            self::Operator => 'green',
        };
    }

    /**
     * Access to the admin sections behind `role:administrator,manager` — orders,
     * masters, the live map, parked OTP codes.
     *
     * Written as an exhaustive match rather than `! isOperator()` on purpose:
     * adding a role to this enum then fails loudly here instead of silently
     * handing the newcomer everything the operator was denied.
     */
    public function canAccessAdminSections(): bool
    {
        return match ($this) {
            self::Administrator, self::Manager => true,
            self::Operator => false,
        };
    }

    public function canManage(self $role): bool
    {
        return match ($this) {
            self::Administrator => true,
            self::Manager => $role !== self::Administrator,
            self::Operator => false,
        };
    }
}
