<?php

namespace FewoTk2\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Zwischenspeicher für abgerufene Daten aus Fewo-Office (Belegung, Preisliste oder Neuigkeiten).
 *
 * Der letzte erfolgreich geladene Stand bleibt erhalten, auch wenn ein
 * späterer Abruf fehlschlägt – Belegungsplan und Preise bleiben so sichtbar.
 *
 * Gespeichert wird je Art der Daten und Adresse: Trägt jemand versehentlich die Adresse der
 * Belegung als Preisliste ein, berührt der Fehlschlag den Stand der Belegung nicht.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoFeeds")
 */
class RemoteFeed
{
    /**
     * SHA-1 aus Art der Daten und Adresse.
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=40)
     */
    protected string $urlHash;

    /**
     * Art der Daten: "occupancy" (Belegung), "pricelist" (Preisliste) oder "news" (Neuigkeiten).
     *
     * @ORM\Column(type="string", length=16)
     */
    protected string $kind;

    /**
     * @ORM\Column(type="text")
     */
    protected string $url;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $body = null;

    /**
     * Zeitpunkt des letzten erfolgreichen Abrufs.
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $fetchedAt = null;

    /**
     * Zeitpunkt des letzten Abrufversuchs (erfolgreich oder nicht).
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $attemptedAt = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $lastError = null;

    public function __construct(string $kind, string $url)
    {
        $this->kind = $kind;
        $this->url = $url;
        $this->urlHash = self::hash($kind, $url);
    }

    public static function hash(string $kind, string $url): string
    {
        return sha1($kind . '|' . $url);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getFetchedAt(): ?DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function getAttemptedAt(): ?DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function markSuccess(string $body, DateTimeImmutable $now): void
    {
        $this->body = $body;
        $this->fetchedAt = $now;
        $this->attemptedAt = $now;
        $this->lastError = null;
    }

    public function markFailure(string $error, DateTimeImmutable $now): void
    {
        $this->attemptedAt = $now;
        $this->lastError = mb_substr($error, 0, 1000);
    }

    public function isStale(int $maxAgeSeconds, DateTimeImmutable $now): bool
    {
        // Nach einem Fehlschlag nicht bei jedem Seitenaufruf erneut versuchen.
        $reference = $this->attemptedAt ?? $this->fetchedAt;

        return $reference === null || $reference->getTimestamp() + $maxAgeSeconds <= $now->getTimestamp();
    }
}
