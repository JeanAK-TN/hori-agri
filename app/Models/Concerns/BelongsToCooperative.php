<?php

namespace App\Models\Concerns;

use App\Models\Cooperative;
use App\Models\Scopes\CooperativeScope;
use App\Models\Scopes\CurrentCooperative;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a business model as belonging to a cooperative (SPEC section 7.1).
 *
 * Applies the isolation scope on every query and fills cooperative_id on
 * creation without any intervention from the calling code.
 */
trait BelongsToCooperative
{
    public static function bootBelongsToCooperative(): void
    {
        static::addGlobalScope(new CooperativeScope);

        static::creating(function (self $model): void {
            $model->cooperative_id ??= CurrentCooperative::id();
        });
    }

    /**
     * @return BelongsTo<Cooperative, $this>
     */
    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }
}
