<?php

namespace App\Models;

use App\Enums\RecoStatus;
use App\Models\Concerns\BelongsToExploitation;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The output of the engine (SPEC section 6.2).
 *
 * @property int $id
 * @property int $exploitation_id
 * @property int $crop_cycle_id
 * @property int $rule_id
 * @property int $priority
 * @property string $generated_message
 * @property RecoStatus $status
 * @property Carbon $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['rule_id', 'priority', 'generated_message', 'status', 'generated_at'])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use BelongsToExploitation, HasFactory;

    /**
     * A recommendation is born new: the farmer moves it along.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => RecoStatus::New->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'status' => RecoStatus::class,
            'generated_at' => 'datetime',
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
     * The action, the source and the origin of the threshold are read
     * through this relation, never copied (SPEC section 9.7).
     *
     * @return BelongsTo<Rule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    /**
     * @return HasOne<Feedback, $this>
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    /**
     * The producer this recommendation concerns, reached through the
     * plot. This chain is what the policy checks before letting anyone
     * answer (SPEC section 11.2).
     */
    public function farmer(): ?Farmer
    {
        return $this->cropCycle?->plot?->farmer;
    }

    /**
     * Reading a new recommendation marks it read. An answer already
     * given is never walked back by a simple visit.
     */
    public function markRead(): void
    {
        if ($this->status === RecoStatus::New) {
            $this->update(['status' => RecoStatus::Read]);
        }
    }
}
