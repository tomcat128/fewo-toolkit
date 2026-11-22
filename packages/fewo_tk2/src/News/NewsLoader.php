<?php

namespace FewoTk2\News;

use FewoTk2\Feed\FeedLoader;
use FewoTk2\Feed\InvalidFeedException;

/**
 * Stellt die Neuigkeiten zu einer Fewo-Office-Adresse bereit (mit Zwischenspeicher).
 */
class NewsLoader
{
    public function __construct(
        private readonly FeedLoader $feedLoader,
        private readonly NewsReader $reader,
    ) {
    }

    public function load(string $url, int $refreshMinutes = FeedLoader::DEFAULT_REFRESH_MINUTES, bool $force = false): LoadedNews
    {
        $url = FeedLoader::normalizeUrl($url);
        if ($url === null) {
            return new LoadedNews([], null, t('No valid news URL has been configured.'));
        }

        $feed = $this->feedLoader->load(FeedLoader::NEWS, $url, $refreshMinutes, fn (string $body) => $this->reader->read($body), $force);
        if ($feed->getBody() === null) {
            return new LoadedNews([], $feed, t('The news could not be loaded yet.'));
        }

        try {
            return new LoadedNews($this->reader->read($feed->getBody()), $feed, null);
        } catch (InvalidFeedException $e) {
            return new LoadedNews([], $feed, t('The news data is invalid.') . ' ' . $e->getMessage());
        }
    }
}
