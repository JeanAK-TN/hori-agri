<?php

namespace App\Models;

use App\Enums\SoilType;
use App\Models\Concerns\BelongsToCooperative;
use Database\Factories\PlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cooperative_id
 * @property int $farmer_id
 * @property string $name
 * @property string $area_ha
 * @property SoilType $soil_type
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $previous_crop
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'farmer_id',
    'name',
    'area_ha',
    'soil_type',
    'latitude',
    'longitude',
    'previous_crop',
])]
class Plot extends Model
{
    /** @use HasFactory<PlotFactory> */
    use BelongsToCooperative, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'soil_type' => SoilType::class,
            'area_ha' => 'decimal:3',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }
}
