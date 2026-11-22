<?php

namespace FewoTk2\News;

/**
 * Stufen der Wichtigkeit, wie der Block "Neuigkeiten" sie darstellt: je Stufe der Code aus dem Feed von Fewo-Office
 * (Feld "importance"), die Bezeichnung über dem Titel und die Farbe. Die Reihenfolge der Stufen ist die Reihenfolge
 * der Anzeige – die erste Stufe steht oben.
 *
 * Fewo-Office kennt "info" (grün), "notice" (gelb) und "important" (rot); das sind die Vorgaben. Weitere Codes
 * lassen sich ergänzen, falls Fewo-Office später mehr Stufen liefert.
 */
final class ImportanceLevels
{
    public const MAX_LEVELS = 10;
    public const MAX_CODE_CHARS = 32;
    public const MAX_LABEL_CHARS = 60;

    /** Farbe für Meldungen, deren Code keiner Stufe zugeordnet ist. */
    public const NEUTRAL_COLOR = '#6b7280';

    /**
     * Vorgaben in der Sprache der Website, Farben wie in Fewo-Office.
     *
     * @return array<int, array{code: string, label: string, color: string}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => NewsItem::IMPORTANT, 'label' => t('Important notice'), 'color' => '#c62828'],
            ['code' => NewsItem::NOTICE, 'label' => t('Notice'), 'color' => '#f9a825'],
            ['code' => NewsItem::INFO, 'label' => t('Information'), 'color' => '#2e7d32'],
        ];
    }

    /**
     * Gespeicherte Stufen eines Blocks (JSON); leer oder unlesbar: die Vorgaben.
     *
     * @return array<int, array{code: string, label: string, color: string}>
     */
    public static function decode(?string $json): array
    {
        $data = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
        $levels = self::sanitize($data);

        return $levels !== [] ? $levels : self::defaults();
    }

    /**
     * @param array<int, array{code: string, label: string, color: string}> $levels
     */
    public static function encode(array $levels): string
    {
        return (string) json_encode(self::sanitize($levels), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Zeilen aus dem Formular des Blocks (je Feld eine Liste in derselben Reihenfolge).
     *
     * @return array<int, array{code: string, label: string, color: string}>
     */
    public static function fromForm(mixed $codes, mixed $labels, mixed $colors): array
    {
        $codes = is_array($codes) ? array_values($codes) : [];
        $labels = is_array($labels) ? array_values($labels) : [];
        $colors = is_array($colors) ? array_values($colors) : [];
        $rows = [];
        foreach ($codes as $i => $code) {
            $rows[] = ['code' => $code, 'label' => $labels[$i] ?? '', 'color' => $colors[$i] ?? ''];
        }

        return self::sanitize($rows);
    }

    /**
     * Bereinigt: Code in Kleinbuchstaben (a–z, 0–9, "-", "_"), jeder Code nur einmal, Bezeichnung einzeilig,
     * Farbe als #rrggbb. Zeilen ohne Code entfallen.
     *
     * @return array<int, array{code: string, label: string, color: string}>
     */
    public static function sanitize(mixed $rows): array
    {
        $levels = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || count($levels) >= self::MAX_LEVELS) {
                continue;
            }
            $code = self::code($row['code'] ?? null);
            if ($code === '' || isset($levels[$code])) {
                continue;
            }
            $label = is_string($row['label'] ?? null) ? trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $row['label'])) : '';
            $levels[$code] = [
                'code' => $code,
                'label' => mb_substr($label, 0, self::MAX_LABEL_CHARS),
                'color' => self::color($row['color'] ?? null) ?? self::NEUTRAL_COLOR,
            ];
        }

        return array_values($levels);
    }

    /**
     * Code aus dem Feed so, wie er verglichen wird ("Important " → "important").
     */
    public static function code(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $code = strtolower(trim($value));

        return preg_match('/^[a-z0-9_-]{1,' . self::MAX_CODE_CHARS . '}$/', $code) ? $code : '';
    }

    /**
     * Stufe zu einem Code – null, wenn der Block ihn nicht kennt.
     *
     * @param array<int, array{code: string, label: string, color: string}> $levels
     *
     * @return array{code: string, label: string, color: string}|null
     */
    public static function find(array $levels, string $code): ?array
    {
        foreach ($levels as $level) {
            if ($level['code'] === $code) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Neuigkeiten in der Reihenfolge der Stufen; Meldungen mit unbekanntem Code zuletzt. Innerhalb einer Stufe
     * bleibt die Reihenfolge von Fewo-Office (usort ist seit PHP 8.0 stabil).
     *
     * @param NewsItem[] $items
     * @param array<int, array{code: string, label: string, color: string}> $levels
     *
     * @return NewsItem[]
     */
    public static function sort(array $items, array $levels): array
    {
        $rank = array_flip(array_column($levels, 'code'));
        $items = array_values($items);
        usort($items, static fn (NewsItem $a, NewsItem $b): int => ($rank[$a->importance] ?? PHP_INT_MAX) <=> ($rank[$b->importance] ?? PHP_INT_MAX));

        return $items;
    }

    /**
     * Farben für die Darstellung einer Stufe: die Farbe selbst (Streifen, Schild), eine gut lesbare Schrift auf
     * dem Schild (schwarz oder weiß, je nachdem, was mehr Kontrast hat) und ein heller Hintergrund der Meldung.
     *
     * @return array{color: string, text: string, background: string}
     */
    public static function colors(string $color): array
    {
        $color = self::color($color) ?? self::NEUTRAL_COLOR;
        [$r, $g, $b] = array_map('hexdec', str_split(substr($color, 1), 2));
        $channel = static function (int $value): float {
            $value /= 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
        // Kontrast zu Weiß: 1.05 / (L + 0.05), zu Schwarz: (L + 0.05) / 0.05
        $text = 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.05 ? '#fff' : '#000';
        // 10 % der Farbe auf Weiß
        $tint = static fn (int $value): int => (int) round(255 - (255 - $value) * 0.1);

        return [
            'color' => $color,
            'text' => $text,
            'background' => sprintf('#%02x%02x%02x', $tint($r), $tint($g), $tint($b)),
        ];
    }

    private static function color(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            $value = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }
}
