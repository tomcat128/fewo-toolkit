<?php

namespace FewoTk2\Support;

/**
 * Reicht der Arbeitsspeicher von PHP, um ein Bild zu verkleinern?
 *
 * Geht der Speicher beim Verkleinern aus, bricht PHP mit einem Fehler ab, der sich nicht abfangen lässt – die
 * ganze Seite fällt aus. Das trifft große Fotos und vor allem 360°-Panoramen auf Servern mit der üblichen
 * Grenze von 128 MB. Die Blöcke fragen deshalb vorher und zeigen sonst das Bild in Originalgröße.
 */
final class ImageMemory
{
    /**
     * Speicher je Bildpunkt beim Verkleinern: Die Bildbibliothek (GD über Imagine) hält das entpackte Original
     * und eine Arbeitskopie mit je rund 6 Byte je Bildpunkt (gemessen: 50 MB für 4096 × 2048).
     */
    private const BYTES_PER_PIXEL = 12;

    /**
     * @param int|null $limit Speichergrenze in Byte (null = Einstellung von PHP), -1 = unbegrenzt
     * @param int|null $used bereits belegter Speicher in Byte (null = aktueller Stand)
     */
    public static function canResize(int $width, int $height, ?int $limit = null, ?int $used = null): bool
    {
        if ($width < 1 || $height < 1) {
            // Größe unbekannt (z. B. SVG): Concrete entscheidet selbst
            return true;
        }
        $limit ??= self::parse((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;
        }

        return ($used ?? memory_get_usage(true)) + $width * $height * self::BYTES_PER_PIXEL <= $limit;
    }

    /**
     * Wert von memory_limit in Byte ("128M", "1G", "-1").
     */
    public static function parse(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
