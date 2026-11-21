<?php

namespace FewoTk2\Pricing;

use DateTimeImmutable;
use FewoTk2\Feed\FewoOfficeDocument;
use FewoTk2\Feed\InvalidFeedException;

/**
 * Liest die Preisliste aus Fewo-Office (JSON oder XML, am Inhalt erkannt).
 *
 * Fewo-Office liefert nur veröffentlichte Saisons, die noch nicht abgelaufen
 * sind. Weil der abgerufene Stand einige Stunden alt sein kann, werden
 * inzwischen abgelaufene Saisons hier noch einmal aussortiert.
 */
class PricelistReader
{
    /**
     * @throws InvalidFeedException
     */
    public function read(string $body, ?DateTimeImmutable $today = null): Pricelist
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $data = FewoOfficeDocument::decode($body);
        if (!is_array($data['pricelist'] ?? null) || !is_array($data['seasons'] ?? null)) {
            throw new InvalidFeedException(t('The data does not contain a price list.'));
        }
        $meta = $data['pricelist'];

        $seasons = [];
        foreach ($data['seasons'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $from = $this->date($entry['date_start'] ?? null);
            $to = $this->date($entry['date_end'] ?? null);
            $prices = $this->prices($entry['prices'] ?? null);
            // Saisons ohne Preise überspringen: für ihre Tage gilt dann wie in Fewo-Office der Standardpreis.
            if ($from === null || $to === null || $to < $from || $to < $today || $prices->isEmpty()) {
                continue;
            }
            $seasons[] = new Season(trim((string) ($entry['name'] ?? '')), $from, $to, $prices);
        }
        $defaultPrices = $this->prices($data['default_prices'] ?? null);

        $maxGuests = (int) ($meta['max_guests'] ?? 0);
        if ($maxGuests < 1) {
            $maxGuests = max(1, $defaultPrices->getMaxGuests(), ...array_map(static fn (Season $s) => $s->prices->getMaxGuests(), $seasons));
        }

        return new Pricelist(
            trim((string) ($meta['name'] ?? '')),
            trim((string) ($meta['title'] ?? '')),
            $maxGuests,
            max(1, (int) ($meta['min_stay'] ?? 1)),
            $this->cents($meta['cleaning'] ?? null) ?? 0,
            // Fewo-Office nennt die Anzahlung in Prozent wie das Altsystem "commission"
            min(100.0, max(0.0, (float) (is_numeric($meta['commission'] ?? null) ? $meta['commission'] : 0))),
            $seasons,
            $defaultPrices,
        );
    }

    private function prices(mixed $list): PriceTable
    {
        $prices = [];
        foreach (is_array($list) ? $list : [] as $price) {
            if (is_array($price) && is_numeric($price['persons'] ?? null)) {
                $prices[(int) $price['persons']] = $this->cents($price['value'] ?? null);
            }
        }

        return new PriceTable($prices);
    }

    /**
     * Beträge sind maschinenlesbar ("44.00", "44.5" oder "44"). Ohne Umweg über Fließkommazahlen.
     */
    private function cents(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }
        if (!preg_match('/^(\d+)(?:[.,](\d{1,2}))?$/', trim((string) $value), $match)) {
            return null;
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
