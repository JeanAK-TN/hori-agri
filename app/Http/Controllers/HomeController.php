<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * La racine mène chacun à son premier écran (SPEC-CORRECTION
     * section 9).
     *
     * La page d'accueil du kit de départ n'appartenait pas à
     * l'application : elle a disparu, et personne n'atterrit plus sur un
     * écran vide.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return to_route(match ($request->user()->role) {
            Role::SuperAdmin => 'exploitations.index',
            Role::ExploitationAdmin => 'exploitation-dashboard.index',
            Role::Technician => 'cycles.index',
            Role::Farmer => 'recommendations.index',
        });
    }
}
