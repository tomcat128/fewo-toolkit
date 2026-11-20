<?php

namespace FewoToolkit\Tests\Unit;

use DateTimeImmutable;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Entity\Season;
use FewoToolkit\Pricing\CalculationMode;
use FewoToolkit\Pricing\PriceCalculator;
use FewoToolkit\Pricing\PricingException;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2027-01-01');
    }

    private function pricelist(): Pricelist
    {
        $pricelist = (new Pricelist())
            ->setName('Seeherz')
            ->setMaxGuests(4)
            ->setMinNights(3)
            ->setCleaningFee(6000)
            ->setSecurityDeposit(20000)
            ->setDownPaymentPercent(30);

        $pricelist->addSeason((new Season('Nebensaison', new DateTimeImmutable('2027-01-01'), new DateTimeImmutable('2027-05-31')))
            ->setPrices([1 => 6000, 2 => 7000, 4 => 9000]));
        $pricelist->addSeason((new Season('Hauptsaison', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-09-15')))
            ->setPrices([2 => 9500, 4 => 11000])
            ->setMinNights(7));

        return $pricelist;
    }

    private function quote(string $arrival, string $departure, int $guests, CalculationMode $mode = CalculationMode::ArrivalDay)
    {
        return (new PriceCalculator())->quote($this->pricelist(), new DateTimeImmutable($arrival), new DateTimeImmutable($departure), $guests, $mode, $this->today);
    }

    public function testSimpleStay(): void
    {
        $quote = $this->quote('2027-03-01', '2027-03-04', 2);

        self::assertSame(3, $quote->getNights());
        self::assertSame(21000, $quote->getRent());
        self::assertSame(27000, $quote->getTotal());
        self::assertCount(1, $quote->lines);
        self::assertSame('Nebensaison', $quote->lines[0]->season);
        self::assertSame(20000, $quote->securityDeposit);
    }

    public function testDownPaymentIsRoundedDownToFullEuros(): void
    {
        // 30 % von 270,00 € = 81,00 €
        self::assertSame(8100, $this->quote('2027-03-01', '2027-03-04', 2)->getDownPayment());

        // 33 % von 270,00 € = 89,10 € => 89,00 €
        $pricelist = $this->pricelist()->setDownPaymentPercent(33);
        $quote = (new PriceCalculator())->quote($pricelist, new DateTimeImmutable('2027-03-01'), new DateTimeImmutable('2027-03-04'), 2, CalculationMode::ArrivalDay, $this->today);
        self::assertSame(8900, $quote->getDownPayment());
    }

    public function testMissingGuestCountUsesNextHigherPrice(): void
    {
        // Für 3 Personen ist kein Preis hinterlegt => Preis für 4 Personen
        self::assertSame(27000, $this->quote('2027-03-01', '2027-03-04', 3)->getRent());
    }

    public function testStaySpanningTwoSeasonsArrivalDayMode(): void
    {
        $quote = $this->quote('2027-05-29', '2027-06-05', 2);

        self::assertCount(2, $quote->lines);
        self::assertSame(3, $quote->lines[0]->nights); // 29., 30., 31. Mai
        self::assertSame(4, $quote->lines[1]->nights); // 1.–4. Juni
        self::assertSame(3 * 7000 + 4 * 9500, $quote->getRent());
        self::assertSame('2027-06-01', $quote->lines[0]->to->format('Y-m-d'));
    }

    public function testFollowingDayModeShiftsSeasonBoundary(): void
    {
        $quote = $this->quote('2027-05-29', '2027-06-05', 2, CalculationMode::FollowingDay);

        self::assertSame(2, $quote->lines[0]->nights); // Nächte bis 30./31. Mai
        self::assertSame(5, $quote->lines[1]->nights);
    }

    public function testSeasonMinimumStayApplies(): void
    {
        $this->expectException(PricingException::class);
        $this->expectExceptionMessage('minimum stay is 7 nights');
        $this->quote('2027-07-01', '2027-07-05', 2);
    }

    public function testPricelistMinimumStayApplies(): void
    {
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

    public function testRejectsPeriodWithoutSeason(): void
    {
        $this->expectExceptionMessage('no prices for 16.09.2027');
        $this->quote('2027-09-10', '2027-09-20', 2);
    }

    public function testRejectsGuestCountWithoutPrice(): void
    {
        $pricelist = $this->pricelist();
        $pricelist->setMaxGuests(6);

        $this->expectExceptionMessage('no price for 5 guests');
        (new PriceCalculator())->quote($pricelist, new DateTimeImmutable('2027-03-01'), new DateTimeImmutable('2027-03-05'), 5, CalculationMode::ArrivalDay, $this->today);
    }

    public function testOverlappingSeasonLaterStartWins(): void
    {
        $pricelist = $this->pricelist();
        $pricelist->addSeason((new Season('Ostern', new DateTimeImmutable('2027-03-26'), new DateTimeImmutable('2027-04-05')))->setPrices([4 => 12000]));

        $quote = (new PriceCalculator())->quote($pricelist, new DateTimeImmutable('2027-03-24'), new DateTimeImmutable('2027-03-28'), 2, CalculationMode::ArrivalDay, $this->today);

        self::assertSame(['Nebensaison', 'Ostern'], array_map(static fn ($line) => $line->season, $quote->lines));
        self::assertSame(2 * 7000 + 2 * 12000, $quote->getRent());
    }

    public function testQuoteSerializesToArray(): void
    {
        $data = $this->quote('2027-03-01', '2027-03-04', 2)->toArray();

        self::assertSame(27000, $data['total']);
        self::assertSame('2027-03-01', $data['lines'][0]['from']);
        self::assertSame('EUR', $data['currency']);
    }
}
