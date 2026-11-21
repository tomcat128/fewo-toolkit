<?php

namespace FewoTk2\Pricing;

use DateTimeImmutable;

/**
 * Ein Saisonzeitraum einer Preisliste. Der Zeitraum ist inklusive.
 */
final class Season
{
    public readonly DateTimeImmutable $dateFrom;
    public readonly DateTimeImmutable $dateTo;

    public function __construct(
        public readonly string $name,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        public readonly PriceTable $prices,
    ) {
        $this->dateFrom = $dateFrom->setTime(0, 0);
        $this->dateTo = $dateTo->setTime(0, 0);
    }

    public function covers(DateTimeImmutable $day): bool
    {
        $day = $day->setTime(0, 0);

        return $day >= $this->dateFrom && $day <= $this->dateTo;
    }
}
