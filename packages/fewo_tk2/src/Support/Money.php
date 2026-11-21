<?php

namespace FewoTk2\Support;

/**
 * Hilfsfunktionen für Geldbeträge in Cent.
 */
final class Money
{
    /**
     * Wandelt eine Eingabe wie "1.234,50", "1234.5" oder "85" in Cent um.
     */
    public static function parse(?string $input): ?int
    {
        $value = trim((string) $input);
        $value = str_replace(["\u{00A0}", ' ', '€', 'EUR'], '', $value);
        if ($value === '') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            // Deutsches Format: Punkt = Tausender, Komma = Dezimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($lastDot !== false && $lastComma !== false) {
            // Englisches Format mit Tausender-Komma
            $value = str_replace(',', '', $value);
        } elseif ($lastDot !== false && preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            // "1.234" ohne Dezimalteil = Tausenderpunkt
            $value = str_replace('.', '', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    /**
     * Formatiert Cent für Eingabefelder, z. B. 123450 => "1234,50" bzw. 8500 => "85".
     */
    public static function toInput(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }
        if ($cents % 100 === 0) {
            return (string) intdiv($cents, 100);
        }

        return number_format($cents / 100, 2, ',', '');
    }

    /**
     * Formatiert Cent für die Anzeige, z. B. 123450 => "1.234,50 €".
     */
    public static function format(int $cents, string $currency = 'EUR', ?string $locale = null, bool $hideZeroDecimals = true): string
    {
        $locale ??= self::activeLocale();
        $fractionDigits = ($hideZeroDecimals && $cents % 100 === 0) ? 0 : 2;

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $fractionDigits);
            $formatted = $formatter->formatCurrency($cents / 100, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        $symbol = $currency === 'EUR' ? '€' : $currency;

        return number_format($cents / 100, $fractionDigits, ',', '.') . "\u{00A0}" . $symbol;
    }

    /**
     * Formatiert einen Prozentwert ohne überflüssige Nachkommastellen, z. B. 25.0 => "25", 12.5 => "12,5".
     */
    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }

    private static function activeLocale(): string
    {
        if (class_exists(\Concrete\Core\Localization\Localization::class)) {
            return \Concrete\Core\Localization\Localization::activeLocale();
        }

        return 'de_DE';
    }
}
