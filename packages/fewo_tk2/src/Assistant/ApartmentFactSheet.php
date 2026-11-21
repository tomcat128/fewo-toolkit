<?php

namespace FewoTk2\Assistant;

use DateTimeImmutable;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PriceTable;
use FewoTk2\Support\Money;

/**
 * Preise und freie Zeiträume einer Wohnung als kurzer Text für den Assistenten.
 *
 * Die Zahlen stammen aus Fewo-Office (dieselben Daten wie in Preisübersicht und Belegungsplan);
 * der Assistent bekommt sie ausformuliert, damit er nichts selbst ableiten muss.
 */
final class ApartmentFactSheet
{
    /** So weit in die Zukunft reicht die Belegung, die der Assistent kennt. */
    public const MONTHS_AHEAD = 12;

    /**
     * Für diese Aufenthaltslängen in Nächten steht der nächste freie Zeitraum ausdrücklich dabei. Nur die Woche:
     * Mit einer zweiten, fast gleichlautenden Zeile für zwei Wochen griff das kleine Testmodell öfter daneben.
     */
    private const STAY_LENGTHS = [7];

    /** Für so viele Monate ab heute kennt der Assistent die Preise. */
    public const PRICE_MONTHS_AHEAD = 15;

    private const DATE = 'd.m.Y';

    /**
     * Preise pro Nacht, Monat für Monat: Gefragt wird meist nach einem Monat ("Was kostet es im Juli?").
     * Wechselt die Saison mitten im Monat, stehen beide Abschnitte mit ihren Tagen in derselben Zeile –
     * ein kleines Sprachmodell muss die Zeiträume so nicht selbst zuordnen.
     */
    public static function prices(Pricelist $pricelist, DateTimeImmutable $today, ?string $locale = null): string
    {
        $today = $today->setTime(0, 0);
        $lines = [];
        $month = $today;
        for ($i = 0; $i < self::PRICE_MONTHS_AHEAD; $i++) {
            $line = self::month($pricelist, $month, $locale);
            if ($line !== '') {
                $lines[] = '- ' . $line;
            }
            $month = $month->modify('first day of next month');
        }
        if ($lines === []) {
            return '';
        }

        array_unshift($lines, t('Prices per night by month:'));
        if ($pricelist->cleaningFee > 0) {
            $lines[] = t('Final cleaning: %s once per stay, in addition to the price per night.', Money::format($pricelist->cleaningFee, $pricelist->currency));
        }
        if ($pricelist->minNights > 1) {
            $lines[] = t('Minimum stay: %d nights.', $pricelist->minNights);
        }
        if ($pricelist->maxGuests > 0) {
            $lines[] = t('Maximum number of guests: %d.', $pricelist->maxGuests);
        }
        if ($pricelist->downPaymentPercent > 0) {
            $lines[] = t('Down payment: %s %% of the total price.', Money::percent($pricelist->downPaymentPercent));
        }

        return implode("\n", $lines);
    }

    /**
     * Preise pro Nacht eines Monats in einer Zeile, z. B. "Dezember 2026: 01.12.2026 bis 18.12.2026 (Wintersaison):
     * 1–2 Gäste 45 €, 3 Gäste 55 €; 19.12.2026 bis 31.12.2026 (Weihnachten): …"; leer, wenn es keine Preise gibt.
     *
     * @param DateTimeImmutable $from erster Tag, ab dem der Monat betrachtet wird (z. B. heute)
     */
    public static function month(Pricelist $pricelist, DateTimeImmutable $from, ?string $locale = null): string
    {
        $parts = self::monthPeriods($pricelist, $from);

        return $parts === [] ? '' : self::monthName($from, $locale) . ': ' . implode('; ', $parts);
    }

    /**
     * Die Preise eines Monats als eigene Zeile je Zeitraum – für die Antwort auf "Was kostet eine Nacht im
     * Dezember?": Mit allen Zeiträumen in einer Zeile nannte das kleine Testmodell nur den ersten.
     *
     * @return string[] erste Zeile: Monat und Zahl der Zeiträume; leer, wenn es keine Preise gibt
     */
    public static function monthLines(Pricelist $pricelist, DateTimeImmutable $from, ?string $locale = null): array
    {
        $parts = self::monthPeriods($pricelist, $from);
        if ($parts === []) {
            return [];
        }
        $name = self::monthName($from, $locale);

        return array_merge(
            [count($parts) > 1 ? t('%1$s – %2$d periods with different prices:', $name, count($parts)) : $name . ':'],
            array_map(static fn (string $part): string => '- ' . $part, $parts)
        );
    }

