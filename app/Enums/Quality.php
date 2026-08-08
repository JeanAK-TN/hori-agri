<?php

namespace App\Enums;

enum Quality: string implements HasLabel
{
    case Good = 'good';
    case Average = 'average';
    case Poor = 'poor';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Good => 'Bonne',
            self::Average => 'Moyenne',
            self::Poor => 'Mauvaise',
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
