<?php

namespace FewoTk2\Feed;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Entity\RemoteFeed;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Ruft Belegung, Preislisten und Neuigkeiten aus Fewo-Office ab und hält sie in der Datenbank vor.
 *
 * Unabhängig vom Concrete-Cache: Auch wenn der Cache abgeschaltet ist, wird
 * eine Adresse höchstens einmal pro Aktualisierungsintervall abgerufen.
 */
class FeedLoader
{
    /** Arten von Daten; jede hat ihren eigenen Zwischenspeicher. */
    public const OCCUPANCY = 'occupancy';
    public const PRICELIST = 'pricelist';
    public const NEWS = 'news';

    public const DEFAULT_REFRESH_MINUTES = 60;

    /** Wählbare Aktualisierungsintervalle in Minuten. */
    public const REFRESH_OPTIONS = [5, 10, 15, 30, 60, 120, 360, 720, 1440];

    private const MIN_REFRESH_MINUTES = 5;
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Wandelt webcal:// in https:// um und lässt nur http(s) zu.
     */
    public static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('~^webcals?://~i', $url)) {
            $url = 'https://' . preg_replace('~^webcals?://~i', '', $url);
        }
        if (!preg_match('~^https?://~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    public static function sanitizeRefreshMinutes(mixed $minutes): int
    {
        return in_array((int) $minutes, self::REFRESH_OPTIONS, true) ? (int) $minutes : self::DEFAULT_REFRESH_MINUTES;
    }

    /**
     * Zwischengespeicherter Stand, ohne Fewo-Office abzurufen.
     */
    public function getFeed(string $kind, string $url): ?RemoteFeed
    {
        return $this->entityManager->find(RemoteFeed::class, RemoteFeed::hash($kind, $url));
    }

    /**
     * Liefert die Daten und ruft sie neu ab, wenn sie älter als $refreshMinutes sind.
     *
     * @param string $kind self::OCCUPANCY, self::PRICELIST oder self::NEWS
     * @param callable(string): mixed $validate wirft eine Ausnahme, wenn sich die Antwort nicht auswerten lässt;
     *                                          eine unbrauchbare Antwort ersetzt so nie den letzten guten Stand
     */
    public function load(string $kind, string $url, int $refreshMinutes, callable $validate, bool $force = false): RemoteFeed
    {
        $now = new DateTimeImmutable();
        $feed = $this->getFeed($kind, $url);
        if ($feed === null) {
            $feed = new RemoteFeed($kind, $url);
            $this->entityManager->persist($feed);
        }

        if ($force || $feed->isStale(max(self::MIN_REFRESH_MINUTES, $refreshMinutes) * 60, $now)) {
            try {
                $body = $this->download($url);
                $validate($body);
                $feed->markSuccess($body, $now);
            } catch (Throwable $e) {
                $feed->markFailure($e->getMessage(), $now);
                $this->logger->warning('Fewo Toolkit: {url} could not be loaded: {error}', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
            $this->entityManager->flush();
        }

        return $feed;
    }

    private function download(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 15,
            'connect_timeout' => 5,
            'headers' => ['Accept' => 'application/json, application/xml, text/calendar, */*;q=0.1'],
            'allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https']],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('HTTP status %d', $response->getStatusCode()));
        }

        $body = (string) $response->getBody();
        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException('The response is too large.');
        }

        return $body;
    }
}
