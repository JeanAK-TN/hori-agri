<?php

namespace App\Enums;

enum ThresholdStatus: string implements HasLabel
{
    case Sourced = 'sourced';
    case Imported = 'imported';
    case Convention = 'convention';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Sourced => 'Source togolaise publiée',
            self::Imported => 'Source régionale',
            self::Convention => 'Convention à confirmer',
        };
    }

    /**
     * A working convention has to be confirmed by the cooperative before
     * it fires: such a rule is disabled by default (SPEC section 6.2).
     */
    public function isConvention(): bool
    {
        return $this === self::Convention;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
