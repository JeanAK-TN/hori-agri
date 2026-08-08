<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HarvestPolicy
{
    public function create(User $user, CropCycle $cycle): Response
    {
        if ($user->role !== Role::Technician) {
            return Response::deny('Seul un technicien enregistre les récoltes.');
        }

        return $cycle->status->isOpen()
            ? Response::allow()
            : Response::deny('Ce cycle est terminé : il n\'accepte plus de saisie.');
    }

    public function update(User $user, Harvest $harvest): Response
    {
        return $this->create($user, $harvest->cropCycle);
    }

    public function delete(User $user, Harvest $harvest): Response
    {
        return $this->create($user, $harvest->cropCycle);
    }
}
