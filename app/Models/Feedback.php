<?php

namespace App\Models;

use App\Models\Concerns\BelongsToExploitation;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The farmer's answer to one recommendation (SPEC section 6.2).
 *
 * `farmer_id` never comes from the form: it is the producer sheet of the
 * signed-in account, resolved server side.
 *
 * @property int $id
 * @property int $exploitation_id
 * @property int $recommendation_id
 * @property int $farmer_id
 * @property bool $applied
 * @property string|null $comment
 * @property string|null $observed_result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['applied', 'comment', 'observed_result'])]
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use BelongsToExploitation, HasFactory;

    /**
     * « Feedback » se pluralise en « feedback » pour Laravel ; la table
     * imposée par la spécification est `feedbacks` (SPEC section 6.2).
     *
     * @var string
     */
    protected $table = 'feedbacks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
