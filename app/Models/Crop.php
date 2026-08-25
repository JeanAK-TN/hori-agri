<?php

namespace App\Models;

use Database\Factories\CropFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Common crop registry, shared by every exploitation and maintained by
 * the super_admin. Deliberately not scoped (SPEC section 7.1).
 *
 * Every reference value below comes from the seed files, never from the
 * code: no agronomic value is ever written in PHP (SPEC rule 1).
 *
 * @property int $id
 * @property string $name
 * @property string|null $family
 * @property int $cycle_days
 * @property int $reference_density
 * @property string $reference_yield_kg_ha
 * @property array<string, int|float>|null $npk_needs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'family',
    'cycle_days',
    'reference_density',
    'reference_yield_kg_ha',
    'npk_needs',
])]
class Crop extends Model
{
    /** @use HasFactory<CropFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle_days' => 'integer',
            'reference_density' => 'integer',
            'reference_yield_kg_ha' => 'decimal:2',
            'npk_needs' => 'array',
        ];
    }

    /**
     * @return HasMany<Variety, $this>
     */
    public function varieties(): HasMany
    {
        return $this->hasMany(Variety::class);
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }
}
