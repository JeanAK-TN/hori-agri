<?php

namespace App\Enums;

enum InputType: string implements HasLabel
{
    case Npk = 'npk';
    case Urea = 'urea';
    case Potassium = 'potassium';
    case Phosphate = 'phosphate';
    case Organic = 'organic';
    case Seed = 'seed';
    case Inoculant = 'inoculant';
    case Herbicide = 'herbicide';
    case Insecticide = 'insecticide';
    case Fungicide = 'fungicide';
    case Other = 'other';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Npk => 'NPK',
            self::Urea => 'Urée',
            self::Potassium => 'Potassium',
            self::Phosphate => 'Phosphate',
            self::Organic => 'Fumure organique',
            self::Seed => 'Semence',
            self::Inoculant => 'Inoculant',
            self::Herbicide => 'Herbicide',
            self::Insecticide => 'Insecticide',
            self::Fungicide => 'Fongicide',
            self::Other => 'Autre',
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
