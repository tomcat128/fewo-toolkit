<?php

namespace FewoTk2\Pricing;

/**
 * Preise pro Nacht in Cent je Gästeanzahl, z. B. {1: 4400, 2: 4400, 3: 5400}.
 */
final class PriceTable
{
    /**
     * @var array<int, int> Gästeanzahl => Cent, aufsteigend sortiert
     */
    private array $prices = [];

    /**
     * @param array<int|string, int|null> $prices Gästeanzahl => Cent; leere Werte werden entfernt
     */
    public function __construct(array $prices = [])
    {
        foreach ($prices as $guests => $cents) {
            if ($cents !== null && (int) $guests >= 1 && (int) $cents > 0) {
                $this->prices[(int) $guests] = (int) $cents;
            }
        }
        ksort($this->prices);
    }

    public function isEmpty(): bool
    {
        return $this->prices === [];
    }

    /**
     * @return array<int, int>
     */
    public function all(): array
    {
        return $this->prices;
    }

    /**
     * Größte Gästeanzahl, für die ein Preis hinterlegt ist.
     */
    public function getMaxGuests(): int
    {
        return $this->prices === [] ? 0 : (int) array_key_last($this->prices);
    }

    /**
     * Preis pro Nacht für die angegebene Gästeanzahl – dieselbe Regel wie in Fewo-Office:
     * exakter Treffer, sonst die nächsthöhere hinterlegte Gästeanzahl (nie zu günstig anbieten),
     * sonst die höchste hinterlegte.
     */
    /**
     * Mindestbelegung: bis zu dieser Gästezahl gilt der niedrigste Preis – weniger Gäste zahlen denselben Preis
     * (z. B. 2, wenn eine und zwei Personen gleich viel kosten oder erst ab zwei Personen ein Preis hinterlegt ist).
     * Fewo-Office kennt dafür kein eigenes Feld; der Wert ergibt sich aus den Preisen.
     *
     * @return int 0 = keine Preise
     */
    public function getMinOccupancy(): int
    {
        return $this->ranges()[0]['max'] ?? 0;
    }

    public function forGuests(int $guests): ?int
    {
        foreach ($this->prices as $count => $cents) {
            if ($count >= $guests) {
                return $cents;
            }
        }

        return $this->prices === [] ? null : end($this->prices);
    }

    /**
     * Fasst Gästeanzahlen mit gleichem Preis zu Bereichen zusammen.
     *
     * Berücksichtigt die Regel aus forGuests(): Bei {2: 75 €, 4: 90 €}
     * gilt 75 € für 1–2 Gäste und 90 € für 3–4 Gäste.
     *
     * @return array<int, array{min: int, max: int, price: int}>
     */
    public function ranges(): array
    {
        $ranges = [];
        $previous = 0;
        foreach ($this->prices as $guests => $cents) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last]['price'] === $cents) {
                $ranges[$last]['max'] = $guests;
            } else {
                $ranges[] = ['min' => $previous + 1, 'max' => $guests, 'price' => $cents];
            }
            $previous = $guests;
        }

        return $ranges;
    }
}
