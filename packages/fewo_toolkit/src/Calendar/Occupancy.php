<?php

namespace FewoToolkit\Calendar;

use DateTimeImmutable;

/**
 * Belegung pro Nacht und daraus abgeleitet pro Tag (vormittags/nachmittags).
 *
 * Vormittags zählt die Nacht davor (Abreise), nachmittags die folgende Nacht
 * (Anreise). So lassen sich Wechseltage halb belegt darstellen.
 */
final class Occupancy
{
    public const FREE = 'free';
    public const BOOKED = 'booked';
    public const REQUEST = 'request';

    /**
     * @var array<string, string> Y-m-d der Nacht => BOOKED|REQUEST
     */
    private array $nights = [];

    /**
     * @param Booking[] $bookings
     */
    public function __construct(array $bookings = [])
    {
        foreach ($bookings as $booking) {
            $this->add($booking);
        }
    }

    public function add(Booking $booking): void
    {
        $status = $booking->onRequest ? self::REQUEST : self::BOOKED;
        for ($night = $booking->start; $night < $booking->end; $night = $night->modify('+1 day')) {
            $key = $night->format('Y-m-d');
            // Eine feste Buchung hat Vorrang vor "auf Anfrage"
            if (($this->nights[$key] ?? null) !== self::BOOKED) {
                $this->nights[$key] = $status;
            }
        }
    }

    public function night(DateTimeImmutable $night): string
    {
        return $this->nights[$night->format('Y-m-d')] ?? self::FREE;
    }

    /**
     * @return array{am: string, pm: string}
     */
    public function day(DateTimeImmutable $day): array
    {
        return [
            'am' => $this->night($day->modify('-1 day')),
            'pm' => $this->night($day),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->nights === [];
    }
}
