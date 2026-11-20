<?php

namespace FewoToolkit\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Liest Belegungen aus einem ICS-Kalender (Airbnb, Booking.com, Fewo-direkt,
 * Google Kalender, Smoobu, …).
 */
class IcsParser
{
    /**
     * @param string[] $onRequestKeywords Ereignisse, deren Titel eines dieser Wörter enthält, gelten als "auf Anfrage"
     */
    public function __construct(
        private readonly DateTimeZone $timezone,
        private readonly array $onRequestKeywords = ['Auf Anfrage'],
    ) {
    }

    /**
     * @return Booking[]
     *
     * @throws InvalidCalendarException
     */
    public function parse(string $ics, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        try {
            $calendar = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable $e) {
            throw new InvalidCalendarException('The calendar could not be read: ' . $e->getMessage(), 0, $e);
        }
        if (!$calendar instanceof VCalendar) {
            throw new InvalidCalendarException('The data is not an iCalendar (VCALENDAR) file.');
        }

        try {
            // Serientermine auflösen und auf den benötigten Zeitraum beschränken
            $calendar = $calendar->expand($from, $until, $this->timezone);
        } catch (Throwable $e) {
            throw new InvalidCalendarException('The calendar events could not be expanded: ' . $e->getMessage(), 0, $e);
        }

        $bookings = [];
        foreach ($calendar->select('VEVENT') as $event) {
            $booking = $this->toBooking($event);
            if ($booking !== null) {
                $bookings[] = $booking;
            }
        }

        return $bookings;
    }

    private function toBooking(VEvent $event): ?Booking
    {
        if (!isset($event->DTSTART)) {
            return null;
        }
        if (strtoupper((string) ($event->STATUS ?? '')) === 'CANCELLED') {
            return null;
        }

        $start = $this->toDay($event->DTSTART->getDateTime($this->timezone));
        if (isset($event->DTEND)) {
            $end = $this->toDay($event->DTEND->getDateTime($this->timezone));
        } elseif (isset($event->DURATION)) {
            $end = $this->toDay($event->DTSTART->getDateTime($this->timezone)->add($event->DURATION->getDateInterval()));
        } else {
            $end = $start->modify('+1 day');
        }
        if ($end <= $start) {
            // Ereignis innerhalb eines Tages: diesen Tag als belegte Nacht behandeln
            $end = $start->modify('+1 day');
        }

        return new Booking($start, $end, $this->isOnRequest((string) ($event->SUMMARY ?? '')));
    }

    private function toDay(DateTimeInterface $dateTime): DateTimeImmutable
    {
        $local = DateTimeImmutable::createFromInterface($dateTime)->setTimezone($this->timezone);

        return new DateTimeImmutable($local->format('Y-m-d'), $this->timezone);
    }

    private function isOnRequest(string $summary): bool
    {
        foreach ($this->onRequestKeywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && mb_stripos($summary, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
