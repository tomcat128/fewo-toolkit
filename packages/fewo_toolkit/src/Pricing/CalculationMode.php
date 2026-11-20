<?php

namespace FewoToolkit\Pricing;

/**
 * Legt fest, welcher Tag über den Saisonpreis einer Nacht entscheidet.
 */
enum CalculationMode: string
{
    /** Der Tag, an dem die Nacht beginnt (Anreisetag). */
    case ArrivalDay = 'arrival';

    /** Der Folgetag, an dem die Nacht endet. */
    case FollowingDay = 'following';

    public static function fromValue(?string $value): self
    {
        return match ($value) {
            // Werte aus dem alten Toolkit: T = Tag der Anreise, N = nächster Tag
            'following', 'N' => self::FollowingDay,
            default => self::ArrivalDay,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ArrivalDay => t('Season of the arrival day of each night'),
            self::FollowingDay => t('Season of the following day of each night'),
        };
    }
}
