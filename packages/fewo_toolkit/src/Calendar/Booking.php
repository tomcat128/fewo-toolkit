<?php

namespace FewoToolkit\Calendar;

use DateTimeImmutable;

/**
 * Ein belegter Zeitraum. $start = Anreisetag, $end = Abreisetag (exklusiv:
 * die letzte belegte Nacht ist die Nacht vor $end).
 */
final class Booking
{
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly bool $onRequest = false,
    ) {
    }
}
