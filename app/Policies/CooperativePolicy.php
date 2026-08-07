<?php

namespace App\Policies;

use App\Models\Cooperative;
use App\Models\User;

class CooperativePolicy
{
    /**
     * Determine whether the user can view the list of cooperatives.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create cooperatives.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the cooperative.
     */
    public function update(User $user, Cooperative $cooperative): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can suspend or reactivate the cooperative.
     */
    public function suspend(User $user, Cooperative $cooperative): bool
    {
        return $user->isSuperAdmin();
    }
}
