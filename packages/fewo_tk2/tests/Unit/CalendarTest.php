<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Calendar\Booking;
use FewoTk2\Calendar\IcsParser;
use FewoTk2\Calendar\InvalidCalendarException;
use FewoTk2\Calendar\MonthGrid;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Feed\FeedLoader;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
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

    private function parse(string $file, array $keywords = ['Auf Anfrage']): array
    {
        $parser = new IcsParser($this->tz, $keywords);

        return $parser->parse(file_get_contents(__DIR__ . '/../fixtures/' . $file), $this->day('2027-01-01'), $this->day('2028-01-01'));
    }

    public function testParsesAllDayEventsWithExclusiveEnd(): void
    {
        $bookings = $this->parse('airbnb.ics');

        self::assertCount(3, $bookings);
        self::assertSame('2027-03-10', $bookings[0]->start->format('Y-m-d'));
        self::assertSame('2027-03-15', $bookings[0]->end->format('Y-m-d'));
        self::assertFalse($bookings[0]->onRequest);
    }

    public function testDateTimeEventsAreConvertedToLocalDays(): void
    {
        $bookings = $this->parse('airbnb.ics');

        // 2027-04-01T22:30:00Z = 2. April 00:30 in Berlin
        self::assertSame('2027-04-02', $bookings[1]->start->format('Y-m-d'));
        self::assertSame('2027-04-05', $bookings[1]->end->format('Y-m-d'));
    }

    public function testOnRequestKeywordAndCancelledEvents(): void
    {
        $bookings = $this->parse('airbnb.ics');

        self::assertTrue($bookings[2]->onRequest);
        // Das stornierte Ereignis taucht nicht auf
        foreach ($bookings as $booking) {
            self::assertNotSame('2027-05-20', $booking->start->format('Y-m-d'));
        }
    }

    public function testRecurringEventsAreExpanded(): void
    {
        $bookings = (new IcsParser($this->tz))->parse(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
BEGIN:VEVENT
UID:weekly@test
DTSTART;VALUE=DATE:20270104
DTEND;VALUE=DATE:20270106
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Blocked
END:VEVENT
END:VCALENDAR
ICS, $this->day('2027-01-01'), $this->day('2027-12-31'));

        self::assertCount(3, $bookings);
        self::assertSame('2027-01-18', $bookings[2]->start->format('Y-m-d'));
    }

    public function testInvalidCalendarThrows(): void
    {
        $this->expectException(InvalidCalendarException::class);
        (new IcsParser($this->tz))->parse('this is not a calendar', $this->day('2027-01-01'), $this->day('2027-12-31'));
    }

    public function testOccupancyHalfDays(): void
    {
        $occupancy = new Occupancy([
            new Booking($this->day('2027-03-10'), $this->day('2027-03-15')),
            new Booking($this->day('2027-03-15'), $this->day('2027-03-17'), true),
        ]);

        self::assertSame(['am' => 'free', 'pm' => 'free'], $occupancy->day($this->day('2027-03-09')));
        self::assertSame(['am' => 'free', 'pm' => 'booked'], $occupancy->day($this->day('2027-03-10')));
        self::assertSame(['am' => 'booked', 'pm' => 'booked'], $occupancy->day($this->day('2027-03-12')));
        self::assertSame(['am' => 'booked', 'pm' => 'request'], $occupancy->day($this->day('2027-03-15')));
        self::assertSame(['am' => 'request', 'pm' => 'free'], $occupancy->day($this->day('2027-03-17')));
    }

    public function testBookedOverridesRequest(): void
    {
        $occupancy = new Occupancy([
            new Booking($this->day('2027-03-10'), $this->day('2027-03-12'), true),
            new Booking($this->day('2027-03-11'), $this->day('2027-03-13')),
        ]);

        self::assertSame('request', $occupancy->night($this->day('2027-03-10')));
        self::assertSame('booked', $occupancy->night($this->day('2027-03-11')));
    }

    public function testMonthGrid(): void
    {
        $months = MonthGrid::build(new Occupancy(), $this->day('2027-02-14'), 3);

        self::assertCount(3, $months);
        self::assertSame('2027-02', $months[0]['month']->format('Y-m'));
        self::assertSame(0, $months[0]['leading']); // 1. Februar 2027 ist ein Montag
        self::assertCount(28, $months[0]['days']);
        self::assertTrue($months[0]['days'][12]['past']);
        self::assertTrue($months[0]['days'][13]['today']);
        self::assertSame(0, $months[1]['leading']); // 1. März 2027: Montag
        self::assertSame(3, $months[2]['leading']); // 1. April 2027: Donnerstag
    }

    public function testNormalizeUrl(): void
    {
        self::assertSame('https://example.com/cal.ics', FeedLoader::normalizeUrl(' webcal://example.com/cal.ics '));
        self::assertSame('http://example.com/a.ics?x=1', FeedLoader::normalizeUrl('http://example.com/a.ics?x=1'));
        self::assertNull(FeedLoader::normalizeUrl('file:///etc/passwd'));
        self::assertNull(FeedLoader::normalizeUrl('ftp://example.com/a.ics'));
        self::assertNull(FeedLoader::normalizeUrl(''));
    }
}
