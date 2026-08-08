<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CropCycle;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Recording operations is the work of the technician (SPEC section 5).
 * The cooperative administrator reads the itinerary but does not fill
 * it in.
 */
class OperationPolicy
{
    /**
     * A closed or given-up cycle no longer accepts entries.
     */
    public function create(User $user, CropCycle $cycle): Response
    {
        if ($user->role !== Role::Technician) {
            return Response::deny('Seul un technicien enregistre les opérations.');
        }

        return $cycle->status->isOpen()
            ? Response::allow()
            : Response::deny('Ce cycle est terminé : il n\'accepte plus de saisie.');
    }

    public function update(User $user, Operation $operation): Response
    {
        return $this->create($user, $operation->cropCycle);
    }

    public function delete(User $user, Operation $operation): Response
    {
        return $this->create($user, $operation->cropCycle);
    }
}
