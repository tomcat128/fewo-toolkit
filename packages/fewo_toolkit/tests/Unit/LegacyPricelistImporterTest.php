<?php

namespace FewoToolkit\Tests\Unit;

use FewoToolkit\Import\ImportException;
use FewoToolkit\Import\LegacyPricelistImporter;
use FewoToolkit\Support\Money;
use PHPUnit\Framework\TestCase;

final class LegacyPricelistImporterTest extends TestCase
{
    public function testImportsLegacyExport(): void
    {
        // So sieht der Export des alten Toolkits aus (inkl. unmaskiertem "&").
        $xml = '<?xml version="1.0" encoding="UTF-8"?><fewobacher data="pricelist">'
            . '<status><type>OK</type><message>Okay!</message></status>'
            . '<pricelist><name>Seeherz</name><cleaning>60.00</cleaning><min_stay>3</min_stay><max_guests>4</max_guests><commission>30</commission></pricelist>'
            . '<seasons>'
            . '<season><name>Ostern & Pfingsten</name><date_start>2027-03-26</date_start><date_end>2027-04-05</date_end>'
            . '<prices><persons>1</persons><value>70</value></prices><prices><persons>2</persons><value>70</value></prices>'
            . '<prices><persons>3</persons><value>85,5</value></prices><prices><persons>4</persons><value></value></prices></season>'
            . '<season><name>Kaputt</name><date_start>irgendwann</date_start><date_end>2027-04-05</date_end></season>'
            . '</seasons></fewobacher>';

        $pricelist = (new LegacyPricelistImporter())->import($xml);

        self::assertSame('Seeherz', $pricelist->getName());
        self::assertSame(6000, $pricelist->getCleaningFee());
        self::assertSame(3, $pricelist->getMinNights());
        self::assertSame(4, $pricelist->getMaxGuests());
        self::assertSame(30, $pricelist->getDownPaymentPercent());

        $seasons = $pricelist->getSeasons();
        self::assertCount(1, $seasons);
        self::assertSame('Ostern & Pfingsten', $seasons[0]->getName());
        self::assertSame([1 => 7000, 2 => 7000, 3 => 8550], $seasons[0]->getPrices());
        self::assertSame([['min' => 1, 'max' => 2, 'price' => 7000], ['min' => 3, 'max' => 3, 'price' => 8550]], $seasons[0]->getPriceRanges());

        // Lücken: der Preis gilt auch für die kleineren Gästeanzahlen davor
        $season = (new \FewoToolkit\Entity\Season('Test'))->setPrices([2 => 7500, 4 => 9000, 5 => 9000]);
        self::assertSame([['min' => 1, 'max' => 2, 'price' => 7500], ['min' => 3, 'max' => 5, 'price' => 9000]], $season->getPriceRanges());
    }

    public function testRejectsErrorStatus(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Wrong Password');
        (new LegacyPricelistImporter())->import('<fewobacher data="pricelist"><status><type>ERROR</type><message>Wrong Password</message></status></fewobacher>');
    }

    public function testRejectsGarbage(): void
    {
        $this->expectException(ImportException::class);
        (new LegacyPricelistImporter())->import('<html><body>Not found');
    }

    public function testMoneyParsing(): void
    {
        self::assertSame(123450, Money::parse('1.234,50'));
        self::assertSame(123450, Money::parse('1,234.50'));
        self::assertSame(8550, Money::parse('85,5'));
        self::assertSame(8550, Money::parse('85.50'));
        self::assertSame(123400, Money::parse('1.234'));
        self::assertSame(8500, Money::parse('85 €'));
        self::assertNull(Money::parse(''));
        self::assertNull(Money::parse('abc'));
        self::assertSame('85', Money::toInput(8500));
        self::assertSame('85,50', Money::toInput(8550));
    }
}
