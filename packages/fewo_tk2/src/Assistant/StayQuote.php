<?php

namespace FewoTk2\Assistant;

use DateTimeImmutable;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Pricing\PriceCalculator;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PricingException;
use FewoTk2\Pricing\Quote;
use FewoTk2\Support\Money;

/**
 * Preis eines Aufenthalts als kurzer Text für den Assistenten – berechnet mit dem Preisrechner des Pakets,
 * also mit denselben Regeln wie Fewo-Office: Saisonwechsel, Endreinigung und Anzahlung stimmen.
 * Das Sprachmodell gibt die Zahlen nur wieder und rechnet nicht selbst.
 */
final class StayQuote
{
    private const DATE = 'd.m.Y';

    /**
     * Erste Zeile für alle Wohnungen: für welchen Aufenthalt gerechnet wurde.
     */
    public static function heading(DateTimeImmutable $arrival, DateTimeImmutable $departure): string
    {
        $nights = (int) $arrival->diff($departure)->days;

        return t2(
            'Calculation by the booking system for the stay from %2$s to %3$s (%1$s night):',
            'Calculation by the booking system for the stay from %2$s to %3$s (%1$s nights):',
            $nights,
            $arrival->format(self::DATE),
            $departure->format(self::DATE)
        );
    }

    /**
     * @param int|null $guests null = unbekannt: Dann steht der Preis für jede Gästezahl da
     * @param Occupancy|null $occupancy null = Belegung unbekannt
     */
    public static function describe(string $apartment, Pricelist $pricelist, ?Occupancy $occupancy, DateTimeImmutable $arrival, DateTimeImmutable $departure, ?int $guests, DateTimeImmutable $today): string
    {
        $calculator = new PriceCalculator();
        // Zeilen je Gästezahl; aufeinanderfolgende Gästezahlen mit demselben Preis stehen zusammen ("1–2 Gäste")
        $groups = [];
        foreach ($guests !== null ? [$guests] : range(1, max(1, $pricelist->maxGuests)) as $count) {
            try {
                $text = self::quote($calculator->quote($pricelist, $arrival, $departure, $count, $today));
            } catch (PricingException $e) {
                $text = t('cannot be calculated: %s', $e->getMessage());
            }
            $last = count($groups) - 1;
            if ($last >= 0 && $groups[$last]['text'] === $text) {
                $groups[$last]['max'] = $count;
            } else {
                $groups[] = ['min' => $count, 'max' => $count, 'text' => $text];
            }
        }

        $lines = [$apartment . ':'];
        // Die Belegung zuerst: Ist der Zeitraum nicht frei, soll das in der Antwort nicht untergehen
        $booked = $occupancy !== null && !$occupancy->isFree($arrival, $departure);
        if ($occupancy !== null) {
            $lines[] = '- ' . ($booked
                ? t('Availability: the apartment is NOT free for the whole period – it is already booked.')
                : t('Availability: the apartment is still free in this period (not binding).'));
        }
        foreach ($groups as $group) {
            $label = $group['min'] === $group['max']
                ? t2('%s guest', '%s guests', $group['max'])
                : t('%1$s–%2$s guests', $group['min'], $group['max']);
            // belegt: steht an jedem Preis, damit er nicht wie ein buchbares Angebot klingt
            $lines[] = '- ' . $label . ': ' . $group['text'] . ($booked ? ' – ' . t('for information only, because the period is already booked') : '');
        }

        return implode("\n", $lines);
    }

    private static function quote(Quote $quote): string
    {
        $money = static fn (int $cents): string => Money::format($cents, $quote->currency);
        $parts = [];
        foreach ($quote->lines as $line) {
            $parts[] = t2('%1$s night × %2$s', '%1$s nights × %2$s', $line->nights, $money($line->pricePerNight))
                . ' (' . $line->season . ', ' . t('%1$s to %2$s', $line->from->format(self::DATE), $line->to->format(self::DATE)) . ')';
        }
        // Der Gesamtpreis steht vorn und die Zwischensumme der Nächte fehlt: Kleine Sprachmodelle nennen sonst
        // die erste Summe, die sie finden – und damit den Preis ohne Endreinigung.
        $text = $quote->cleaningFee > 0
            ? t('total price %1$s (including final cleaning) = %2$s + final cleaning %3$s', $money($quote->getTotal()), implode(' + ', $parts), $money($quote->cleaningFee))
            : t('total price %1$s = %2$s', $money($quote->getTotal()), implode(' + ', $parts));
        if ($quote->getDownPayment() > 0) {
            $text .= '; ' . t('down payment %s', $money($quote->getDownPayment()));
        }

        return $text;
    }
}
