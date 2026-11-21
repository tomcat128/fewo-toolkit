<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Feed\InvalidFeedException;
use FewoTk2\Pricing\PriceCalculator;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PricelistReader;
use FewoTk2\Pricing\Season;
use PHPUnit\Framework\TestCase;

/**
 * Preisliste aus Fewo-Office. Die Dateien tests/fixtures/fewo-office-preise.*
 * sind unveränderte Antworten der Schnittstelle (/api/wohnungen/{kürzel}/preise.json bzw. .xml).
 */
final class PricelistReaderTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-11-20');
    }

    private function read(string $file, ?DateTimeImmutable $today = null): Pricelist
    {
        return (new PricelistReader())->read(file_get_contents(__DIR__ . '/../fixtures/' . $file), $today ?? $this->today);
    }

    /**
     * @return string[]
     */
    private function describe(Pricelist $pricelist): array
    {
        return array_map(
            static fn (Season $s): string => sprintf('%s %s–%s %s', $s->name, $s->dateFrom->format('Y-m-d'), $s->dateTo->format('Y-m-d'), json_encode($s->prices->all())),
            $pricelist->getSeasons()
        );
    }

    public function testReadsJson(): void
    {
        $pricelist = $this->read('fewo-office-preise.json');

        self::assertSame('sonnenseele', $pricelist->name);
        self::assertSame('Ferienapartment Sonnenseele', $pricelist->title);
        self::assertSame(3, $pricelist->maxGuests);
        self::assertSame(2, $pricelist->minNights);
        self::assertSame(5500, $pricelist->cleaningFee);
        self::assertSame(25.0, $pricelist->downPaymentPercent);
        self::assertTrue($pricelist->defaultPrices->isEmpty());

        $seasons = $this->describe($pricelist);
        self::assertCount(19, $seasons);
        self::assertSame('Wintersaison 2026-11-09–2026-12-18 {"1":4400,"2":4400,"3":5400}', $seasons[0]);
        self::assertSame('Weihnachten 2026-12-19–2027-01-10 {"1":6100,"2":6100,"3":7100}', $seasons[1]);
        self::assertSame('Hauptsaison 2029-04-28–2029-09-09 {"1":7900,"2":7900,"3":8900}', $seasons[18]);
    }

    public function testXmlYieldsTheSamePricelistAsJson(): void
    {
        $json = $this->read('fewo-office-preise.json');
        $xml = $this->read('fewo-office-preise.xml');

        self::assertSame($this->describe($json), $this->describe($xml));
        self::assertSame(
            [$json->name, $json->title, $json->maxGuests, $json->minNights, $json->cleaningFee, $json->downPaymentPercent],
            [$xml->name, $xml->title, $xml->maxGuests, $xml->minNights, $xml->cleaningFee, $xml->downPaymentPercent]
        );
    }

    public function testExpiredSeasonsAreDropped(): void
    {
        // Der zwischengespeicherte Stand kann älter sein: am 11.01.2027 sind die ersten beiden Saisons vorbei
        $seasons = $this->describe($this->read('fewo-office-preise.json', new DateTimeImmutable('2027-01-11')));

        self::assertCount(17, $seasons);
        self::assertStringStartsWith('Wintersaison 2027-01-11–2027-03-25', $seasons[0]);

        // Eine Saison gilt bis einschließlich ihres letzten Tages
        self::assertCount(18, $this->describe($this->read('fewo-office-preise.json', new DateTimeImmutable('2027-01-10'))));
    }

    public function testCalculatesLikeFewoOffice(): void
    {
        // Nacht bis 18.12.: Wintersaison 44 €, Nächte bis 19., 20. und 21.12.: Weihnachten 61 €
        $quote = (new PriceCalculator())->quote(
            $this->read('fewo-office-preise.json'),
            new DateTimeImmutable('2026-12-17'),
            new DateTimeImmutable('2026-12-21'),
            2,
            $this->today
        );

        self::assertSame(4400 + 3 * 6100, $quote->getRent());
        self::assertSame(22700 + 5500, $quote->getTotal());
        self::assertSame(7000, $quote->getDownPayment()); // 25 % von 282,00 € = 70,50 € => 70,00 €
    }

    public function testDefaultPricesAndMissingValues(): void
    {
        $pricelist = (new PricelistReader())->read(json_encode([
            'status' => ['type' => 'OK', 'message' => 'Okay!'],
            'pricelist' => ['name' => 'seeherz', 'title' => 'Seeherz', 'cleaning' => null, 'min_stay' => null, 'max_guests' => null, 'commission' => null],
            'seasons' => [
                ['name' => 'Sommer', 'date_start' => '2027-06-01', 'date_end' => '2027-08-31', 'prices' => [['persons' => 2, 'value' => '95.50'], ['persons' => 4, 'value' => '110']]],
                ['name' => 'Ohne Preise', 'date_start' => '2027-09-01', 'date_end' => '2027-09-30', 'prices' => []],
                ['name' => 'Ungültig', 'date_start' => '31.12.2027', 'date_end' => '2028-01-05', 'prices' => [['persons' => 2, 'value' => '50.00']]],
                ['name' => 'Verdreht', 'date_start' => '2027-12-10', 'date_end' => '2027-12-01', 'prices' => [['persons' => 2, 'value' => '50.00']]],
            ],
            'default_prices' => [['persons' => 2, 'value' => '60.00'], ['persons' => 5, 'value' => '80.00']],
        ]), $this->today);

        self::assertSame(['Sommer 2027-06-01–2027-08-31 {"2":9550,"4":11000}'], $this->describe($pricelist));
        self::assertSame([2 => 6000, 5 => 8000], $pricelist->defaultPrices->all());
        // Ohne Angabe: höchste Personenzahl, für die es einen Preis gibt
        self::assertSame(5, $pricelist->maxGuests);
        self::assertSame(1, $pricelist->minNights);
        self::assertSame(0, $pricelist->cleaningFee);
        self::assertSame(0.0, $pricelist->downPaymentPercent);
        self::assertTrue($pricelist->hasPrices());
    }

    public function testPricelistWithoutSeasonsIsValid(): void
    {
        $pricelist = (new PricelistReader())->read('{"status":{"type":"OK"},"pricelist":{"name":"leer"},"seasons":[],"default_prices":[]}', $this->today);

        self::assertFalse($pricelist->hasPrices());
        self::assertSame(1, $pricelist->maxGuests);
    }

    /**
     * @dataProvider invalidDocuments
     */
    public function testRejectsInvalidData(string $body, string $message): void
    {
        $this->expectException(InvalidFeedException::class);
        $this->expectExceptionMessage($message);
        (new PricelistReader())->read($body, $this->today);
    }

    public static function invalidDocuments(): array
    {
        return [
            'leer' => ['', 'neither JSON nor XML'],
            'HTML-Fehlerseite' => ['<html><body>502 Bad Gateway</body></html>', 'does not contain a price list'],
            'kaputtes JSON' => ['{"pricelist": ', 'not valid JSON'],
            'kaputtes XML' => ['<fewobacher><pricelist>', 'not valid XML'],
            'XML mit Dokumenttyp' => ['<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><fewobacher><pricelist><name>&e;</name></pricelist><seasons/></fewobacher>', 'not valid XML'],
            'Belegung statt Preisliste' => [file_get_contents(__DIR__ . '/../fixtures/fewo-office-belegung.json'), 'does not contain a price list'],
            'Fehlermeldung von Fewo-Office' => ['{"status":{"type":"ERROR","message":"Unbekannte Wohnung"},"pricelist":{},"seasons":[]}', 'Unbekannte Wohnung'],
            'Kalenderdatei' => ["BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n", 'neither JSON nor XML'],
        ];
    }
}
