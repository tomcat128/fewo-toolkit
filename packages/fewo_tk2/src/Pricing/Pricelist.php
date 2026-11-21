<?php

namespace FewoTk2\Pricing;

use DateTimeImmutable;

/**
 * Preisliste einer Ferienwohnung, wie sie Fewo-Office ausliefert.
 *
 * Wird nicht gespeichert und nicht auf der Website gepflegt: Die Preisliste
 * wird bei jeder Anzeige aus dem zuletzt abgerufenen Stand gelesen.
 * Alle Geldbeträge sind Integer in Cent.
 */
final class Pricelist
{
    /**
     * @var Season[] sortiert nach Beginn
     */
    private array $seasons;

    /**
     * @param Season[] $seasons
     * @param PriceTable $defaultPrices gilt für jeden Tag, den keine Saison abdeckt
     * @param float $downPaymentPercent Anzahlung in Prozent des Gesamtpreises
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly int $maxGuests,
        public readonly int $minNights,
        public readonly int $cleaningFee,
        public readonly float $downPaymentPercent,
        array $seasons,
        public readonly PriceTable $defaultPrices,
        public readonly string $currency = 'EUR',
    ) {
        usort($seasons, static fn (Season $a, Season $b) => $a->dateFrom <=> $b->dateFrom);
        $this->seasons = $seasons;
    }

    /**
     * @return Season[] sortiert nach Beginn
     */
    public function getSeasons(): array
    {
        return $this->seasons;
    }

    public function findSeason(DateTimeImmutable $day): ?Season
    {
        // Bei Überschneidungen gewinnt die zuletzt beginnende Saison
        // (z. B. "Ostern" innerhalb der "Vorsaison").
        $match = null;
        foreach ($this->seasons as $season) {
            if ($season->covers($day)) {
                $match = $season;
            }
        }

        return $match;
    }

    public function hasPrices(): bool
    {
        return $this->seasons !== [] || !$this->defaultPrices->isEmpty();
    }
}
