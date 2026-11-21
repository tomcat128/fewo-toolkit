<?php

namespace FewoTk2\Pricing;

use FewoTk2\Entity\RemoteFeed;

/**
 * Ergebnis von PricelistLoader::load(): die Preisliste oder der Grund, warum es keine gibt.
 */
final class LoadedPricelist
{
    public function __construct(
        public readonly ?Pricelist $pricelist,
        public readonly ?RemoteFeed $feed,
        public readonly ?string $error,
    ) {
    }
}
