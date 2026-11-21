<?php

namespace FewoTk2\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Entity\RemoteFeed;
use FewoTk2\Feed\FeedLoader;

/**
 * Stellt die Belegung zu einer Fewo-Office-Adresse bereit (mit Zwischenspeicher).
 */
class OccupancyLoader
{
    public function __construct(private readonly FeedLoader $feedLoader)
    {
    }

    /**
     * Liefert den zwischengespeicherten Stand und ruft ihn bei Bedarf neu ab.
     * Eine Antwort, die sich nicht als Belegung lesen lässt, ersetzt den letzten guten Stand nicht.
     */
    public function load(string $url, int $refreshMinutes = FeedLoader::DEFAULT_REFRESH_MINUTES, bool $force = false): RemoteFeed
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get()));
        $reader = new BookingReader($today->getTimezone());

        return $this->feedLoader->load(
            FeedLoader::OCCUPANCY,
            $url,
            $refreshMinutes,
            static fn (string $body) => $reader->read($body, $today, $today->modify('+1 year')),
            $force
        );
    }
}
