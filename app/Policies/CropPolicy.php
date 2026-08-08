<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Crop;
use App\Models\User;

/**
 * The crop registry is common to the whole solution and maintained by
 * the super_admin alone (SPEC section 5). Everyone else reads it through
 * the cycle screens.
 */
class CropPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function update(User $user, Crop $crop): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
