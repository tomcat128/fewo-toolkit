<?php

namespace FewoToolkit\Pricing;

use DateTimeImmutable;

/**
 * Aufeinanderfolgende Nächte mit gleicher Saison und gleichem Preis.
 */
final class QuoteLine
{
    public function __construct(
        public readonly string $season,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly int $nights,
        public readonly int $pricePerNight,
    ) {
    }

    public function getSubtotal(): int
    {
        return $this->nights * $this->pricePerNight;
    }

    public function withAdditionalNight(): self
    {
        return new self($this->season, $this->from, $this->to->modify('+1 day'), $this->nights + 1, $this->pricePerNight);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'season' => $this->season,
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'nights' => $this->nights,
            'pricePerNight' => $this->pricePerNight,
            'subtotal' => $this->getSubtotal(),
        ];
    }
}
