<?php

namespace App\Enums;

enum Role: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case ExploitationAdmin = 'exploitation_admin';
    case Technician = 'technician';
    case Farmer = 'farmer';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administrateur de la solution',
            self::ExploitationAdmin => 'Administrateur d\'exploitation',
            self::Technician => 'Technicien',
            self::Farmer => 'Agriculteur',
        };
    }

    /**
     * Roles an actor is allowed to assign when creating or updating an
     * account (SPEC section 5). The super_admin creates the exploitation
     * administrator; the latter manages the accounts of their own
     * exploitation. No role can ever create a super_admin, which keeps the
     * invariant "exploitation_id is null if and only if super_admin" true
     * for every account created through the interface.
     *
     * @return array<int, self>
     */
    public static function assignableBy(self $actor): array
    {
        return match ($actor) {
            self::SuperAdmin => [self::ExploitationAdmin],
            self::ExploitationAdmin => [self::ExploitationAdmin, self::Technician, self::Farmer],
            self::Technician, self::Farmer => [],
        };
    }

    /**
     * @param  array<int, self>  $roles
     * @return array<int, string>
     */
    public static function values(array $roles): array
    {
        return array_map(static fn (self $role): string => $role->value, $roles);
    }
}
