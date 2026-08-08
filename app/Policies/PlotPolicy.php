<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Plot;
use App\Models\User;

/**
 * Plots are recorded by technicians and by the cooperative
 * administrator (SPEC section 5). Cross-cooperative access never reaches
 * this policy: CooperativeScope already hides the record.
 */
class PlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Plot $plot): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Plot $plot): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return in_array($user->role, [Role::CoopAdmin, Role::Technician], true);
    }
}
