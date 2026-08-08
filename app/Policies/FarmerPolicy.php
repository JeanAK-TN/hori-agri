<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Farmer;
use App\Models\User;

/**
 * Producers are managed by the cooperative administrator and the
 * technicians (SPEC section 5). Cross-cooperative access never reaches
 * this policy: CooperativeScope already makes the record unreachable,
 * which yields "not found" instead of "forbidden" (SPEC section 7.2).
 */
class FarmerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Farmer $farmer): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Farmer $farmer): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return in_array($user->role, [Role::CoopAdmin, Role::Technician], true);
    }
}
