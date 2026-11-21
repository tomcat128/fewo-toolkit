<?php

namespace FewoTk2\Assistant;

use DateTimeImmutable;

/**
 * Was eine Nachricht über einen Aufenthalt verrät: Anreise, Abreise bzw. Dauer und Zahl der Gäste –
 * jeweils null, wenn die Nachricht dazu nichts sagt (siehe StayParser).
 */
final class StayRequest
{
    public function __construct(
        public readonly ?DateTimeImmutable $arrival = null,
        public readonly ?DateTimeImmutable $departure = null,
        public readonly ?int $nights = null,
        public readonly ?int $guests = null,
    ) {
    }

    public function hasDates(): bool
    {
        return $this->arrival !== null && $this->getDeparture() !== null;
    }

    /**
     * Abreisetag: ausdrücklich genannt oder aus Anreise und Dauer.
     */
    public function getDeparture(): ?DateTimeImmutable
    {
        if ($this->departure !== null) {
            return $this->departure;
        }

        return $this->arrival !== null && $this->nights !== null ? $this->arrival->modify('+' . $this->nights . ' days') : null;
    }

    /**
     * Ergänzt, was hier fehlt, aus einer früheren Nachricht des Gesprächs.
     */
    public function completedBy(self $earlier): self
    {
        $arrival = $this->arrival;
        $departure = $this->departure;
        $nights = $this->nights;
        if ($arrival === null && $earlier->arrival !== null) {
            $arrival = $earlier->arrival;
            // eine neu genannte Dauer gilt ab der früher genannten Anreise
            if ($nights === null) {
                $departure = $earlier->departure;
                $nights = $earlier->nights;
            }
        }

        return new self($arrival, $departure, $nights, $this->guests ?? $earlier->guests);
    }
}
