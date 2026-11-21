<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Calendar\Booking;
use FewoTk2\Calendar\BookingReader;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Feed\InvalidFeedException;
use PHPUnit\Framework\TestCase;

/**
 * Belegung aus Fewo-Office. Die Dateien tests/fixtures/fewo-office-belegung.*
 * sind unveränderte Antworten der Schnittstelle (/api/wohnungen/{kürzel}/belegung.json, .xml und .ics).
 */
final class BookingReaderTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, $this->tz);
    }

    /**
     * @return string[] Belegungen als "Anreise–Abreise" bzw. "Anreise–Abreise?" (auf Anfrage)
     */
    private function bookings(string $body, string $from = '2026-10-01', string $until = '2027-12-01', array $keywords = []): array
    {
        $list = array_map(
            static fn (Booking $b): string => $b->start->format('Y-m-d') . '–' . $b->end->format('Y-m-d') . ($b->onRequest ? '?' : ''),
            (new BookingReader($this->tz, $keywords))->read($body, $this->day($from), $this->day($until))
        );
        sort($list);

        return $list;
    }

    private function fixture(string $extension): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/fewo-office-belegung.' . $extension);
    }

    public function testReadsJson(): void
    {
        $bookings = $this->bookings($this->fixture('json'));

        self::assertCount(13, $bookings);
        self::assertSame('2026-10-24–2026-11-01', $bookings[0]);
        self::assertSame('2027-08-27–2027-09-01', $bookings[12]);
    }

    public function testAllThreeFormatsYieldTheSameOccupancy(): void
    {
        $json = $this->bookings($this->fixture('json'));

        self::assertSame($json, $this->bookings($this->fixture('xml')));
        // ICS: Anreise 15 Uhr, Abreise 10 Uhr – gezählt werden die Kalendertage
        self::assertSame($json, $this->bookings($this->fixture('ics')));
    }

    public function testChangeoverDays(): void
    {
        $reader = new BookingReader($this->tz);
        $occupancy = new Occupancy($reader->read($this->fixture('json'), $this->day('2026-11-01'), $this->day('2026-12-31')));

        self::assertSame(['am' => 'free', 'pm' => 'booked'], $occupancy->day($this->day('2026-11-06')));
        self::assertSame(['am' => 'booked', 'pm' => 'free'], $occupancy->day($this->day('2026-11-09')));
        // 15.11.: Abreise der einen, Anreise der nächsten Buchung
        self::assertSame(['am' => 'booked', 'pm' => 'booked'], $occupancy->day($this->day('2026-11-15')));
        self::assertSame(['am' => 'free', 'pm' => 'free'], $occupancy->day($this->day('2026-11-10')));
    }

    public function testAvailabilityForAStay(): void
    {
        $reader = new BookingReader($this->tz);
        $occupancy = new Occupancy($reader->read($this->fixture('json'), $this->day('2026-11-01'), $this->day('2026-12-31')));

        // Belegt: 6.–9.11., 12.–15.11., 15.–27.11., 27.11.–12.12., 12.–20.12.
        self::assertTrue($occupancy->isFree($this->day('2026-11-01'), $this->day('2026-11-06')));
        // Anreise am Abreisetag und Abreise am Anreisetag anderer Gäste sind möglich
        self::assertTrue($occupancy->isFree($this->day('2026-11-09'), $this->day('2026-11-12')));
        self::assertFalse($occupancy->isFree($this->day('2026-11-08'), $this->day('2026-11-12')));
        self::assertFalse($occupancy->isFree($this->day('2026-11-09'), $this->day('2026-11-13')));
        self::assertFalse($occupancy->isFree($this->day('2026-11-01'), $this->day('2026-12-31')));

        // Aneinander anschließende Buchungen ergeben einen Zeitraum
        self::assertSame(
            [['2026-11-06', '2026-11-09'], ['2026-11-12', '2026-12-20']],
            $occupancy->getBookedRanges($this->day('2026-11-01'), $this->day('2026-12-31'))
        );
        // Ein Zeitraum, der über das Ende hinausreicht, wird dort abgeschnitten
        self::assertSame([['2026-11-12', '2026-11-20']], $occupancy->getBookedRanges($this->day('2026-11-10'), $this->day('2026-11-20')));
        self::assertSame([], (new Occupancy())->getBookedRanges($this->day('2026-11-01'), $this->day('2026-12-31')));
    }

    public function testLimitsBookingsToTheRequestedPeriod(): void
    {
        // Die erste Buchung endet am 1.11. und berührt den Zeitraum nicht mehr, die vom 15.–27.11. wird abgeschnitten
        self::assertSame(
            ['2026-11-06–2026-11-09', '2026-11-12–2026-11-15', '2026-11-15–2026-11-20'],
            $this->bookings($this->fixture('json'), '2026-11-01', '2026-11-20')
        );
    }

    public function testKeywordsMarkEntriesAsOnRequest(): void
    {
        $body = '{"status":{"type":"OK"},"bookings":['
            . '{"date_start":"2027-02-01","date_end":"2027-02-05","vendor":"direkt","info":"Betriebsferien"},'
            . '{"date_start":"2027-02-10","date_end":"2027-02-12","vendor":"airbnb","info":null}]}';

        self::assertSame(['2027-02-01–2027-02-05', '2027-02-10–2027-02-12'], $this->bookings($body));
        self::assertSame(['2027-02-01–2027-02-05?', '2027-02-10–2027-02-12'], $this->bookings($body, keywords: ['betriebsferien']));
    }

    public function testSkipsInvalidEntries(): void
    {
        $body = '{"status":{"type":"OK"},"bookings":['
            . '{"date_start":"2027-02-30","date_end":"2027-03-02"},'
            . '{"date_start":"2027-03-05","date_end":"2027-03-05"},'
            . '{"date_start":"2027-03-10"},'
            . '"unsinn",'
            . '{"date_start":"2027-03-20","date_end":"2027-03-22"}]}';

        self::assertSame(['2027-03-20–2027-03-22'], $this->bookings($body));
        self::assertSame([], $this->bookings('<fewobacher data="occupancy"><status><type>OK</type></status><bookings/></fewobacher>'));
    }

    /**
     * @dataProvider invalidDocuments
     */
    public function testRejectsInvalidData(string $body, string $message): void
    {
        $this->expectException(InvalidFeedException::class);
        $this->expectExceptionMessage($message);
        $this->bookings($body);
    }

    public static function invalidDocuments(): array
    {
        return [
            'leer' => ['', 'neither JSON nor XML'],
            'Text' => ['Wartungsarbeiten', 'neither JSON nor XML'],
            'HTML-Fehlerseite' => ['<html><body>404</body></html>', 'does not contain an occupancy list'],
            'Preisliste statt Belegung' => [file_get_contents(__DIR__ . '/../fixtures/fewo-office-preise.json'), 'does not contain an occupancy list'],
            'Fehlermeldung von Fewo-Office' => ['<fewobacher><status><type>ERROR</type><message>Unbekannte Wohnung</message></status></fewobacher>', 'Unbekannte Wohnung'],
        ];
    }
}
