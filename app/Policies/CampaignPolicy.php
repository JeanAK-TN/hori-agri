<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;

/**
 * Campaigns are set by the exploitation administrator (SPEC section 5).
 * Technicians read them to open cycles, but never change their bounds.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::ExploitationAdmin, Role::Technician], true);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::ExploitationAdmin;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->role === Role::ExploitationAdmin;
    }
}
