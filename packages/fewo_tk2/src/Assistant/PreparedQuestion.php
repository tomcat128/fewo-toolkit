<?php

namespace FewoTk2\Assistant;

/**
 * Eine geprüfte Frage mit allem, was das Sprachmodell zum Antworten braucht.
 */
final class PreparedQuestion
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array{title: string, url: string}> $sources Seiten, die zur Frage passen
     * @param int|null $contextWindow Kontextfenster des Modells in Token, passend zum Umfang der Website
     * @param bool $complete true, wenn alle Inhalte der Website enthalten sind (der Anfang ist dann bei jeder Frage gleich)
     */
    public function __construct(
        public readonly AssistantSettings $settings,
        public readonly array $messages,
        public readonly array $sources,
        public readonly bool $complete = false,
        public readonly ?int $contextWindow = null,
    ) {
    }
}
