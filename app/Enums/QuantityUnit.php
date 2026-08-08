<?php

namespace App\Enums;

enum QuantityUnit: string implements HasLabel
{
    case Kilogram = 'kg';
    case Litre = 'l';
    case Bag50Kg = 'sac_50kg';
    case Bag25Kg = 'sac_25kg';
    case Unit = 'unite';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogramme',
            self::Litre => 'Litre',
            self::Bag50Kg => 'Sac de 50 kg',
            self::Bag25Kg => 'Sac de 25 kg',
            self::Unit => 'Unité',
        };
    }

    /**
     * Conversion factor towards kilograms (SPEC section 8).
     *
     * Litres and units return null: they do not convert to a mass, so a
     * quantity expressed in them never enters a dose in kilograms per
     * hectare (SPEC section 9.4).
     */
    public function toKilograms(): ?float
    {
        return match ($this) {
            self::Kilogram => 1.0,
            self::Bag50Kg => 50.0,
            self::Bag25Kg => 25.0,
            self::Litre, self::Unit => null,
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
