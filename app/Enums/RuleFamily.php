<?php

namespace App\Enums;

enum RuleFamily: string implements HasLabel
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::A => 'Écart au référentiel technique',
            self::B => 'Écart au calendrier cultural',
            self::C => 'Comparaison entre parcelles semblables',
            self::D => 'Économie et durabilité',
        };
    }

    /**
     * Family C compares a plot to the median of its zone: it is
     * suspended below five comparable cycles (SPEC section 9.6).
     */
    public function needsComparableZone(): bool
    {
        return $this === self::C;
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
