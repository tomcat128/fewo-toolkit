<?php

namespace FewoTk2\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Feed\FeedFormat;
use FewoTk2\Feed\FewoOfficeDocument;
use FewoTk2\Feed\InvalidFeedException;

/**
 * Liest die Belegung aus Fewo-Office. Alle drei Formate der Schnittstelle
 * werden am Inhalt erkannt: JSON (empfohlen), XML und ICS.
 */
class BookingReader
{
    /**
     * @param string[] $onRequestKeywords Belegungen, deren Infotext (JSON/XML) bzw. Titel (ICS) eines dieser Wörter
     *                                    enthält, gelten als "auf Anfrage"
     */
    public function __construct(
        private readonly DateTimeZone $timezone,
        private readonly array $onRequestKeywords = [],
    ) {
    }

    /**
     * @return Booking[] Belegungen, die den Zeitraum $from–$until berühren
     *
     * @throws InvalidFeedException
     */
    public function read(string $body, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        if (FeedFormat::detect($body) === FeedFormat::ICS) {
            return (new IcsParser($this->timezone, $this->onRequestKeywords))->parse($body, $from, $until);
        }

        $data = FewoOfficeDocument::decode($body);
        if (!is_array($data['bookings'] ?? null)) {
            throw new InvalidFeedException(t('The data does not contain an occupancy list.'));
        }

        $bookings = [];
        foreach ($data['bookings'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $start = $this->date($entry['date_start'] ?? null);
            $end = $this->date($entry['date_end'] ?? null);
            if ($start === null || $end === null) {
                continue;
            }
            // Auf den benötigten Zeitraum begrenzen
            $start = max($start, $from);
            $end = min($end, $until);
            if ($end <= $start) {
                continue;
            }
            $bookings[] = new Booking($start, $end, self::matchesKeyword((string) ($entry['info'] ?? ''), $this->onRequestKeywords));
        }

        return $bookings;
    }

    /**
     * @param string[] $keywords
     */
    public static function matchesKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
