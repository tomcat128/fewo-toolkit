<?php

namespace FewoTk2\News;

use DateTimeImmutable;
use FewoTk2\Entity\RemoteFeed;

/**
 * Ergebnis von NewsLoader::load(): die Neuigkeiten oder der Grund, warum es keine gibt.
 */
final class LoadedNews
{
    /**
     * @param NewsItem[] $items alle Neuigkeiten des abgerufenen Stands
     */
    public function __construct(
        public readonly array $items,
        public readonly ?RemoteFeed $feed,
        public readonly ?string $error,
    ) {
    }

    /**
     * Die Neuigkeiten, in deren Zeitraum der Tag fällt, in der Reihenfolge der Stufen.
     *
     * @param array<int, array{code: string, label: string, color: string}>|null $levels null = Vorgaben
     *
     * @return NewsItem[]
     */
    public function current(DateTimeImmutable $today, ?array $levels = null): array
    {
        return NewsReader::current($this->items, $today, $levels);
    }
}
