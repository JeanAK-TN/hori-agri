<?php

namespace App\Enums;

enum CycleStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Harvested = 'harvested';
    case Abandoned = 'abandoned';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifié',
            self::InProgress => 'En cours',
            self::Harvested => 'Récolté',
            self::Abandoned => 'Abandonné',
        };
    }

    /**
     * A cycle that is neither harvested nor abandoned still accepts
     * operations and harvests.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
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
