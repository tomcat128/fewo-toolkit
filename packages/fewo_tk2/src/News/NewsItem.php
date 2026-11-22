<?php

namespace FewoTk2\News;

use DateTimeImmutable;

/**
 * Eine Neuigkeit aus Fewo-Office: Titel und Text, gültig von $dateFrom bis $dateTo (beide Tage zählen mit),
 * mit ihrer Wichtigkeit wie in Fewo-Office: Info (grün), Hinweis (gelb), Wichtige Mitteilung (rot).
 */
final class NewsItem
{
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const IMPORTANT = 'important';

    /** Von wenig zu sehr wichtig. */
    public const IMPORTANCE_LEVELS = [self::INFO, self::NOTICE, self::IMPORTANT];

    public readonly string $importance;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $text,
        public readonly DateTimeImmutable $dateFrom,
        public readonly DateTimeImmutable $dateTo,
        string $importance = self::INFO,
    ) {
        // Unbekannte Stufe (etwa eine spätere Erweiterung in Fewo-Office) oder ältere Daten ohne Angabe: Info
        $importance = strtolower(trim($importance));
        $this->importance = in_array($importance, self::IMPORTANCE_LEVELS, true) ? $importance : self::INFO;
    }

    /**
     * Bezeichnung der Wichtigkeit in der Sprache der Website.
     */
    public function getImportanceLabel(): string
    {
        return match ($this->importance) {
            self::NOTICE => t('Notice'),
            self::IMPORTANT => t('Important notice'),
            default => t('Info'),
        };
    }

    /**
     * Rangfolge zum Sortieren: wichtige zuerst.
     */
    public function getImportanceRank(): int
    {
        return (int) array_search($this->importance, self::IMPORTANCE_LEVELS, true);
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
