<?php

namespace App\Enums;

/**
 * La conduite du sol chez INSECTGO : le maraîchage se fait sur planches
 * surélevées et sur billons (SPEC-CORRECTION section 5.4). Aucune règle
 * active ne lit ce champ ; il décrit une pratique réelle et figure au
 * dictionnaire de données du mémoire.
 */
enum SoilPreparation: string implements HasLabel
{
    case Planches = 'planches';
    case Billons = 'billons';
    case Plat = 'plat';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Planches => 'Planches surélevées',
            self::Billons => 'Billons',
            self::Plat => 'Culture à plat',
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
