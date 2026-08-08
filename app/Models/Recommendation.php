<?php

namespace App\Models;

use App\Enums\RecoStatus;
use App\Models\Concerns\BelongsToCooperative;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The output of the engine (SPEC section 6.2).
 *
 * @property int $id
 * @property int $cooperative_id
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
    use BelongsToCooperative, HasFactory;

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
}
