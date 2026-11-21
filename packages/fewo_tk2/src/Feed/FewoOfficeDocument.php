<?php

namespace FewoTk2\Feed;

use JsonException;
use SimpleXMLElement;

/**
 * Liest die Export-Schnittstellen von Fewo-Office (Belegung und Preisliste)
 * als JSON oder XML und liefert beide Formate in derselben Struktur:
 *
 *   status:         {type, message}
 *   occupancy:      {name, title}
 *   bookings:       [{date_start, date_end, vendor, info}, …]
 *   pricelist:      {name, title, cleaning, min_stay, max_guests, commission}
 *   seasons:        [{name, date_start, date_end, prices: [{persons, value}, …]}, …]
 *   default_prices: [{persons, value}, …]
 */
final class FewoOfficeDocument
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvalidFeedException
     */
    public static function decode(string $body): array
    {
        $data = match (FeedFormat::detect($body)) {
            FeedFormat::JSON => self::fromJson($body),
            FeedFormat::XML => self::fromXml($body),
            default => throw new InvalidFeedException(t('The data is neither JSON nor XML.')),
        };

        $status = is_array($data['status'] ?? null) ? $data['status'] : [];
        if (strtoupper(trim((string) ($status['type'] ?? 'OK'))) !== 'OK') {
            throw new InvalidFeedException(t('Fewo-Office reports an error: %s', trim((string) ($status['message'] ?? ''))));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromJson(string $body): array
    {
        try {
            $data = json_decode(FeedFormat::stripBom($body), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidFeedException(t('The data is not valid JSON.'));
        }
        if (!is_array($data)) {
            throw new InvalidFeedException(t('The data is not valid JSON.'));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromXml(string $body): array
    {
        // Keine Dokumenttyp-Definitionen: schließt eingebettete und externe Entitäten aus.
        if (stripos($body, '<!DOCTYPE') !== false) {
            throw new InvalidFeedException(t('The data is not valid XML.'));
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string(FeedFormat::stripBom($body), SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            throw new InvalidFeedException(t('The data is not valid XML.'));
        }

        $data = [];
        foreach (['status', 'occupancy', 'pricelist'] as $key) {
            if (isset($xml->{$key})) {
                $data[$key] = self::scalars($xml->{$key});
            }
        }
        if (isset($xml->bookings)) {
            $data['bookings'] = [];
            foreach ($xml->bookings->booking as $booking) {
                $data['bookings'][] = self::scalars($booking);
            }
        }
        if (isset($xml->seasons)) {
            $data['seasons'] = [];
            foreach ($xml->seasons->season as $season) {
                $data['seasons'][] = self::scalars($season) + ['prices' => self::prices($season)];
            }
        }
        if (isset($xml->default_prices)) {
            $data['default_prices'] = self::prices($xml->default_prices);
        }

        return $data;
    }

    /**
     * Kindelemente ohne eigene Kinder als Name => Text.
     *
     * @return array<string, string>
     */
    private static function scalars(SimpleXMLElement $node): array
    {
        $values = [];
        foreach ($node->children() as $name => $child) {
            if ($child->count() === 0) {
                $values[$name] = trim((string) $child);
            }
        }

        return $values;
    }

    /**
     * Im XML steht je Personenzahl ein eigenes <prices>-Element.
     *
     * @return array<int, array<string, string>>
     */
    private static function prices(SimpleXMLElement $node): array
    {
        $prices = [];
        foreach ($node->prices as $price) {
            $prices[] = self::scalars($price);
        }

        return $prices;
    }
}
