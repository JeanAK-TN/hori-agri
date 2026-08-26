<?php

namespace App\Services\Rules;

/**
 * Écrit un arbre de conditions en français lisible, pour l'écran de la
 * base de règles (SPEC-CORRECTION section 6) : « ce que la règle
 * vérifie, en français lisible, et non le JSON brut ».
 *
 * La classe ne fait que présenter ce que le fichier de règles contient :
 * aucun seuil agronomique ne vit ici. Les libellés des champs viennent
 * de EvaluationContext::FIELD_LABELS, les mêmes qui complètent les
 * messages des recommandations.
 */
class ConditionPresenter
{
    private const OPERATOR_WORDS = [
        '=' => 'égal à',
        '!=' => 'différent de',
        '<' => 'inférieur à',
        '<=' => 'inférieur ou égal à',
        '>' => 'supérieur à',
        '>=' => 'supérieur ou égal à',
    ];

    /**
     * @param  array<string, mixed>  $condition
     */
    public function sentence(array $condition): string
    {
        if (array_key_exists('and', $condition) && is_array($condition['and'])) {
            return $this->joinBranch($condition['and'], ' et ');
        }

        if (array_key_exists('or', $condition) && is_array($condition['or'])) {
            return $this->joinBranch($condition['or'], ' ou ');
        }

        return $this->leaf($condition);
    }

    /**
     * @param  array<int, mixed>  $children
     */
    private function joinBranch(array $children, string $glue): string
    {
        $parts = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            /** @var array<string, mixed> $child */
            $isBranch = array_key_exists('and', $child) || array_key_exists('or', $child);

            $parts[] = $isBranch
                ? '('.$this->sentence($child).')'
                : $this->sentence($child);
        }

        return implode($glue, $parts);
    }

    /**
     * @param  array<string, mixed>  $leaf
     */
    private function leaf(array $leaf): string
    {
        $field = (string) ($leaf['field'] ?? '');
        $operator = (string) ($leaf['op'] ?? '');
        $value = $leaf['value'] ?? null;

        $labels = EvaluationContext::FIELD_LABELS[$field] ?? null;
        $label = $labels['libellé'] ?? $field;
        $unit = $labels['unité'] ?? '';

        $sentence = match ($operator) {
            'between' => is_array($value) && count($value) === 2
                ? sprintf('%s entre %s et %s', $label, $this->value($value[0], $unit), $this->value($value[1], $unit))
                : $label.' entre deux bornes',
            'in' => sprintf('%s parmi %s', $label, $this->valueList($value, $unit)),
            'not_in' => sprintf('%s hors de %s', $label, $this->valueList($value, $unit)),
            default => sprintf(
                '%s %s %s',
                $label,
                self::OPERATOR_WORDS[$operator] ?? $operator,
                $this->value($value, $unit),
            ),
        };

        return trim($sentence);
    }

    private function value(mixed $value, string $unit): string
    {
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        if (is_int($value) || is_float($value)) {
            $formatted = number_format((float) $value, 2, ',', ' ');
            $formatted = rtrim(rtrim($formatted, '0'), ',');

            return trim($formatted.' '.$unit);
        }

        if (is_string($value)) {
            return '« '.$value.' »';
        }

        return 'valeur vide';
    }

    private function valueList(mixed $values, string $unit): string
    {
        if (! is_array($values)) {
            return 'une liste de valeurs';
        }

        return implode(', ', array_map(
            fn (mixed $value): string => $this->value($value, $unit),
            $values,
        ));
    }
}
