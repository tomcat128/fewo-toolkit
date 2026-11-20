<?php

namespace FewoToolkit\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Zwischenspeicher für einen abgerufenen ICS-Kalender.
 *
 * Der letzte erfolgreich geladene Stand bleibt erhalten, auch wenn ein
 * späterer Abruf fehlschlägt – der Belegungsplan bleibt so immer sichtbar.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoCalendarFeeds")
 */
class CalendarFeed
{
    /**
     * SHA-1 der Feed-URL.
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=40)
     */
    protected string $urlHash;

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

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->urlHash = self::hash($url);
    }

    public static function hash(string $url): string
    {
        return sha1($url);
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
