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
     * Expected counts : cinq règles sur la tomate, toutes issues de la
     * fiche togolaise publiée (SPEC-CORRECTION section 7). La famille D,
     * décrite dans le mémoire, n'a plus aucune règle : elle reste dans
     * l'énumération mais pas dans les comptages.
     */
    public const EXPECTED_TOTAL = 5;

    public const EXPECTED_BY_FAMILY = ['A' => 3, 'B' => 1, 'C' => 1];

    public const EXPECTED_BY_CROP = ['Tomate' => 5];

    public const EXPECTED_BY_THRESHOLD = ['sourced' => 5];

    public const EXPECTED_BY_SEVERITY = ['high' => 2, 'medium' => 3];

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
        'threshold_note',
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

        return [
            ...$problems,
            ...$this->problemsOfCounts($entries),
            ...$this->problemsOfSources($entries),
        ];
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

        // La note précise l'origine du seuil (« formule à confirmer »,
        // « tolérance de conception ») ; nulle quand le seuil est repris
        // tel quel de la fiche (SPEC-CORRECTION section 3.3).
        $note = $entry['threshold_note'];

        if ($note !== null && (! is_string($note) || mb_strlen($note) > 60)) {
            $problems[] = sprintf(
                '%s : « threshold_note » doit être nulle ou une chaîne de 60 caractères au plus.',
                $code,
            );
        }

        // Une convention de travail reste à confirmer par l'exploitation :
        // elle ne s'évalue pas tant qu'elle est active (SPEC 10.4).
        if ($threshold?->isConvention() === true && $entry['enabled'] === true) {
            $problems[] = sprintf(
                '%s : un seuil de convention ne peut pas être actif tant que l\'exploitation ne l\'a pas confirmé.',
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

            return $problems;
        }

        return [...$problems, ...$this->problemsOfFields($entry, $code)];
    }

    /**
     * Chaque champ interrogé appartient aux sept du contexte
     * (SPEC-CORRECTION section 7, contrôle 3).
     *
     * L'essai précédent ne suffit pas : l'évaluateur court-circuite une
     * branche « and » dès le premier enfant faux, et un champ inconnu
     * placé plus loin ne serait jamais lu. leafFields parcourt l'arbre
     * entier, sans évaluer.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    private function problemsOfFields(array $entry, string $code): array
    {
        /** @var array<string, mixed> $condition */
        $condition = $entry['condition'];

        try {
            $fields = $this->evaluator->leafFields($condition);
        } catch (Throwable $exception) {
            return [sprintf('%s : %s', $code, $exception->getMessage())];
        }

        $unknown = array_diff($fields, array_keys(EvaluationContext::FIELD_LABELS));

        if ($unknown === []) {
            return [];
        }

        return [sprintf(
            '%s : champ(s) inconnu(s) du contexte d\'évaluation : %s.',
            $code,
            implode(', ', $unknown),
        )];
    }

    /**
     * Deux contrôles que le mémoire rend nécessaires : les cinq règles
     * viennent d'un seul document publié, et plus aucun seuil n'est
     * simulé (SPEC-CORRECTION section 7, contrôles 1 et 2).
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function problemsOfSources(array $entries): array
    {
        $problems = [];

        $sources = array_unique(array_map(
            static fn (array $entry): string => (string) ($entry['source'] ?? ''),
            $entries,
        ));

        if (count($sources) > 1) {
            $problems[] = sprintf(
                'Les règles doivent porter une seule et même source ; %d apparaissent : %s.',
                count($sources),
                implode(' | ', $sources),
            );
        }

        // Un seuil simulé ne se signale plus par un avertissement : il
        // fait échouer le contrôle.
        foreach ($entries as $entry) {
            if (! str_contains((string) ($entry['_source'] ?? ''), 'SIMULÉ')) {
                continue;
            }

            $problems[] = sprintf(
                '%s : seuil encore marqué SIMULÉ, à remplacer par la référence publiée.',
                (string) ($entry['code'] ?? 'entrée sans code'),
            );
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
     * A context whose seven fields are filled, so that a condition can
     * be exercised without any cycle and without any database. Les
     * valeurs sont des fixtures d'exercice, pas des références.
     */
    public static function fakeContext(): EvaluationContext
    {
        return new EvaluationContext(
            crop_name: 'Tomate',
            planting_density_plants_ha: 30000,
            topdressing_npk_kg_ha: 300.0,
            organic_matter_t_ha: 1.5,
            first_topdressing_days_after_transplanting: 15,
            yield_kg_ha: 10000.0,
            zone_median_yield_kg_ha: 11000.0,
        );
    }
}
