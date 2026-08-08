<?php

namespace App\Enums;

enum Gender: string implements HasLabel
{
    case Male = 'M';
    case Female = 'F';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Homme',
            self::Female => 'Femme',
        };
    }

    /**
     * The enumeration as it is sent to the interface.
     *
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
