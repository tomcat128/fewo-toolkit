<?php

namespace FewoTk2\News;

use DateTimeImmutable;

/**
 * Eine Neuigkeit aus Fewo-Office: Titel und Text, gültig von $dateFrom bis $dateTo (beide Tage zählen mit).
 */
final class NewsItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $text,
        public readonly DateTimeImmutable $dateFrom,
        public readonly DateTimeImmutable $dateTo,
    ) {
    }

    /**
     * Fällt der Tag in den Zeitraum der Neuigkeit? Verglichen wird das Kalenderdatum, unabhängig von Zeitzonen.
     */
    public function isCurrent(DateTimeImmutable $today): bool
    {
        $day = $today->format('Y-m-d');

        return $this->dateFrom->format('Y-m-d') <= $day && $day <= $this->dateTo->format('Y-m-d');
    }

    /**
     * Der Text in Absätzen (getrennt durch Leerzeilen); Zeilenumbrüche innerhalb eines Absatzes bleiben erhalten.
     *
     * @return string[]
     */
    public function paragraphs(): array
    {
        $paragraphs = preg_split('/\n[ \t]*\n\s*/', $this->text) ?: [];

        return array_values(array_filter(array_map('trim', $paragraphs), static fn (string $paragraph): bool => $paragraph !== ''));
    }
}
