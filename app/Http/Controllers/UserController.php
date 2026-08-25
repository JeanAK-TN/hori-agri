<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Exploitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List the accounts the current user is allowed to see: every account
     * for the super_admin, the accounts of their own exploitation for an
     * exploitation administrator.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $users = User::query()
            ->visibleTo($viewer)
            ->with('exploitation:id,name')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'exploitation' => $user->exploitation?->only(['id', 'name']),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'assignableRoles' => collect(Role::assignableBy($viewer->role))
                ->map(fn (Role $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values(),
            'exploitations' => $viewer->isSuperAdmin()
                ? Exploitation::query()->orderBy('name')->get(['id', 'name'])
                : null,
        ]);
    }

    /**
     * Create an account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compte créé.']);

        return to_route('users.index');
    }

    /**
     * Update an account.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->attributesToUpdate());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compte mis à jour.']);

        return to_route('users.index');
    }
}
