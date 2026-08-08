<?php

namespace App\Enums;

enum SeasonType: string implements HasLabel
{
    case GrandeSaison = 'grande_saison';
    case PetiteSaison = 'petite_saison';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::GrandeSaison => 'Grande saison',
            self::PetiteSaison => 'Petite saison',
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
