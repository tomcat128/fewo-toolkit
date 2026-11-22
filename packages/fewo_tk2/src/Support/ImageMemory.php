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
     * und eine Arbeitskopie. Gemessen mit Concrete 9.5.7 an einem Panorama von 5000 × 2500: 11,9 bis
     * 12,6 Byte je Bildpunkt; gerechnet wird mit etwas mehr.
     */
    private const BYTES_PER_PIXEL = 14;

    /** Reserve für alles, was die Seite nach dem Verkleinern noch braucht. */
    private const HEADROOM = 8 * 1024 * 1024;

    /**
     * @param int $fileSize Größe der Bilddatei in Byte: Sie wird zum Entpacken vollständig eingelesen
     * @param int|null $limit Speichergrenze in Byte (null = Einstellung von PHP), -1 = unbegrenzt
     * @param int|null $used bereits belegter Speicher in Byte (null = aktueller Stand)
     */
    public static function canResize(int $width, int $height, int $fileSize = 0, ?int $limit = null, ?int $used = null): bool
    {
        if ($width < 1 || $height < 1) {
            // Größe unbekannt (z. B. SVG): Concrete entscheidet selbst
            return true;
        }
        $limit ??= self::parse((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;
        }

        $needed = $width * $height * self::BYTES_PER_PIXEL + 2 * max(0, $fileSize) + self::HEADROOM;

        // Der tatsächlich belegte Speicher zählt, nicht der einmal vom System geholte: Nach dem ersten großen Bild
        // einer Seite bliebe der sonst hoch, und alle weiteren Bilder würden grundlos nicht mehr verkleinert.
        return ($used ?? memory_get_usage()) + $needed <= $limit;
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
