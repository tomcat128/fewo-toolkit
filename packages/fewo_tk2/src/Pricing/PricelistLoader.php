<?php

namespace FewoTk2\Pricing;

use FewoTk2\Feed\FeedLoader;
use FewoTk2\Feed\InvalidFeedException;

/**
 * Stellt die Preisliste zu einer Fewo-Office-Adresse bereit (mit Zwischenspeicher).
 */
class PricelistLoader
{
    public function __construct(
        private readonly FeedLoader $feedLoader,
        private readonly PricelistReader $reader,
    ) {
    }

    public function load(string $url, int $refreshMinutes = FeedLoader::DEFAULT_REFRESH_MINUTES, bool $force = false): LoadedPricelist
    {
        $url = FeedLoader::normalizeUrl($url);
        if ($url === null) {
            return new LoadedPricelist(null, null, t('No valid price list URL has been configured.'));
        }

        $feed = $this->feedLoader->load(FeedLoader::PRICELIST, $url, $refreshMinutes, fn (string $body) => $this->reader->read($body), $force);
        if ($feed->getBody() === null) {
            return new LoadedPricelist(null, $feed, t('The price list could not be loaded yet.'));
        }

        try {
            return new LoadedPricelist($this->reader->read($feed->getBody()), $feed, null);
        } catch (InvalidFeedException $e) {
            return new LoadedPricelist(null, $feed, t('The price list data is invalid.') . ' ' . $e->getMessage());
        }
    }
}
