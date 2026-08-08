<?php

namespace App\Enums;

enum OperationType: string implements HasLabel
{
    case Tillage = 'tillage';
    case Sowing = 'sowing';
    case Fertilization = 'fertilization';
    case Treatment = 'treatment';
    case Weeding = 'weeding';
    case Irrigation = 'irrigation';
    case Harvest = 'harvest';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Tillage => 'Labour',
            self::Sowing => 'Semis',
            self::Fertilization => 'Fertilisation',
            self::Treatment => 'Traitement',
            self::Weeding => 'Sarclage',
            self::Irrigation => 'Irrigation',
            self::Harvest => 'Récolte',
        };
    }

    /**
     * Whether the operation brings a product to the plot, in which case
     * operations.input_type is mandatory (SPEC section 6.3).
     *
     * Without that attribute, seven of the seventeen rules cannot be
     * evaluated: this is the most important design point of the model
     * and it must not be relaxed.
     */
    public function requiresInput(): bool
    {
        return match ($this) {
            self::Sowing, self::Fertilization, self::Treatment => true,
            self::Tillage, self::Weeding, self::Irrigation, self::Harvest => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, requires_input: bool}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'requires_input' => $case->requiresInput(),
            ],
            self::cases(),
        );
    }
}
