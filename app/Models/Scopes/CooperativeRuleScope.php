<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The isolation scope of the knowledge base (SPEC section 7.2).
 *
 * Unlike every other business table, a cooperative sees the rules whose
 * cooperative_id is null — the ones common to the whole solution — as
 * well as its own. Hence a dedicated scope rather than CooperativeScope.
 *
 * @implements Scope<Model>
 */
class CooperativeRuleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $cooperativeId = CurrentCooperative::id();

        // Ni coopérative ni utilisateur : super_admin ou traitement de
        // fond, qui voient la base entière.
        if ($cooperativeId === null) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $cooperativeId): void {
            $query->whereNull($model->qualifyColumn('cooperative_id'))
                ->orWhere($model->qualifyColumn('cooperative_id'), $cooperativeId);
        });
    }
}
