<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Recommendation;
use App\Models\User;

/**
 * Qui lit une recommandation, et qui y répond (SPEC sections 5 et 11.2).
 *
 * Le technicien et l'administrateur suivent les producteurs de leur
 * coopérative : ils lisent. Seul l'agriculteur propriétaire de la
 * parcelle concernée change l'état d'une recommandation et enregistre un
 * retour.
 *
 * L'isolation entre coopératives est déjà tenue par le scope global : une
 * recommandation d'une autre coopérative n'est jamais liée à la route, la
 * réponse est un 404. Ce qui se joue ici est le cas intra-coopérative,
 * qui répond 403.
 */
class RecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::CoopAdmin, Role::Technician, Role::Farmer], true);
    }

    public function view(User $user, Recommendation $recommendation): bool
    {
        if ($user->role === Role::Farmer) {
            return $this->owns($user, $recommendation);
        }

        return in_array($user->role, [Role::CoopAdmin, Role::Technician], true);
    }

    /**
     * Marquer une recommandation appliquée ou écartée, et dire ce qui a
     * été observé : c'est le geste de l'agriculteur, personne ne le fait
     * à sa place.
     */
    public function respond(User $user, Recommendation $recommendation): bool
    {
        return $user->role === Role::Farmer && $this->owns($user, $recommendation);
    }

    /**
     * La chaîne recommandation → cycle → parcelle → producteur → compte.
     */
    private function owns(User $user, Recommendation $recommendation): bool
    {
        $farmer = $recommendation->farmer();

        return $farmer !== null && $farmer->user_id === $user->id;
    }
}
