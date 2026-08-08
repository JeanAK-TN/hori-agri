<?php

namespace App\Services\Rules;

use App\Enums\RuleFamily;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use Throwable;

/**
 * Checks the rules file before a single row is written (SPEC 10.4).
 *
 * Four controls: the schema of each entry, the fact that every condition
 * is accepted by the evaluator on a fake context, the counts of section
 * 10.3, and the fact that no working convention is left enabled.
 *
 * The same class serves the seeder and the `rules:validate` command, so
 * that the command really checks what the seeder will do.
 */
class RulesFileValidator
{
    /**
     * Expected counts (SPEC section 10.3).
     */
    public const EXPECTED_TOTAL = 17;

    public const EXPECTED_BY_FAMILY = ['A' => 7, 'B' => 5, 'C' => 2, 'D' => 3];

    public const EXPECTED_BY_CROP = ['Maïs' => 8, 'Soja' => 3, 'Manioc' => 3, 'toute culture' => 3];

    public const EXPECTED_BY_THRESHOLD = ['sourced' => 11, 'imported' => 1, 'convention' => 5];

    public const EXPECTED_BY_SEVERITY = ['high' => 7, 'medium' => 10];

    private const REQUIRED_KEYS = [
        'code',
        'crop',
        'family',
        'severity',
        'condition',
        'message',
        'action',
        'estimated_gain',
        'source',
        'threshold_status',
        'enabled',
    ];

    public function __construct(private readonly RuleEvaluator $evaluator) {}

    /**
     * Every problem found, as sentences a reader can act on. An empty
     * array means the file is usable.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    public function problems(array $entries): array
    {
        $problems = [];

        foreach ($entries as $index => $entry) {
            $problems = [...$problems, ...$this->problemsOfEntry($entry, $index + 1)];
        }

        return [...$problems, ...$this->problemsOfCounts($entries)];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    private function problemsOfEntry(array $entry, int $position): array
    {
        $problems = [];
        $code = is_string($entry['code'] ?? null) ? $entry['code'] : 'entrée '.$position;

        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $entry)) {
                $problems[] = sprintf('%s : la clé « %s » manque.', $code, $key);
            }
        }

        if ($problems !== []) {
            return $problems;
        }

        if (RuleFamily::tryFrom((string) $entry['family']) === null) {
            $problems[] = sprintf('%s : famille « %s » inconnue.', $code, $entry['family']);
        }

        if (Severity::tryFrom((string) $entry['severity']) === null) {
            $problems[] = sprintf('%s : sévérité « %s » inconnue.', $code, $entry['severity']);
        }

        $threshold = ThresholdStatus::tryFrom((string) $entry['threshold_status']);

        if ($threshold === null) {
            $problems[] = sprintf('%s : origine du seuil « %s » inconnue.', $code, $entry['threshold_status']);
        }

        if (! is_bool($entry['enabled'])) {
            $problems[] = sprintf('%s : « enabled » doit être vrai ou faux.', $code);
        }

        // Une convention de travail reste à confirmer par la coopérative :
        // elle ne s'évalue pas tant qu'elle est active (SPEC 10.4).
        if ($threshold?->isConvention() === true && $entry['enabled'] === true) {
            $problems[] = sprintf(
                '%s : un seuil de convention ne peut pas être actif tant que la coopérative ne l\'a pas confirmé.',
                $code,
            );
        }

        if (! is_array($entry['condition'])) {
            $problems[] = sprintf('%s : la condition doit être un arbre JSON.', $code);

            return $problems;
        }

        // La condition doit être acceptée par l'évaluateur, sur un
        // contexte factice : c'est le seul moyen de savoir qu'elle
        // tournera le jour venu (SPEC section 10.4).
        try {
            /** @var array<string, mixed> $condition */
            $condition = $entry['condition'];
            $this->evaluator->evaluate($condition, self::fakeContext());
        } catch (Throwable $exception) {
            $problems[] = sprintf('%s : %s', $code, $exception->getMessage());
        }

        return $problems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function problemsOfCounts(array $entries): array
    {
        $problems = [];

        if (count($entries) !== self::EXPECTED_TOTAL) {
            $problems[] = sprintf(
                'Le fichier porte %d règles au lieu des %d attendues.',
                count($entries),
                self::EXPECTED_TOTAL,
            );
        }

        $tallies = [
            'famille' => [self::EXPECTED_BY_FAMILY, $this->countBy($entries, 'family')],
            'culture' => [self::EXPECTED_BY_CROP, $this->countByCrop($entries)],
            'origine du seuil' => [self::EXPECTED_BY_THRESHOLD, $this->countBy($entries, 'threshold_status')],
            'sévérité' => [self::EXPECTED_BY_SEVERITY, $this->countBy($entries, 'severity')],
        ];

        foreach ($tallies as $dimension => [$expected, $actual]) {
            foreach ($expected as $key => $count) {
                $found = $actual[$key] ?? 0;

                if ($found !== $count) {
                    $problems[] = sprintf(
                        'Répartition par %s : %d règle(s) pour « %s » au lieu de %d.',
                        $dimension,
                        $found,
                        $key,
                        $count,
                    );
                }
            }
        }

        $codes = array_map(static fn (array $entry): string => (string) ($entry['code'] ?? ''), $entries);
        $duplicates = array_unique(array_diff_assoc($codes, array_unique($codes)));

        foreach ($duplicates as $duplicate) {
            $problems[] = sprintf('Le code « %s » apparaît plusieurs fois.', $duplicate);
        }

        return $problems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, int>
     */
    private function countBy(array $entries, string $key): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $value = (string) ($entry[$key] ?? '');
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, int>
     */
    private function countByCrop(array $entries): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $crop = $entry['crop'] ?? null;
            $key = $crop === null ? 'toute culture' : (string) $crop;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * A context whose fifteen fields are filled, so that a condition can
     * be exercised without any cycle and without any database.
     */
    public static function fakeContext(): EvaluationContext
    {
        return new EvaluationContext(
            crop_name: 'Maïs',
            region: 'Kara',
            sowing_month: 4,
            sowing_density_plants_ha: 50000,
            seed_rate_kg_ha: 25.0,
            npk_dose_kg_ha: 100.0,
            urea_dose_kg_ha: 50.0,
            potassium_dose_kg_ha: 30.0,
            inoculant_used: false,
            first_weeding_days_after_sowing: 20,
            urea_days_after_sowing: 40,
            consecutive_seasons_same_crop: 1,
            yield_vs_zone_median_ratio: 1.0,
            cost_vs_zone_median_ratio: 1.0,
            input_cost_share_of_harvest_value: 0.3,
        );
    }
}
