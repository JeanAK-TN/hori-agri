<?php

namespace App\Models;

use Database\Factories\VarietyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Common variety registry (SPEC section 6.2), not scoped either.
 *
 * @property int $id
 * @property int $crop_id
 * @property string $name
 * @property int|null $cycle_days
 * @property string|null $potential_yield_kg_ha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['crop_id', 'name', 'cycle_days', 'potential_yield_kg_ha'])]
class Variety extends Model
{
    /** @use HasFactory<VarietyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle_days' => 'integer',
            'potential_yield_kg_ha' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Crop, $this>
     */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }
}
