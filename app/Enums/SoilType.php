<?php

namespace App\Enums;

enum SoilType: string implements HasLabel
{
    case Ferrugineux = 'ferrugineux';
    case Sableux = 'sableux';
    case Argileux = 'argileux';
    case Limoneux = 'limoneux';
    case Hydromorphe = 'hydromorphe';
    // Le sol du site d'INSECTGO, décrit dans la fiche de recueil
    // (SPEC-CORRECTION section 5.6).
    case SabloArgiloLimoneux = 'sablo_argilo_limoneux';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Ferrugineux => 'Ferrugineux',
            self::Sableux => 'Sableux',
            self::Argileux => 'Argileux',
            self::Limoneux => 'Limoneux',
            self::Hydromorphe => 'Hydromorphe',
            self::SabloArgiloLimoneux => 'Sablo-argilo-limoneux',
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
