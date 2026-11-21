<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Calendar\Booking;
use FewoTk2\Calendar\IcsParser;
use FewoTk2\Calendar\Occupancy;
use PHPUnit\Framework\TestCase;

/**
 * Kalender, wie sie die gängigen Portale und Channel-Manager ausliefern.
 *
 * Die Dateien unter tests/fixtures sind den Exporten nachgebildet (Aufbau, Zeilenenden,
 * Zeitangaben); sie ersetzen nicht den Test mit dem echten Kalender eines Objekts.
 */
final class CalendarFeedFormatsTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    /**
     * @return string[] Belegungen als "Anreise–Abreise" bzw. "Anreise–Abreise?" (auf Anfrage)
     */
    private function bookings(string $file, array $keywords = ['Auf Anfrage']): array
    {
        $parser = new IcsParser($this->tz, $keywords);
        $bookings = $parser->parse(
            file_get_contents(__DIR__ . '/../fixtures/' . $file),
            new DateTimeImmutable('2027-01-01', $this->tz),
            new DateTimeImmutable('2028-02-01', $this->tz)
        );
        $list = array_map(
            static fn (Booking $b): string => $b->start->format('Y-m-d') . '–' . $b->end->format('Y-m-d') . ($b->onRequest ? '?' : ''),
            $bookings
        );
        sort($list);

        return $list;
    }

    public function testBookingCom(): void
    {
        self::assertSame(['2027-07-12–2027-07-19', '2027-07-19–2027-07-26'], $this->bookings('booking-com.ics'));

        // Zwei Buchungen stoßen aneinander: der Wechseltag ist vormittags und nachmittags belegt
        $parser = new IcsParser($this->tz);
        $occupancy = new Occupancy($parser->parse(
            file_get_contents(__DIR__ . '/../fixtures/booking-com.ics'),
            new DateTimeImmutable('2027-07-01', $this->tz),
            new DateTimeImmutable('2027-08-01', $this->tz)
        ));
        self::assertSame(['am' => 'free', 'pm' => 'booked'], $occupancy->day(new DateTimeImmutable('2027-07-12', $this->tz)));
        self::assertSame(['am' => 'booked', 'pm' => 'booked'], $occupancy->day(new DateTimeImmutable('2027-07-19', $this->tz)));
        self::assertSame(['am' => 'booked', 'pm' => 'free'], $occupancy->day(new DateTimeImmutable('2027-07-26', $this->tz)));
    }

    public function testFewoDirektWithUnixLineEndings(): void
    {
        self::assertSame(['2027-08-01–2027-08-08', '2027-08-15–2027-08-16'], $this->bookings('fewo-direkt.ics'));
    }

    public function testSmoobuWithByteOrderMarkTimesAndFoldedLines(): void
    {
        // Anreise 16 Uhr / Abreise 10 Uhr: gezählt werden die Kalendertage, nicht die Uhrzeiten
        self::assertSame(
            ['2027-09-03–2027-09-10', '2027-09-10–2027-09-12?', '2027-12-24–2028-01-02'],
            $this->bookings('smoobu.ics', ['auf anfrage', 'Option'])
        );
        // Ohne Stichwörter gilt alles als belegt
        self::assertSame(
            ['2027-09-03–2027-09-10', '2027-09-10–2027-09-12', '2027-12-24–2028-01-02'],
            $this->bookings('smoobu.ics', [])
        );
    }

    public function testGoogleCalendarUtcTimesOpenEndAndRecurrence(): void
    {
        self::assertSame([
            // 22:00 UTC am 1.10. ist in Berlin (Sommerzeit) der 2.10., 0:00 Uhr
            '2027-10-02–2027-10-05',
            // Ganztägiger Termin ohne Ende: eine Nacht
            '2027-10-15–2027-10-16',
            // Wöchentlich, drei Wiederholungen
            '2027-11-01–2027-11-03',
            '2027-11-08–2027-11-10',
            '2027-11-15–2027-11-17',
        ], $this->bookings('google.ics'));
    }
}
