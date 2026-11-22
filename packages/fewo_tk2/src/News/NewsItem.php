<?php

namespace FewoTk2\News;

use DateTimeImmutable;

/**
 * Eine Neuigkeit aus Fewo-Office: Titel und Text, gültig von $dateFrom bis $dateTo (beide Tage zählen mit),
 * mit dem Code ihrer Wichtigkeit aus Fewo-Office ("info", "notice", "important"). Wie eine Stufe aussieht, legt der
 * Block fest (siehe ImportanceLevels).
 */
final class NewsItem
{
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const IMPORTANT = 'important';

    /** Code der Wichtigkeit in Kleinbuchstaben. */
    public readonly string $importance;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $text,
        public readonly DateTimeImmutable $dateFrom,
        public readonly DateTimeImmutable $dateTo,
        string $importance = self::INFO,
    ) {
        // Ältere Daten ohne Angabe: Info. Unbekannte Codes bleiben erhalten – der Block kann sie einer Stufe zuordnen
        $code = ImportanceLevels::code($importance);
        $this->importance = $code !== '' ? $code : self::INFO;
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
