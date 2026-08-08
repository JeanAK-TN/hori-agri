<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCooperative;
use App\Services\Indicators\IndicatorService;
use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The ten computed values of a cycle (SPEC section 9.2). Nothing here is
 * ever entered by hand: IndicatorService is the only writer.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $crop_cycle_id
 * @property string|null $yield_kg_ha
 * @property string|null $production_cost_ha
 * @property string|null $gross_margin_ha
 * @property string|null $input_cost_share
 * @property string|null $zone_median_yield_kg_ha
 * @property string|null $zone_median_cost_ha
 * @property int $zone_plot_count
 * @property string|null $yield_vs_zone_median
 * @property string|null $cost_vs_zone_median
 * @property Carbon $computed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'yield_kg_ha',
    'production_cost_ha',
    'gross_margin_ha',
    'input_cost_share',
    'zone_median_yield_kg_ha',
    'zone_median_cost_ha',
    'zone_plot_count',
    'yield_vs_zone_median',
    'cost_vs_zone_median',
    'computed_at',
])]
class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use BelongsToCooperative, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'yield_kg_ha' => 'decimal:2',
            'production_cost_ha' => 'decimal:2',
            'gross_margin_ha' => 'decimal:2',
            'input_cost_share' => 'decimal:3',
            'zone_median_yield_kg_ha' => 'decimal:2',
            'zone_median_cost_ha' => 'decimal:2',
            'zone_plot_count' => 'integer',
            'yield_vs_zone_median' => 'decimal:3',
            'cost_vs_zone_median' => 'decimal:3',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * Whether the comparison zone is large enough for the two ratios to
     * mean anything (SPEC section 9.3). Below five comparable cycles the
     * family C rules stay suspended.
     */
    public function hasComparableZone(): bool
    {
        return $this->zone_plot_count >= IndicatorService::MINIMUM_ZONE_SIZE;
    }
}
