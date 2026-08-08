<?php

namespace App\Services\Rules;

/**
 * Evaluates a tree of conditions against a context (SPEC section 9.5).
 *
 * The evaluator knows neither agronomy nor cooperatives. It makes no
 * database call. The grammar is deliberately poor so that it stays
 * readable by the technical lead of a cooperative: three shapes, nine
 * operators, no function, no computation, no external call.
 */
class RuleEvaluator
{
    private const OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'between', 'in', 'not_in'];

    /**
     * Operators that answer true when the field is null. Everywhere else
     * a null field makes the condition false (SPEC section 9.4).
     */
    private const TRUE_ON_NULL = ['!=', 'not_in'];

    /**
     * @param  array<string, mixed>  $condition
     */
    public function evaluate(array $condition, EvaluationContext $context): bool
    {
        if (array_key_exists('and', $condition)) {
            return $this->evaluateBranch($condition, 'and', $context);
        }

        if (array_key_exists('or', $condition)) {
            return $this->evaluateBranch($condition, 'or', $context);
        }

        return $this->evaluateLeaf($condition, $context);
    }

    /**
     * The fields a condition reads, in the order they appear. The
     * generated message quotes their observed values (SPEC section 9.7).
     *
     * @param  array<string, mixed>  $condition
     * @return array<int, string>
     */
    public function leafFields(array $condition): array
    {
        foreach (['and', 'or'] as $branch) {
            if (array_key_exists($branch, $condition)) {
                $this->assertBranchIsWellFormed($condition, $branch);

                $fields = [];

                /** @var array<int, array<string, mixed>> $children */
                $children = $condition[$branch];

                foreach ($children as $child) {
                    $fields = [...$fields, ...$this->leafFields($child)];
                }

                return array_values(array_unique($fields));
            }
        }

        $this->assertLeafIsWellFormed($condition);

        return [(string) $condition['field']];
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function evaluateBranch(array $condition, string $branch, EvaluationContext $context): bool
    {
        $this->assertBranchIsWellFormed($condition, $branch);

        /** @var array<int, array<string, mixed>> $children */
        $children = $condition[$branch];

        foreach ($children as $child) {
            $result = $this->evaluate($child, $context);

            if ($branch === 'and' && ! $result) {
                return false;
            }

            if ($branch === 'or' && $result) {
                return true;
            }
        }

        return $branch === 'and';
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function evaluateLeaf(array $condition, EvaluationContext $context): bool
    {
        $this->assertLeafIsWellFormed($condition);

        $operator = (string) $condition['op'];

        if (! in_array($operator, self::OPERATORS, true)) {
            throw InvalidConditionException::unknownOperator($operator);
        }

        $actual = $context->get((string) $condition['field']);
        $expected = $condition['value'];

        if ($actual === null) {
            return in_array($operator, self::TRUE_ON_NULL, true);
        }

        return match ($operator) {
            '=' => $this->looseEquals($actual, $expected),
            '!=' => ! $this->looseEquals($actual, $expected),
            '<' => $this->compare($actual) < $this->compare($expected),
            '<=' => $this->compare($actual) <= $this->compare($expected),
            '>' => $this->compare($actual) > $this->compare($expected),
            '>=' => $this->compare($actual) >= $this->compare($expected),
            'between' => $this->between($actual, $expected),
            'in' => $this->isIn($actual, $expected),
            'not_in' => ! $this->isIn($actual, $expected),
        };
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function assertLeafIsWellFormed(array $condition): void
    {
        foreach (['field', 'op', 'value'] as $key) {
            if (! array_key_exists($key, $condition)) {
                throw InvalidConditionException::malformed(
                    sprintf('une comparaison attend les clés field, op et value (« %s » manque).', $key),
                );
            }
        }

        $unexpected = array_diff(array_keys($condition), ['field', 'op', 'value']);

        if ($unexpected !== []) {
            throw InvalidConditionException::malformed(
                sprintf('clés inattendues dans une comparaison : %s.', implode(', ', $unexpected)),
            );
        }

        if (! is_string($condition['field']) || ! is_string($condition['op'])) {
            throw InvalidConditionException::malformed('field et op doivent être des chaînes.');
        }
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function assertBranchIsWellFormed(array $condition, string $branch): void
    {
        if (count($condition) !== 1) {
            throw InvalidConditionException::malformed(
                sprintf('une branche « %s » ne porte aucune autre clé.', $branch),
            );
        }

        $children = $condition[$branch];

        if (! is_array($children) || $children === [] || ! array_is_list($children)) {
            throw InvalidConditionException::malformed(
                sprintf('une branche « %s » attend une liste non vide de conditions.', $branch),
            );
        }

        foreach ($children as $child) {
            if (! is_array($child)) {
                throw InvalidConditionException::malformed(
                    sprintf('une branche « %s » ne contient que des conditions.', $branch),
                );
            }
        }
    }

    /**
     * Compares without the surprises of PHP's loose equality: a boolean
     * only equals a boolean, a number only a number.
     */
    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            return $actual === $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    private function compare(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw InvalidConditionException::malformed(
                'une comparaison d\'ordre attend des nombres.',
            );
        }

        return (float) $value;
    }

    /**
     * Bounds included (SPEC section 9.5).
     */
    private function between(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected) || count($expected) !== 2 || ! array_is_list($expected)) {
            throw InvalidConditionException::malformed(
                'l\'opérateur between attend un tableau de deux nombres.',
            );
        }

        $value = $this->compare($actual);

        return $value >= $this->compare($expected[0]) && $value <= $this->compare($expected[1]);
    }

    private function isIn(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected) || ! array_is_list($expected)) {
            throw InvalidConditionException::malformed(
                'les opérateurs in et not_in attendent un tableau de valeurs.',
            );
        }

        foreach ($expected as $candidate) {
            if ($this->looseEquals($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
