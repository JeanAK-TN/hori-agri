<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Multi-tenant isolation scope (SPEC section 7).
 *
 * Restricts every query on a business model to the current cooperative.
 * When no cooperative resolves (guest, super_admin, background context
 * without an explicit CurrentCooperative::set()), no constraint is added.
 *
 * @implements Scope<Model>
 */
class CooperativeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $cooperativeId = CurrentCooperative::id();

        if ($cooperativeId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('cooperative_id'), $cooperativeId);
    }
}
