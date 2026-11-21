<?php

namespace FewoTk2\Feed;

/**
 * Erkennt das Format abgerufener Daten am Inhalt – unabhängig von der Endung
 * der Adresse (.json, .xml, .ics) und vom Content-Type des Servers.
 */
final class FeedFormat
{
    public const JSON = 'json';
    public const XML = 'xml';
    public const ICS = 'ics';

    public static function detect(string $body): ?string
    {
        $body = self::stripBom($body);
        if ($body === '') {
            return null;
        }
        if ($body[0] === '{') {
            return self::JSON;
        }
        if ($body[0] === '<') {
            return self::XML;
        }
        if (stripos($body, 'BEGIN:VCALENDAR') === 0) {
            return self::ICS;
        }

        return null;
    }

    /**
     * Entfernt Byte Order Mark und Leerraum am Anfang.
     */
    public static function stripBom(string $body): string
    {
        return ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body);
    }
}
