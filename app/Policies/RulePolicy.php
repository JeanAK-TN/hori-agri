<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The knowledge base is read by the super_admin and by the cooperative
 * administrators. Only the latter override a threshold, and only for
 * their own cooperative (SPEC sections 5 and 11).
 */
class RulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::CoopAdmin], true);
    }

    /**
     * Overriding a rule means carrying a copy of it for one cooperative.
     * A rule already belonging to another cooperative is out of reach,
     * and answers "not found" rather than "forbidden" (SPEC 7.2).
     */
    public function override(User $user, Rule $rule): Response
    {
        if ($user->role !== Role::CoopAdmin) {
            return Response::deny('Seul un administrateur de coopérative surcharge un seuil.');
        }

        if ($rule->cooperative_id !== null && $rule->cooperative_id !== $user->cooperative_id) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }
}
