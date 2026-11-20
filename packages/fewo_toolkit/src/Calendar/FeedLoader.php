<?php

namespace FewoToolkit\Calendar;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FewoToolkit\Entity\CalendarFeed;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Lädt ICS-Kalender und hält sie in der Datenbank vor.
 *
 * Unabhängig vom Concrete-Cache: Auch wenn der Cache abgeschaltet ist, wird
 * ein Feed höchstens einmal pro Aktualisierungsintervall abgerufen.
 */
class FeedLoader
{
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

    public function getFeed(string $url): ?CalendarFeed
    {
        return $this->entityManager->find(CalendarFeed::class, CalendarFeed::hash($url));
    }

    /**
     * Liefert den Feed und ruft ihn neu ab, wenn er älter als $refreshMinutes ist.
     */
    public function load(string $url, int $refreshMinutes, bool $force = false): CalendarFeed
    {
        $now = new DateTimeImmutable();
        $feed = $this->getFeed($url);
        if ($feed === null) {
            $feed = new CalendarFeed($url);
            $this->entityManager->persist($feed);
        }

        if ($force || $feed->isStale(max(5, $refreshMinutes) * 60, $now)) {
            try {
                $feed->markSuccess($this->download($url), $now);
            } catch (Throwable $e) {
                $feed->markFailure($e->getMessage(), $now);
                $this->logger->warning('Fewo Toolkit: calendar {url} could not be loaded: {error}', [
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
            'headers' => ['Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.1'],
            'allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https']],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('HTTP status %d', $response->getStatusCode()));
        }

        $body = (string) $response->getBody();
        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException('The calendar file is too large.');
        }
        if (stripos(ltrim($body, "\xEF\xBB\xBF \t\r\n"), 'BEGIN:VCALENDAR') !== 0) {
            throw new \RuntimeException('The response is not an iCalendar file.');
        }

        return $body;
    }
}
