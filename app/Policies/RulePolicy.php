<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The knowledge base is read by the super_admin and by the exploitation
 * administrators. Only the latter override a threshold, and only for
 * their own exploitation (SPEC sections 5 and 11).
 */
class RulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::ExploitationAdmin], true);
    }

    /**
     * Overriding a rule means carrying a copy of it for one exploitation.
     * A rule already belonging to another exploitation is out of reach,
     * and answers "not found" rather than "forbidden" (SPEC 7.2).
     */
    public function override(User $user, Rule $rule): Response
    {
        if ($user->role !== Role::ExploitationAdmin) {
            return Response::deny('Seul un administrateur d\'exploitation surcharge un seuil.');
        }

        if ($rule->exploitation_id !== null && $rule->exploitation_id !== $user->exploitation_id) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }
}
