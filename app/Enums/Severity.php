<?php

namespace App\Enums;

enum Severity: string implements HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::High => 'Élevée',
            self::Medium => 'Modérée',
            self::Low => 'Faible',
        };
    }

    /**
     * Sorting weight: recommendations are ordered by decreasing severity
     * before anything else (SPEC section 9.7).
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
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
