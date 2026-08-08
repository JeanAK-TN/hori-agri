<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

/**
 * Campaigns are set by the cooperative administrator (SPEC section 5).
 * Technicians read them to open cycles, but never change their bounds.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::CoopAdmin, Role::Technician], true);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::CoopAdmin;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->role === Role::CoopAdmin;
    }
}
