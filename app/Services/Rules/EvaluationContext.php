<?php

namespace App\Services\Rules;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Models\CropCycle;
use App\Models\Operation;
use Illuminate\Support\Collection;

/**
 * The immutable object a rule reads (SPEC section 9.4, corrigée).
 *
 * It exposes exactly seven fields, taillés pour le maraîchage : la fiche
 * togolaise de la tomate compte le calendrier de fertilisation à partir
 * du repiquage, pas du semis. No other field is interrogable by a rule:
 * adding one means changing this class and documenting it in the
 * specification.
 *
 * A null field is not zero. A condition on a null field is false, except
 * for the operators != and not_in where it is true — that behaviour
 * lives in RuleEvaluator and is the most likely source of error of the
 * whole engine. Chez INSECTGO la densité n'est pas renseignée, parce que
 * la ferme travaille en planches : TOM-A01 ne se déclenche alors pas, et
 * c'est le résultat attendu. Le moteur ne conclut pas plutôt que de
 * conclure mal.
 */
final readonly class EvaluationContext
{
    /**
     * French labels and units, used to complete the message of a
     * recommendation with the values that made it fire (SPEC 9.7).
     */
    public const FIELD_LABELS = [
        'crop_name' => ['libellé' => 'culture', 'unité' => ''],
        'planting_density_plants_ha' => ['libellé' => 'densité de plantation', 'unité' => 'plants/ha'],
        'topdressing_npk_kg_ha' => ['libellé' => 'NPK de couverture cumulé', 'unité' => 'kg/ha'],
        'organic_matter_t_ha' => ['libellé' => 'matière organique apportée', 'unité' => 't/ha'],
        'first_topdressing_days_after_transplanting' => ['libellé' => 'premier apport de couverture', 'unité' => 'jours après repiquage'],
        'yield_kg_ha' => ['libellé' => 'rendement', 'unité' => 'kg/ha'],
        'zone_median_yield_kg_ha' => ['libellé' => 'rendement médian des parcelles comparables', 'unité' => 'kg/ha'],
    ];

    public function __construct(
        public string $crop_name,
        public ?int $planting_density_plants_ha,
        public ?float $topdressing_npk_kg_ha,
        public ?float $organic_matter_t_ha,
        public ?int $first_topdressing_days_after_transplanting,
        public ?float $yield_kg_ha,
        public ?float $zone_median_yield_kg_ha,
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
            'operations',
            'harvests',
            'indicator',
        ]);

        $area = (float) $cycle->plot->area_ha;
        $operations = $cycle->operations;

        return new self(
            crop_name: $cycle->crop->name,
            planting_density_plants_ha: $cycle->density,
            topdressing_npk_kg_ha: self::topdressingNpkPerHectare($cycle, $operations, $area),
            organic_matter_t_ha: self::organicMatterPerHectare($operations, $area),
            first_topdressing_days_after_transplanting: self::firstTopdressingDaysAfterTransplanting($cycle, $operations),
            yield_kg_ha: self::yieldPerHectare($cycle, $area),
            zone_median_yield_kg_ha: self::asFloat($cycle->indicator?->zone_median_yield_kg_ha),
        );
    }

    /**
     * Read a field by name, refusing anything outside the seven.
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
            return $label.' : valeur non renseignée';
        }

        $formatted = is_float($value)
            ? rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',')
            : (string) $value;

        return trim($label.' : '.$formatted.' '.$unit);
    }

    /**
     * L'apport de couverture : la somme des fertilisations NPK
     * postérieures au repiquage, ramenée à l'hectare (SPEC-CORRECTION
     * section 4.1).
     *
     * Sans date de repiquage, impossible de séparer la fumure de fond de
     * la couverture : le champ reste nul et TOM-A02 ne conclut pas. Une
     * fertilisation portée au jour même du repiquage compte comme fumure
     * de fond. A quantity only counts if its unit converts to a mass;
     * when nothing is eligible the dose is null, which is not zero.
     *
     * @param  Collection<int, Operation>  $operations
     */
    private static function topdressingNpkPerHectare(
        CropCycle $cycle,
        Collection $operations,
        float $area,
    ): ?float {
        if ($cycle->transplanting_date === null || $area <= 0) {
            return null;
        }

        $eligible = $operations
            ->filter(fn (Operation $operation): bool => $operation->type === OperationType::Fertilization
                && $operation->input_type === InputType::Npk
                && $operation->date->greaterThan($cycle->transplanting_date))
            ->map(fn (Operation $operation): ?float => $operation->quantityInKilograms())
            ->filter(fn (?float $kilograms): bool => $kilograms !== null);

        if ($eligible->isEmpty()) {
            return null;
        }

        return round($eligible->sum() / $area, 4);
    }

    /**
     * La matière organique apportée sur le cycle, en tonnes par hectare :
     * toutes les opérations dont la nature d'intrant est organique,
     * quelle que soit leur place dans le calendrier (le minimum de la
     * fiche est annuel).
     *
     * @param  Collection<int, Operation>  $operations
     */
    private static function organicMatterPerHectare(Collection $operations, float $area): ?float
    {
        if ($area <= 0) {
            return null;
        }

        $eligible = $operations
            ->filter(fn (Operation $operation): bool => $operation->input_type === InputType::Organic)
            ->map(fn (Operation $operation): ?float => $operation->quantityInKilograms())
            ->filter(fn (?float $kilograms): bool => $kilograms !== null);

        if ($eligible->isEmpty()) {
            return null;
        }

        return round($eligible->sum() / 1000 / $area, 4);
    }

    /**
     * Le nombre de jours entre le repiquage et la première opération de
     * fertilisation postérieure à cette date (SPEC-CORRECTION section
     * 5.2). Si la date de repiquage est nulle, le champ vaut null et
     * TOM-B01 ne se déclenche pas.
     *
     * @param  Collection<int, Operation>  $operations
     */
    private static function firstTopdressingDaysAfterTransplanting(
        CropCycle $cycle,
        Collection $operations,
    ): ?int {
        if ($cycle->transplanting_date === null) {
            return null;
        }

        $first = $operations
            ->filter(fn (Operation $operation): bool => $operation->type === OperationType::Fertilization
                && $operation->date->greaterThan($cycle->transplanting_date))
            ->sortBy('date')
            ->first();

        if ($first === null) {
            return null;
        }

        return (int) $cycle->transplanting_date->startOfDay()
            ->diffInDays($first->date->startOfDay());
    }

    /**
     * La somme des récoltes du cycle sur la superficie de la parcelle :
     * la tomate se récolte en plusieurs passages (SPEC section 9.2).
     * Sans récolte, pas de rendement : le champ reste nul.
     */
    private static function yieldPerHectare(CropCycle $cycle, float $area): ?float
    {
        if ($area <= 0 || $cycle->harvests->isEmpty()) {
            return null;
        }

        $harvested = $cycle->harvests->sum(
            fn ($harvest): float => (float) $harvest->quantity_kg,
        );

        return round($harvested / $area, 2);
    }

    private static function asFloat(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
