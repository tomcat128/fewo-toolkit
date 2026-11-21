<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Pricing\PriceCalculator;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PriceTable;
use FewoTk2\Pricing\PricingException;
use FewoTk2\Pricing\Quote;
use FewoTk2\Pricing\Season;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2027-01-01');
    }

    /**
     * @param Season[] $extraSeasons
     * @param array<int, int> $defaultPrices
     */
    private function pricelist(int $maxGuests = 4, float $downPaymentPercent = 30.0, array $extraSeasons = [], array $defaultPrices = []): Pricelist
    {
        return new Pricelist('seeherz', 'Seeherz', $maxGuests, 3, 6000, $downPaymentPercent, [
            new Season('Nebensaison', new DateTimeImmutable('2027-01-01'), new DateTimeImmutable('2027-05-31'), new PriceTable([1 => 6000, 2 => 7000, 4 => 9000])),
            new Season('Hauptsaison', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-09-15'), new PriceTable([2 => 9500, 4 => 11000])),
            ...$extraSeasons,
        ], new PriceTable($defaultPrices));
    }

    private function quote(string $arrival, string $departure, int $guests, ?Pricelist $pricelist = null): Quote
    {
        return (new PriceCalculator())->quote($pricelist ?? $this->pricelist(), new DateTimeImmutable($arrival), new DateTimeImmutable($departure), $guests, $this->today);
    }

    public function testSimpleStay(): void
    {
        $quote = $this->quote('2027-03-01', '2027-03-04', 2);

        self::assertSame(3, $quote->getNights());
        self::assertSame(21000, $quote->getRent());
        self::assertSame(27000, $quote->getTotal());
        self::assertCount(1, $quote->lines);
        self::assertSame('Nebensaison', $quote->lines[0]->season);
    }

    public function testDownPaymentIsRoundedDownToFullEuros(): void
    {
        // 30 % von 270,00 € = 81,00 €
        self::assertSame(8100, $this->quote('2027-03-01', '2027-03-04', 2)->getDownPayment());
        // 33 % von 270,00 € = 89,10 € => 89,00 €
        self::assertSame(8900, $this->quote('2027-03-01', '2027-03-04', 2, $this->pricelist(downPaymentPercent: 33))->getDownPayment());
        // 12,5 % von 270,00 € = 33,75 € => 33,00 €
        self::assertSame(3300, $this->quote('2027-03-01', '2027-03-04', 2, $this->pricelist(downPaymentPercent: 12.5))->getDownPayment());
        self::assertSame(0, $this->quote('2027-03-01', '2027-03-04', 2, $this->pricelist(downPaymentPercent: 0))->getDownPayment());
    }

    public function testMissingGuestCountUsesNextHigherPrice(): void
    {
        // Für 3 Personen ist kein Preis hinterlegt => Preis für 4 Personen
        self::assertSame(27000, $this->quote('2027-03-01', '2027-03-04', 3)->getRent());
    }

    public function testGuestCountAboveHighestPriceUsesHighestPrice(): void
    {
        // Wie in Fewo-Office: gibt es keinen höheren Preis, gilt der der höchsten hinterlegten Personenzahl
        self::assertSame(27000, $this->quote('2027-03-01', '2027-03-04', 5, $this->pricelist(maxGuests: 6))->getRent());
    }

    public function testNightIsPricedByTheDayItEndsOn(): void
    {
        // Wie in Fewo-Office: die Nacht vom 31. Mai auf den 1. Juni kostet bereits den Preis der Hauptsaison
        $quote = $this->quote('2027-05-29', '2027-06-05', 2);

        self::assertCount(2, $quote->lines);
        self::assertSame(2, $quote->lines[0]->nights); // Nächte bis 30. und 31. Mai
        self::assertSame(5, $quote->lines[1]->nights);
        self::assertSame(2 * 7000 + 5 * 9500, $quote->getRent());
        self::assertSame('2027-05-31', $quote->lines[0]->to->format('Y-m-d'));
    }

    public function testMinimumStayApplies(): void
    {
        $this->expectException(PricingException::class);
        $this->expectExceptionMessage('minimum stay is 3 nights');
        $this->quote('2027-03-01', '2027-03-03', 2);
    }

    public function testRejectsTooManyGuests(): void
    {
        $this->expectExceptionMessage('maximum of 4 guests');
        $this->quote('2027-03-01', '2027-03-05', 5);
    }

    public function testRejectsPastArrival(): void
    {
        $this->expectExceptionMessage('in the past');
        $this->quote('2026-12-30', '2027-01-05', 2);
    }

    public function testRejectsDepartureBeforeArrival(): void
    {
        $this->expectExceptionMessage('must be after the arrival');
        $this->quote('2027-03-05', '2027-03-05', 2);
    }

    public function testRejectsPeriodWithoutSeasonAndWithoutDefaultPrice(): void
    {
        $this->expectExceptionMessage('no prices for 16.09.2027');
        $this->quote('2027-09-10', '2027-09-20', 2);
    }

    public function testDefaultPriceFillsGapsBetweenSeasons(): void
    {
        $quote = $this->quote('2027-09-10', '2027-09-20', 2, $this->pricelist(defaultPrices: [2 => 5000]));

        self::assertSame(['Hauptsaison', 'Other periods'], array_map(static fn ($line) => $line->season, $quote->lines));
        self::assertSame(5 * 9500 + 5 * 5000, $quote->getRent());
    }

    public function testOverlappingSeasonLaterStartWins(): void
    {
        $easter = new Season('Ostern', new DateTimeImmutable('2027-03-26'), new DateTimeImmutable('2027-04-05'), new PriceTable([4 => 12000]));
        $quote = $this->quote('2027-03-24', '2027-03-28', 2, $this->pricelist(extraSeasons: [$easter]));

        self::assertSame(['Nebensaison', 'Ostern'], array_map(static fn ($line) => $line->season, $quote->lines));
        self::assertSame(1 * 7000 + 3 * 12000, $quote->getRent());
    }

    public function testQuoteSerializesToArray(): void
    {
        $data = $this->quote('2027-03-01', '2027-03-04', 2)->toArray();

        self::assertSame(27000, $data['total']);
        self::assertSame('2027-03-01', $data['lines'][0]['from']);
        self::assertSame('EUR', $data['currency']);
    }

    public function testPriceRangesGroupEqualPrices(): void
    {
        self::assertSame(
            [['min' => 1, 'max' => 2, 'price' => 4400], ['min' => 3, 'max' => 3, 'price' => 5400]],
            (new PriceTable([1 => 4400, 2 => 4400, 3 => 5400]))->ranges()
        );
        self::assertSame(
            [['min' => 1, 'max' => 2, 'price' => 7500], ['min' => 3, 'max' => 4, 'price' => 9000]],
            (new PriceTable([4 => 9000, 2 => 7500, 3 => null, 0 => 100]))->ranges()
        );
    }
}
