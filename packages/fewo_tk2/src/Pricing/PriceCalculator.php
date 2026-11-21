<?php

namespace FewoTk2\Pricing;

use DateTimeImmutable;

/**
 * Berechnet den Mietpreis für einen Aufenthalt anhand einer Preisliste.
 *
 * Rechnet wie Fewo-Office, damit die Website dieselben Preise nennt wie ein
 * Angebot von dort: Über den Preis einer Nacht entscheidet der Tag, an dem sie
 * endet (die Nacht vom 23. auf den 24. kostet den Preis des 24.). Deckt keine
 * Saison diesen Tag ab, gilt der Standardpreis der Wohnung.
 */
class PriceCalculator
{
    /**
     * Maximale Aufenthaltsdauer, die online berechnet wird.
     */
    public const MAX_NIGHTS = 365;

    /**
     * @throws PricingException wenn der Aufenthalt nicht berechnet werden kann
     */
    public function quote(
        Pricelist $pricelist,
        DateTimeImmutable $arrival,
        DateTimeImmutable $departure,
        int $guests,
        ?DateTimeImmutable $today = null,
    ): Quote {
        $arrival = $arrival->setTime(0, 0);
        $departure = $departure->setTime(0, 0);
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($arrival < $today) {
            throw new PricingException(t('The arrival date is in the past.'));
        }
        if ($departure <= $arrival) {
            throw new PricingException(t('The departure date must be after the arrival date.'));
        }
        $nights = (int) $arrival->diff($departure)->days;
        if ($nights > self::MAX_NIGHTS) {
            throw new PricingException(t('Please contact us for stays longer than %s nights.', self::MAX_NIGHTS));
        }
        if ($guests < 1) {
            throw new PricingException(t('Please specify the number of guests.'));
        }
        if ($guests > $pricelist->maxGuests) {
            throw new PricingException(t('A maximum of %s guests can stay.', $pricelist->maxGuests));
        }
        if ($nights < $pricelist->minNights) {
            throw new PricingException(t('The minimum stay is %s nights.', $pricelist->minNights));
        }

        $defaultPrice = $pricelist->defaultPrices->forGuests($guests);
        /** @var QuoteLine[] $lines */
        $lines = [];
        for ($night = $arrival; $night < $departure; $night = $night->modify('+1 day')) {
            $nightEnd = $night->modify('+1 day');
            $season = $pricelist->findSeason($nightEnd);
            $price = $season?->prices->forGuests($guests);
            $name = $season?->name ?? '';
            if ($price === null) {
                $price = $defaultPrice;
                $name = t('Other periods');
            }
            if ($price === null) {
                throw new PricingException(t('There are no prices for %s yet. Please contact us.', $nightEnd->format('d.m.Y')));
            }

            $last = array_key_last($lines);
            if ($last !== null && $lines[$last]->season === $name && $lines[$last]->pricePerNight === $price) {
                $lines[$last] = $lines[$last]->withAdditionalNight();
            } else {
                $lines[] = new QuoteLine($name, $night, $nightEnd, 1, $price);
            }
        }

        return new Quote(
            $arrival,
            $departure,
            $guests,
            $lines,
            $pricelist->cleaningFee,
            $pricelist->downPaymentPercent,
            $pricelist->currency,
        );
    }
}
