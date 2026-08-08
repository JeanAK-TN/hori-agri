<?php

namespace App\Services\Rules;

use InvalidArgumentException;

/**
 * Raised when a condition tree cannot be evaluated: unknown field,
 * unknown operator, or malformed shape (SPEC section 9.5).
 *
 * It is never an end-user error: a condition reaching the evaluator has
 * already passed the checks of the seeder and of `rules:validate`.
 */
class InvalidConditionException extends InvalidArgumentException
{
    public static function unknownField(string $field): self
    {
        return new self(sprintf(
            'Le champ « %s » n\'est pas l\'un des quinze champs interrogeables du contexte.',
            $field,
        ));
    }

    public static function unknownOperator(string $operator): self
    {
        return new self(sprintf(
            'L\'opérateur « %s » n\'est pas reconnu.',
            $operator,
        ));
    }

    public static function malformed(string $detail): self
    {
        return new self('Arbre de conditions invalide : '.$detail);
    }
}
