<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Plot;
use App\Models\User;

/**
 * Plots are recorded by technicians and by the exploitation
 * administrator (SPEC section 5). Cross-exploitation access never reaches
 * this policy: ExploitationScope already hides the record.
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
        return in_array($user->role, [Role::ExploitationAdmin, Role::Technician], true);
    }
}
