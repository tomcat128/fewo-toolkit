<?php

namespace FewoToolkit\Pricing;

use DateTimeImmutable;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Entity\Season;

/**
 * Berechnet den Mietpreis für einen Aufenthalt anhand einer Preisliste.
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
        CalculationMode $mode = CalculationMode::ArrivalDay,
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
        if ($guests > $pricelist->getMaxGuests()) {
            throw new PricingException(t('A maximum of %s guests can stay.', $pricelist->getMaxGuests()));
        }

        $seasons = $pricelist->getSeasons();
        $arrivalSeason = $this->findSeason($seasons, $mode === CalculationMode::FollowingDay ? $arrival->modify('+1 day') : $arrival);
        $minNights = max($pricelist->getMinNights(), $arrivalSeason?->getMinNights() ?? 0);
        if ($nights < $minNights) {
            throw new PricingException(t('The minimum stay is %s nights.', $minNights));
        }

        /** @var QuoteLine[] $lines */
        $lines = [];
        for ($night = $arrival; $night < $departure; $night = $night->modify('+1 day')) {
            $relevantDay = $mode === CalculationMode::FollowingDay ? $night->modify('+1 day') : $night;
            $season = $this->findSeason($seasons, $relevantDay);
            if ($season === null) {
                throw new PricingException(t('There are no prices for %s yet. Please contact us.', $this->formatDate($relevantDay)));
            }
            $price = $season->getPriceForGuests($guests);
            if ($price === null) {
                throw new PricingException(t('There is no price for %s guests in this period. Please contact us.', $guests));
            }

            $last = array_key_last($lines);
            if ($last !== null && $lines[$last]->season === $season->getName() && $lines[$last]->pricePerNight === $price) {
                $lines[$last] = $lines[$last]->withAdditionalNight();
            } else {
                $lines[] = new QuoteLine($season->getName(), $night, $night->modify('+1 day'), 1, $price);
            }
        }

        return new Quote(
            $arrival,
            $departure,
            $guests,
            $lines,
            $pricelist->getCleaningFee(),
            $pricelist->getSecurityDeposit(),
            $pricelist->getDownPaymentPercent(),
            $pricelist->getCurrency(),
            $pricelist->getIncludedServices(),
        );
    }

    /**
     * @param Season[] $seasons
     */
    private function findSeason(array $seasons, DateTimeImmutable $day): ?Season
    {
        // Bei Überschneidungen gewinnt die zuletzt beginnende Saison
        // (z. B. "Ostern" innerhalb der "Vorsaison").
        $match = null;
        foreach ($seasons as $season) {
            if ($season->covers($day) && ($match === null || $season->getDateFrom() >= $match->getDateFrom())) {
                $match = $season;
            }
        }

        return $match;
    }

    private function formatDate(DateTimeImmutable $day): string
    {
        return $day->format('d.m.Y');
    }
}
