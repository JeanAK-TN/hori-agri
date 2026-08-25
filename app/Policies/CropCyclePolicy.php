<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CropCycle;
use App\Models\User;

class CropCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, CropCycle $cycle): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    /**
     * Move the cycle along its state diagram (close, give up).
     */
    public function transition(User $user, CropCycle $cycle): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return in_array($user->role, [Role::ExploitationAdmin, Role::Technician], true);
    }
}
