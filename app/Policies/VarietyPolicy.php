<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Variety;

/**
 * Varieties follow the crop registry: super_admin only.
 */
class VarietyPolicy
{
    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function update(User $user, Variety $variety): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
