<?php

namespace App\Enums;

enum RecoStatus: string implements HasLabel
{
    case New = 'new';
    case Read = 'read';
    case Applied = 'applied';
    case Dismissed = 'dismissed';

    /**
     * French label displayed on screen (SPEC annexe A).
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::Read => 'Lue',
            self::Applied => 'Appliquée',
            self::Dismissed => 'Écartée',
        };
    }

    /**
     * A recommendation the farmer has answered is kept even when the
     * rule no longer fires: it is a trace of what was decided
     * (SPEC section 9.7).
     */
    public function isAnswered(): bool
    {
        return in_array($this, [self::Applied, self::Dismissed], true);
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
