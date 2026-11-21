<?php

namespace FewoTk2\Assistant;

/**
 * Ein Abschnitt der Website-Inhalte, den der Assistent zum Antworten bekommt.
 */
final class Chunk
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $text,
    ) {
    }

    public function length(): int
    {
        return mb_strlen($this->title) + mb_strlen($this->text);
    }
}