    /**
     * @return string[] je Zeitraum mit gleicher Saison: "01.12.2026 bis 18.12.2026 (Wintersaison): 1–2 Gäste 45 €, …"
     */
    private static function monthPeriods(Pricelist $pricelist, DateTimeImmutable $from): array
    {
        $from = $from->setTime(0, 0);
        $last = $from->modify('last day of this month');
        // Aufeinanderfolgende Tage mit derselben Saison zusammenfassen
        $segments = [];
        for ($day = $from; $day <= $last; $day = $day->modify('+1 day')) {
            $season = $pricelist->findSeason($day);
            $prices = $season !== null && !$season->prices->isEmpty() ? $season->prices : $pricelist->defaultPrices;
            $name = $season !== null && !$season->prices->isEmpty() ? $season->name : '';
            $key = $name . '|' . json_encode($prices->all());
            $index = count($segments) - 1;
            if ($index >= 0 && $segments[$index]['key'] === $key) {
                $segments[$index]['to'] = $day;
            } else {
                $segments[] = ['key' => $key, 'from' => $day, 'to' => $day, 'name' => $name, 'prices' => $prices];
            }
        }
        $parts = [];
        foreach ($segments as $segment) {
            if ($segment['prices']->isEmpty()) {
                continue;
            }
            $parts[] = t('%1$s to %2$s', $segment['from']->format(self::DATE), $segment['to']->format(self::DATE))
                . ($segment['name'] !== '' ? ' (' . $segment['name'] . ')' : '')
                . ': ' . self::table($segment['prices'], $pricelist->currency);
        }

        return $parts;
    }

    /**
     * Freie Zeiträume der nächsten zwölf Monate. Lücken unter dem Mindestaufenthalt fehlen – sie lassen
     * sich nicht buchen. Die Antwort auf die häufigste Frage ("Wann ist frei?", "… für eine Woche?")
     * steht ausformuliert dabei: Kleine Sprachmodelle vergleichen Zeiträume sonst unzuverlässig.
     */
    public static function availability(Occupancy $occupancy, DateTimeImmutable $today, int $minNights = 1): string
    {
        $today = $today->setTime(0, 0);
        $until = $today->modify('+' . self::MONTHS_AHEAD . ' months');
        $ranges = [];
        $start = null;
        for ($night = $today; $night <= $until; $night = $night->modify('+1 day')) {
            $free = $night < $until && $occupancy->night($night) === Occupancy::FREE;
            if ($free && $start === null) {
                $start = $night;
            } elseif (!$free && $start !== null) {
                $nights = (int) $start->diff($night)->days;
                if ($nights >= max(1, $minNights)) {
                    $ranges[] = [$nights, t2('%2$s to %3$s (%1$d night)', '%2$s to %3$s (%1$d nights)', $nights, $start->format(self::DATE), $night->format(self::DATE))];
                }
                $start = null;
            }
        }

        $period = t('%1$s to %2$s', $today->format(self::DATE), $until->format(self::DATE));
        if ($ranges === []) {
            return t('Availability %s: fully booked, no free nights.', $period);
        }

        $lines = [t('Free periods %s (first date = earliest arrival, second date = latest departure; every other night in this time is booked):', $period)];
        foreach ($ranges as [, $text]) {
            $lines[] = '- ' . $text;
        }
        $lines[] = t('Next free period: %s.', $ranges[0][1]);
        foreach (self::STAY_LENGTHS as $length) {
            foreach ($ranges as [$nights, $text]) {
                if ($nights >= $length) {
                    $lines[] = t2('Next free period for a stay of %1$d week (%2$d nights or more): %3$s.', 'Next free period for a stay of %1$d weeks (%2$d nights or more): %3$s.', intdiv($length, 7), $length, $text);
                    break;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Monat und Jahr in der Sprache der Website, z. B. "Dezember 2026" (ohne Concrete: englisch).
     */
    private static function monthName(DateTimeImmutable $month, ?string $locale): string
    {
        if (class_exists(\Punic\Calendar::class)) {
            $locale ??= class_exists(\Concrete\Core\Localization\Localization::class) ? \Concrete\Core\Localization\Localization::activeLocale() : 'en_US';

            return \Punic\Calendar::getMonthName($month, 'wide', $locale, true) . ' ' . $month->format('Y');
        }

        return $month->format('F Y');
    }

    private static function table(PriceTable $prices, string $currency): string
    {
        $parts = [];
        foreach ($prices->ranges() as $range) {
            $guests = $range['min'] === $range['max']
                ? t2('%s guest', '%s guests', $range['max'])
                : t('%1$s–%2$s guests', $range['min'], $range['max']);
            $parts[] = $guests . ' ' . Money::format($range['price'], $currency);
        }

        return implode(', ', $parts);
    }
}
