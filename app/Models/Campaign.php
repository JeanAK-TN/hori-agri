<?php

namespace App\Models;

use App\Enums\SeasonType;
use App\Models\Concerns\BelongsToCooperative;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cooperative_id
 * @property string $label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property SeasonType $season_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'start_date', 'end_date', 'season_type'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToCooperative, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'season_type' => SeasonType::class,
        ];
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /**
     * Whether a date falls inside the bounds of the campaign. Every date
     * recorded on a cycle is checked against them (SPEC section 6.2).
     */
    public function covers(Carbon $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }
}
