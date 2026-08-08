<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\Role;
use App\Http\Requests\StoreFarmerRequest;
use App\Http\Requests\UpdateFarmerRequest;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FarmerController extends Controller
{
    /**
     * List the producers of the current cooperative. The global scope
     * does the isolation, so no cooperative clause appears here.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Farmer::class);

        $search = $request->string('recherche')->toString();

        $farmers = Farmer::query()
            ->search($search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Farmer $farmer): array => [
                'id' => $farmer->id,
                'first_name' => $farmer->first_name,
                'last_name' => $farmer->last_name,
                'village' => $farmer->village,
                'phone' => $farmer->phone,
                'experience_years' => $farmer->experience_years,
                'has_account' => $farmer->user_id !== null,
            ]);

        return Inertia::render('farmers/index', [
            'farmers' => $farmers,
            'filters' => ['recherche' => $search],
        ]);
    }

    /**
     * Show the record of a producer.
     */
    public function show(Farmer $farmer): Response
    {
        $this->authorize('view', $farmer);

        $farmer->load(['user:id,name,phone', 'plots']);

        return Inertia::render('farmers/show', [
            'farmer' => $this->present($farmer),
            'plots' => $farmer->plots
                ->sortBy('name')
                ->values()
                ->map(fn (Plot $plot): array => [
                    'id' => $plot->id,
                    'name' => $plot->name,
                    'area_ha' => $plot->area_ha,
                    'soil_type_label' => $plot->soil_type->label(),
                ])
                ->all(),
        ]);
    }

    /**
     * Show the form used to register a producer.
     */
    public function create(): Response
    {
        $this->authorize('create', Farmer::class);

        return Inertia::render('farmers/create', [
            'genders' => Gender::options(),
            'availableAccounts' => $this->availableAccounts(),
        ]);
    }

    /**
     * Register a producer.
     */
    public function store(StoreFarmerRequest $request): RedirectResponse
    {
        $farmer = Farmer::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producteur enregistré.']);

        return to_route('farmers.show', $farmer);
    }

    /**
     * Show the form used to correct a producer record.
     */
    public function edit(Farmer $farmer): Response
    {
        $this->authorize('update', $farmer);

        return Inertia::render('farmers/edit', [
            'farmer' => $this->present($farmer),
            'genders' => Gender::options(),
            'availableAccounts' => $this->availableAccounts($farmer),
        ]);
    }

    /**
     * Update a producer record.
     */
    public function update(UpdateFarmerRequest $request, Farmer $farmer): RedirectResponse
    {
        $farmer->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producteur mis à jour.']);

        return to_route('farmers.show', $farmer);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Farmer $farmer): array
    {
        return [
            'id' => $farmer->id,
            'first_name' => $farmer->first_name,
            'last_name' => $farmer->last_name,
            'full_name' => $farmer->fullName(),
            'gender' => $farmer->gender?->value,
            'gender_label' => $farmer->gender?->label(),
            'birth_year' => $farmer->birth_year,
            'experience_years' => $farmer->experience_years,
            'village' => $farmer->village,
            'phone' => $farmer->phone,
            'user_id' => $farmer->user_id,
            'account' => $farmer->user === null ? null : [
                'id' => $farmer->user->id,
                'name' => $farmer->user->name,
                'phone' => $farmer->user->phone,
            ],
        ];
    }

    /**
     * Farmer accounts of the cooperative that are not attached to any
     * producer yet, plus the one already attached to the record being
     * edited so that it stays selectable.
     *
     * @return array<int, array{id: int, name: string, phone: string}>
     */
    private function availableAccounts(?Farmer $farmer = null): array
    {
        $linked = Farmer::query()
            ->whereNotNull('user_id')
            ->when($farmer !== null, fn ($query) => $query->whereKeyNot($farmer->id))
            ->pluck('user_id');

        return User::query()
            ->visibleTo(request()->user())
            ->where('role', Role::Farmer)
            ->whereNotIn('id', $linked)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ])
            ->all();
    }
}
