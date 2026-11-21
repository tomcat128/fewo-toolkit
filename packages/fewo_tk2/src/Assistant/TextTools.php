<?php

namespace FewoTk2\Assistant;

/**
 * Text aus den Inhalten der Seiten: HTML entfernen, in Abschnitte teilen, in Suchwörter zerlegen.
 */
final class TextTools
{
    /** Häufige Wörter ohne Aussagekraft für die Auswahl der Abschnitte (deutsch und englisch). */
    private const STOPWORDS = [
        'aber', 'alle', 'als', 'also', 'auch', 'auf', 'aus', 'bei', 'bin', 'bis', 'bitte', 'das', 'dass', 'dem', 'den', 'der', 'des', 'die', 'dies',
        'diese', 'doch', 'dort', 'durch', 'ein', 'eine', 'einem', 'einen', 'einer', 'eines', 'er', 'es', 'euch', 'euer', 'eure', 'fur', 'gibt', 'habe',
        'haben', 'hat', 'hier', 'ich', 'ihr', 'ihre', 'im', 'in', 'ist', 'ja', 'kann', 'kein', 'keine', 'konnen', 'man', 'mit', 'nach', 'nicht', 'noch',
        'nur', 'oder', 'sehr', 'sein', 'sich', 'sie', 'sind', 'so', 'und', 'uns', 'unser', 'unsere', 'vom', 'von', 'vor', 'wann', 'war', 'was', 'welche',
        'wenn', 'wer', 'wie', 'wieviel', 'wir', 'wird', 'wo', 'zu', 'zum', 'zur',
        'about', 'and', 'any', 'are', 'can', 'does', 'for', 'from', 'have', 'how', 'is', 'much', 'not', 'the', 'there', 'this', 'what', 'when',
        'where', 'which', 'with', 'you', 'your',
    ];

    /**
     * HTML in lesbaren Text: Absätze und Listenpunkte werden zu Zeilen, Leerraum wird zusammengefasst.
     */
    public static function plain(string $html): string
    {
        $text = (string) preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is', ' ', $html);
        $text = (string) preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr|/dd|/dt|/blockquote)\b[^>]*>~i', "\n", $text);
        $text = (string) preg_replace('~</(td|th)>~i', ' | ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = (string) preg_replace('/[ \t\x0B\f\r]+/u', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);

        return trim((string) preg_replace('/\n{2,}/', "\n", $text));
    }

    /**
     * Teilt Text an Zeilen- und Satzgrenzen in Abschnitte von höchstens $maxChars Zeichen.
     *
     * @return string[]
     */
    public static function split(string $text, int $maxChars = 900): array
    {
        $maxChars = max(100, $maxChars);
        $parts = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) <= $maxChars) {
                $parts[] = $line;
                continue;
            }
            // Lange Zeile: an Satzenden teilen, zur Not hart
            foreach (preg_split('/(?<=[.!?])\s+/u', $line) ?: [$line] as $sentence) {
                while (mb_strlen($sentence) > $maxChars) {
                    $parts[] = mb_substr($sentence, 0, $maxChars);
                    $sentence = mb_substr($sentence, $maxChars);
                }
                if ($sentence !== '') {
                    $parts[] = $sentence;
                }
            }
        }

        $chunks = [];
        $current = '';
        foreach ($parts as $part) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($part) > $maxChars) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : "\n") . $part;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Suchwörter eines Textes: klein geschrieben, ohne Umlaute und Füllwörter, mit grob entfernter Endung
     * ("Hunde" und "Hund", "Parkplätze" und "Parkplatz" treffen sich so).
     *
     * @return string[]
     */
    public static function terms(string $text): array
    {
        // Umlaute auf den Grundvokal: So treffen sich auch Mehrzahlformen mit Umlaut ("Parkplätze" und "Parkplatz")
        $text = strtr(mb_strtolower($text), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
        $terms = [];
        foreach (preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            if (strlen($word) > 4) {
                $word = (string) preg_replace('/(ern|en|er|es|e|n|s)$/', '', $word);
            }
            $terms[] = $word;
        }

        return $terms;
    }
}
