<?php

namespace FewoTk2\Pricing;

use DateTimeImmutable;

/**
 * Ergebnis einer Preisberechnung. Alle Beträge in Cent.
 */
final class Quote
{
    /**
     * @param QuoteLine[] $lines
     */
    public function __construct(
        public readonly DateTimeImmutable $arrival,
        public readonly DateTimeImmutable $departure,
        public readonly int $guests,
        public readonly array $lines,
        public readonly int $cleaningFee,
        public readonly float $downPaymentPercent,
        public readonly string $currency,
    ) {
    }

    public function getNights(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line) => $line->nights, $this->lines));
    }

    public function getRent(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line) => $line->getSubtotal(), $this->lines));
    }

    /**
     * Mietpreis inklusive Endreinigung.
     */
    public function getTotal(): int
    {
        return $this->getRent() + $this->cleaningFee;
    }

    /**
     * Anzahlung, auf volle Währungseinheiten abgerundet.
     */
    public function getDownPayment(): int
    {
        if ($this->downPaymentPercent <= 0) {
            return 0;
        }

        return intdiv((int) floor($this->getTotal() * $this->downPaymentPercent / 100), 100) * 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'arrival' => $this->arrival->format('Y-m-d'),
            'departure' => $this->departure->format('Y-m-d'),
            'nights' => $this->getNights(),
            'guests' => $this->guests,
            'lines' => array_map(static fn (QuoteLine $line) => $line->toArray(), $this->lines),
            'rent' => $this->getRent(),
            'cleaningFee' => $this->cleaningFee,
            'total' => $this->getTotal(),
            'downPayment' => $this->getDownPayment(),
            'downPaymentPercent' => $this->downPaymentPercent,
            'currency' => $this->currency,
        ];
    }
}
