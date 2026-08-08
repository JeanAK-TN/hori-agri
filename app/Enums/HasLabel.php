<?php

namespace App\Enums;

/**
 * Every enumeration of the application exposes the French label shown on
 * screen (SPEC section 8 and annexe A). Stating it as a contract makes
 * the rule checkable rather than merely conventional.
 */
interface HasLabel
{
    public function label(): string;
}
