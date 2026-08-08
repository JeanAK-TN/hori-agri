<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Models\Concerns\BelongsToCooperative;
use Database\Factories\CropCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The pivot class of the model: a plot, a crop, an optional variety and
 * a campaign (SPEC section 6).
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $plot_id
 * @property int $crop_id
 * @property int|null $variety_id
 * @property int $campaign_id
 * @property Carbon $sowing_date
 * @property int|null $density
 * @property CycleStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'plot_id',
    'crop_id',
    'variety_id',
    'campaign_id',
    'sowing_date',
    'density',
])]
class CropCycle extends Model
{
    /** @use HasFactory<CropCycleFactory> */
    use BelongsToCooperative, HasFactory;

    /**
     * A cycle always opens as planned: the status is never taken from a
     * form, it only ever moves through the transitions below.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => CycleStatus::Planned->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sowing_date' => 'date',
            'density' => 'integer',
            'status' => CycleStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Plot, $this>
     */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * @return BelongsTo<Crop, $this>
     */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    /**
     * @return BelongsTo<Variety, $this>
     */
    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<Operation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    /**
     * @return HasMany<Harvest, $this>
     */
    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    /**
     * @return HasOne<Indicator, $this>
     */
    public function indicator(): HasOne
    {
        return $this->hasOne(Indicator::class);
    }

    /*
    |--------------------------------------------------------------------
    | Diagramme d'états (SPEC section 13, module 3)
    |--------------------------------------------------------------------
    |
    | planned --(première opération)--> in_progress --(clôture)--> harvested
    |    \___________________(abandon à tout moment)___________________/
    |
    | Une transition invalide est un défaut de programmation, pas une
    | erreur d'utilisateur : elle lève une LogicException.
    */

    /**
     * Called when the first operation of the cycle is recorded. Doing it
     * again on an already started cycle is a no-op, so that the caller
     * does not have to test the status.
     */
    public function markInProgress(): void
    {
        if ($this->status === CycleStatus::InProgress) {
            return;
        }

        $this->transitionTo(CycleStatus::InProgress, from: [CycleStatus::Planned]);
    }

    /**
     * Close the cycle once the harvest is over.
     */
    public function close(): void
    {
        $this->transitionTo(CycleStatus::Harvested, from: [
            CycleStatus::Planned,
            CycleStatus::InProgress,
        ]);
    }

    /**
     * Give up the cycle. Possible at any time, except on a cycle that is
     * already abandoned.
     */
    public function abandon(): void
    {
        $this->transitionTo(CycleStatus::Abandoned, from: [
            CycleStatus::Planned,
            CycleStatus::InProgress,
            CycleStatus::Harvested,
        ]);
    }

    /**
     * @param  array<int, CycleStatus>  $from
     */
    private function transitionTo(CycleStatus $target, array $from): void
    {
        if (! in_array($this->status, $from, true)) {
            throw new LogicException(sprintf(
                'Un cycle %s ne peut pas passer à l\'état %s.',
                $this->status->value,
                $target->value,
            ));
        }

        $this->forceFill(['status' => $target])->save();
    }
}
