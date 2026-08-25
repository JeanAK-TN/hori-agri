<?php

namespace App\Models;

use App\Enums\Quality;
use App\Models\Concerns\BelongsToExploitation;
use Database\Factories\HarvestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exploitation_id
 * @property int $crop_cycle_id
 * @property Carbon $date
 * @property string $quantity_kg
 * @property string|null $unit_price
 * @property Quality|null $quality
 * @property string|null $post_harvest_losses_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crop_cycle_id',
    'date',
    'quantity_kg',
    'unit_price',
    'quality',
    'post_harvest_losses_kg',
])]
class Harvest extends Model
{
    /** @use HasFactory<HarvestFactory> */
    use BelongsToExploitation, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity_kg' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'quality' => Quality::class,
            'post_harvest_losses_kg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }
}
