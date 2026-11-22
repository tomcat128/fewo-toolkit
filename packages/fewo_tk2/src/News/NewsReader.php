<?php

namespace FewoTk2\News;

use DateTimeImmutable;
use FewoTk2\Feed\FewoOfficeDocument;
use FewoTk2\Feed\InvalidFeedException;

/**
 * Liest die Neuigkeiten aus Fewo-Office (/api/neuigkeiten/{kürzel}.json bzw. .xml, am Inhalt erkannt).
 *
 * Fewo-Office liefert nur Neuigkeiten, die am Tag des Abrufs gültig sind. Weil der abgerufene Stand einige
 * Stunden alt sein kann, entscheidet current() noch einmal selbst, was heute gilt: Eine Neuigkeit verschwindet
 * so am Tag nach ihrem Ende von der Website, auch wenn seither nichts neu abgerufen wurde.
 */
class NewsReader
{
    public const MAX_TITLE_CHARS = 200;
    public const MAX_TEXT_CHARS = 5000;

    /**
     * Alle Neuigkeiten der Antwort in der Reihenfolge von Fewo-Office (die neueste zuerst).
     *
     * @return NewsItem[]
     *
     * @throws InvalidFeedException
     */
    public function read(string $body): array
    {
        $data = FewoOfficeDocument::decode($body);
        if (!is_array($data['news'] ?? null)) {
            throw new InvalidFeedException(t('The data does not contain any news.'));
        }

        $items = [];
        foreach ($data['news'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $title = $this->line($entry['title'] ?? null);
            $text = $this->text($entry['text'] ?? null);
            $from = $this->date($entry['date_start'] ?? null);
            $to = $this->date($entry['date_end'] ?? null);
            if (($title === '' && $text === '') || $from === null || $to === null || $to < $from) {
                continue;
            }
            $importance = is_string($entry['importance'] ?? null) ? $entry['importance'] : NewsItem::INFO;
            $items[] = new NewsItem(is_numeric($entry['id'] ?? null) ? (int) $entry['id'] : 0, $title, $text, $from, $to, $importance);
        }

        return $items;
    }

    /**
     * Die Neuigkeiten, in deren Zeitraum der Tag fällt, in der Reihenfolge der Stufen (Vorgabe: Wichtige Mitteilung,
     * Hinweis, Information), innerhalb einer Stufe in der Reihenfolge von Fewo-Office.
     *
     * @param NewsItem[] $items
     * @param array<int, array{code: string, label: string, color: string}>|null $levels null = Vorgaben
     *
     * @return NewsItem[]
     */
    public static function current(array $items, DateTimeImmutable $today, ?array $levels = null): array
    {
        $current = array_filter($items, static fn (NewsItem $item): bool => $item->isCurrent($today));

        return ImportanceLevels::sort($current, $levels ?? ImportanceLevels::defaults());
    }

    private function line(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }
        $line = trim((string) preg_replace('/\s+/u', ' ', $this->clean((string) $value)));

        return mb_substr($line, 0, self::MAX_TITLE_CHARS);
    }

    private function text(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $this->clean((string) $value));

        return mb_substr(trim($text), 0, self::MAX_TEXT_CHARS);
    }

    /**
     * Ohne Steuerzeichen (außer Zeilenumbruch und Tabulator).
     */
    private function clean(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
