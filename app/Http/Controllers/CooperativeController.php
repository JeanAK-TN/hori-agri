<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCooperativeRequest;
use App\Http\Requests\UpdateCooperativeRequest;
use App\Models\Cooperative;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CooperativeController extends Controller
{
    /**
     * List every cooperative of the solution.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Cooperative::class);

        $cooperatives = Cooperative::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Cooperative $cooperative): array => [
                'id' => $cooperative->id,
                'name' => $cooperative->name,
                'region' => $cooperative->region,
                'prefecture' => $cooperative->prefecture,
                'contact' => $cooperative->contact,
                'users_count' => $cooperative->users_count,
                'suspended' => $cooperative->isSuspended(),
            ]);

        return Inertia::render('cooperatives/index', [
            'cooperatives' => $cooperatives,
        ]);
    }

    /**
     * Register a new cooperative.
     */
    public function store(StoreCooperativeRequest $request): RedirectResponse
    {
        Cooperative::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coopérative créée.']);

        return to_route('cooperatives.index');
    }

    /**
     * Update the details of a cooperative.
     */
    public function update(UpdateCooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $cooperative->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coopérative mise à jour.']);

        return to_route('cooperatives.index');
    }

    /**
     * Suspend a cooperative: its members can no longer sign in.
     */
    public function suspend(Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('suspend', $cooperative);

        $cooperative->suspend();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coopérative suspendue.']);

        return to_route('cooperatives.index');
    }

    /**
     * Lift the suspension of a cooperative.
     */
    public function reactivate(Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('suspend', $cooperative);

        $cooperative->reactivate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coopérative réactivée.']);

        return to_route('cooperatives.index');
    }
}
