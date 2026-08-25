<?php

namespace App\Models;

use App\Enums\RuleFamily;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Models\Scopes\CurrentExploitation;
use App\Models\Scopes\ExploitationRuleScope;
use Database\Factories\RuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One row of the knowledge base (SPEC section 6.2).
 *
 * The condition is a tree of conditions in JSON, deliberately poor so it
 * stays readable by the technical lead of an exploitation. No agronomic
 * condition ever appears in a PHP file (rule 4).
 *
 * @property int $id
 * @property int|null $exploitation_id
 * @property string $code
 * @property int|null $crop_id
 * @property RuleFamily $family
 * @property array<string, mixed> $condition
 * @property Severity $severity
 * @property string $message
 * @property string $action
 * @property string|null $estimated_gain
 * @property string $source
 * @property ThresholdStatus $threshold_status
 * @property string|null $threshold_note
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'crop_id',
    'family',
    'condition',
    'severity',
    'message',
    'action',
    'estimated_gain',
    'source',
    'threshold_status',
    'threshold_note',
    'enabled',
])]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new ExploitationRuleScope);

        // Une règle créée par un administrateur d'exploitation lui
        // appartient ; une règle créée par le super_admin est commune.
        static::creating(function (Rule $rule): void {
            $rule->exploitation_id ??= CurrentExploitation::id();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'family' => RuleFamily::class,
            'condition' => 'array',
            'severity' => Severity::class,
            'threshold_status' => ThresholdStatus::class,
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Exploitation, $this>
     */
    public function exploitation(): BelongsTo
    {
        return $this->belongsTo(Exploitation::class);
    }

    /**
     * @return BelongsTo<Crop, $this>
     */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * A rule carried by an exploitation overrides the common one that
     * shares its code (SPEC section 9.6).
     */
    public function isOverride(): bool
    {
        return $this->exploitation_id !== null;
    }

    /**
     * The first number the estimated gain contains, which is what the
     * sorting compares. A label without any number counts as zero
     * (SPEC section 9.7).
     */
    public function estimatedGainValue(): float
    {
        if ($this->estimated_gain === null) {
            return 0.0;
        }

        // Les libellés s'écrivent « +300 kg/ha » ou « 20 000 FCFA/ha » :
        // les espaces qui séparent les milliers sont retirés d'abord.
        $normalized = str_replace([' ', ' '], '', $this->estimated_gain);

        if (preg_match('/-?\d+(?:[.,]\d+)?/', $normalized, $matches) !== 1) {
            return 0.0;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }
}
