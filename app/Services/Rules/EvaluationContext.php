<?php

namespace App\Services\Rules;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Models\Campaign;
use App\Models\CropCycle;
use App\Models\Operation;
use Illuminate\Support\Collection;

/**
 * The immutable object a rule reads (SPEC section 9.4).
 *
 * It exposes exactly fifteen fields. No other field is interrogable by a
 * rule: adding one means changing this class and documenting it in the
 * specification.
 *
 * A null field is not zero. A condition on a null field is false, except
 * for the operators != and not_in where it is true — that behaviour
 * lives in RuleEvaluator and is the most likely source of error of the
 * whole engine.
 */
final readonly class EvaluationContext
{
    /**
     * French labels and units, used to complete the message of a
     * recommendation with the values that made it fire (SPEC 9.7).
     */
    public const FIELD_LABELS = [
        'crop_name' => ['libellé' => 'culture', 'unité' => ''],
        'region' => ['libellé' => 'région', 'unité' => ''],
        'sowing_month' => ['libellé' => 'mois de semis', 'unité' => ''],
        'sowing_density_plants_ha' => ['libellé' => 'densité de semis', 'unité' => 'plants/ha'],
        'seed_rate_kg_ha' => ['libellé' => 'dose de semence', 'unité' => 'kg/ha'],
        'npk_dose_kg_ha' => ['libellé' => 'dose de NPK', 'unité' => 'kg/ha'],
        'urea_dose_kg_ha' => ['libellé' => 'dose d\'urée', 'unité' => 'kg/ha'],
        'potassium_dose_kg_ha' => ['libellé' => 'dose de potassium', 'unité' => 'kg/ha'],
        'inoculant_used' => ['libellé' => 'inoculant employé', 'unité' => ''],
        'first_weeding_days_after_sowing' => ['libellé' => 'premier sarclage', 'unité' => 'jours après semis'],
        'urea_days_after_sowing' => ['libellé' => 'apport d\'urée', 'unité' => 'jours après semis'],
        'consecutive_seasons_same_crop' => ['libellé' => 'campagnes consécutives de la même culture', 'unité' => ''],
        'yield_vs_zone_median_ratio' => ['libellé' => 'rendement face à la médiane de la zone', 'unité' => ''],
        'cost_vs_zone_median_ratio' => ['libellé' => 'coût face à la médiane de la zone', 'unité' => ''],
        'input_cost_share_of_harvest_value' => ['libellé' => 'part des intrants dans la récolte', 'unité' => ''],
    ];

    public function __construct(
        public string $crop_name,
        public string $region,
        public int $sowing_month,
        public ?int $sowing_density_plants_ha,
        public ?float $seed_rate_kg_ha,
        public ?float $npk_dose_kg_ha,
        public ?float $urea_dose_kg_ha,
        public ?float $potassium_dose_kg_ha,
        public bool $inoculant_used,
        public ?int $first_weeding_days_after_sowing,
        public ?int $urea_days_after_sowing,
        public int $consecutive_seasons_same_crop,
        public ?float $yield_vs_zone_median_ratio,
        public ?float $cost_vs_zone_median_ratio,
        public ?float $input_cost_share_of_harvest_value,
    ) {}

    /**
     * Derive the context of a cycle. Every query needed lives here, so
     * that the evaluator itself never touches the database.
     */
    public static function fromCycle(CropCycle $cycle): self
    {
        $cycle->loadMissing([
            'crop',
            'plot',
            'campaign',
            'cooperative',
            'operations',
            'indicator',
        ]);

        $area = (float) $cycle->plot->area_ha;
        $operations = $cycle->operations;
        $indicator = $cycle->indicator;

        return new self(
            crop_name: $cycle->crop->name,
            region: $cycle->cooperative->region,
            sowing_month: (int) $cycle->sowing_date->month,
            sowing_density_plants_ha: $cycle->density,
            seed_rate_kg_ha: self::dosePerHectare(
                $operations->filter(fn (Operation $operation): bool => $operation->type === OperationType::Sowing),
                InputType::Seed,
                $area,
            ),
            npk_dose_kg_ha: self::dosePerHectare($operations, InputType::Npk, $area),
            urea_dose_kg_ha: self::dosePerHectare($operations, InputType::Urea, $area),
            potassium_dose_kg_ha: self::dosePerHectare($operations, InputType::Potassium, $area),
            inoculant_used: $operations->contains(
                fn (Operation $operation): bool => $operation->input_type === InputType::Inoculant,
            ),
            first_weeding_days_after_sowing: self::daysAfterSowing(
                $cycle,
                $operations->filter(fn (Operation $operation): bool => $operation->type === OperationType::Weeding),
            ),
            urea_days_after_sowing: self::daysAfterSowing(
                $cycle,
                $operations->filter(fn (Operation $operation): bool => $operation->type === OperationType::Fertilization
                    && $operation->input_type === InputType::Urea),
            ),
            consecutive_seasons_same_crop: self::consecutiveSeasons($cycle),
            yield_vs_zone_median_ratio: self::asFloat($indicator?->yield_vs_zone_median),
            cost_vs_zone_median_ratio: self::asFloat($indicator?->cost_vs_zone_median),
            input_cost_share_of_harvest_value: self::asFloat($indicator?->input_cost_share),
        );
    }

    /**
     * Read a field by name, refusing anything outside the fifteen.
     */
    public function get(string $field): mixed
    {
        if (! array_key_exists($field, self::FIELD_LABELS)) {
            throw InvalidConditionException::unknownField($field);
        }

        return $this->{$field};
    }

    /**
     * The value of a field, written out for a human, with its unit.
     */
    public function describe(string $field): string
    {
        $value = $this->get($field);
        $label = self::FIELD_LABELS[$field]['libellé'];
        $unit = self::FIELD_LABELS[$field]['unité'];

        if ($value === null) {
            return $label.' non renseignée';
        }

        if (is_bool($value)) {
            return $label.' : '.($value ? 'oui' : 'non');
        }

        $formatted = is_float($value)
            ? rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',')
            : (string) $value;

        return trim($label.' : '.$formatted.' '.$unit);
    }

    /**
     * The sum of the quantities of an input, converted to kilograms and
     * divided by the area (SPEC section 9.4).
     *
     * A quantity only counts if its unit converts to a mass: litres and
     * units are ignored. An operation without quantity or without unit
     * is ignored too. When nothing is eligible the dose is null, which
     * is not the same as zero.
     *
     * @param  Collection<int, Operation>  $operations
     */
    private static function dosePerHectare(Collection $operations, InputType $inputType, float $area): ?float
    {
        if ($area <= 0) {
            return null;
        }

        $eligible = $operations
            ->filter(fn (Operation $operation): bool => $operation->input_type === $inputType)
            ->map(fn (Operation $operation): ?float => $operation->quantityInKilograms())
            ->filter(fn (?float $kilograms): bool => $kilograms !== null);

        if ($eligible->isEmpty()) {
            return null;
        }

        return round($eligible->sum() / $area, 4);
    }

    /**
     * Days between the sowing and the earliest operation of a set.
     *
     * @param  Collection<int, Operation>  $operations
     */
    private static function daysAfterSowing(CropCycle $cycle, Collection $operations): ?int
    {
        $first = $operations->sortBy('date')->first();

        if ($first === null) {
            return null;
        }

        return (int) $cycle->sowing_date->startOfDay()->diffInDays($first->date->startOfDay());
    }

    /**
     * Number of consecutive campaigns, ordered by start date, where the
     * same plot carried the same crop, the current cycle included
     * (SPEC section 9.4).
     *
     * The count stops at the first campaign that carried another crop or
     * nothing at all.
     */
    private static function consecutiveSeasons(CropCycle $cycle): int
    {
        $campaigns = Campaign::query()
            // La coopérative du cycle, et non celle de l'utilisateur :
            // le calcul tourne aussi hors contexte HTTP.
            ->where('cooperative_id', $cycle->cooperative_id)
            ->where('start_date', '<=', $cycle->campaign->start_date)
            ->orderByDesc('start_date')
            ->pluck('id');

        $seasons = 0;

        foreach ($campaigns as $campaignId) {
            $carriedSameCrop = CropCycle::query()
                ->where('cooperative_id', $cycle->cooperative_id)
                ->where('plot_id', $cycle->plot_id)
                ->where('crop_id', $cycle->crop_id)
                ->where('campaign_id', $campaignId)
                ->exists();

            if (! $carriedSameCrop) {
                break;
            }

            $seasons++;
        }

        // Le cycle courant en fait partie : la suite vaut au moins un.
        return max($seasons, 1);
    }

    private static function asFloat(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
