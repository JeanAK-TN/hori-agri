<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of accounts.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::CoopAdmin], true);
    }

    /**
     * Determine whether the user can create accounts.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::CoopAdmin], true);
    }

    /**
     * Determine whether the user can update the account.
     *
     * A cross-cooperative request answers "not found", never "forbidden",
     * so as not to reveal the existence of the resource (SPEC section 7.2).
     */
    public function update(User $user, User $model): Response
    {
        if ($user->isSuperAdmin()) {
            return Response::allow();
        }

        if ($user->role !== Role::CoopAdmin) {
            return Response::deny();
        }

        if ($model->cooperative_id !== $user->cooperative_id) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }
}
