<?php

namespace FewoTk2\Assistant;

use RuntimeException;

/**
 * Die Frage kann gerade nicht beantwortet werden. Die Meldung ist für Besucher bestimmt
 * (übersetzt, ohne technische Einzelheiten); der Grund steht im Concrete-Log.
 */
class AssistantException extends RuntimeException
{
    public const DISABLED = 'disabled';
    public const INVALID = 'invalid';
    public const LIMIT = 'limit';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(string $message, public readonly string $reason = self::UNAVAILABLE)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return match ($this->reason) {
            self::INVALID => 422,
            self::LIMIT => 429,
            default => 503,
        };
    }
}
