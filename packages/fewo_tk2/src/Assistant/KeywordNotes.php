<?php

namespace FewoTk2\Assistant;

/**
 * Erklärungen zu Stichwörtern (Dashboard › Fewo Toolkit 2 › KI-Assistent): Kommt in der Frage eines Besuchers
 * eines der Stichwörter vor, bekommt das Sprachmodell die zugehörige Erklärung direkt bei der Frage mit.
 *
 * Damit stellt der Vermieter richtig, was der Assistent sonst falsch oder gar nicht beantwortet – z. B. dass es
 * keinen eigenen Stellplatz gibt, obwohl die Website an einer Stelle vom Parken spricht. Die Erklärung gilt vor
 * den Inhalten der Website.
 */
final class KeywordNotes
{
    public const MAX_NOTES = 30;
    public const MAX_KEYWORDS_CHARS = 200;
    public const MAX_TEXT_CHARS = 600;

    /** Kürzere Stichwörter ("WC", "TV") gelten nur als ganzes Wort, längere auch als Teil eines Wortes. */
    private const MIN_PART_LENGTH = 4;

    /**
     * Bereinigt gespeicherte oder eingegebene Erklärungen: ohne Steuerzeichen, gekürzt, nur vollständige Einträge.
     *
     * @return array<int, array{keywords: string, text: string}>
     */
    public static function sanitize(mixed $value): array
    {
        $notes = [];
        foreach (is_array($value) ? $value : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $keywords = implode(', ', self::keywords(is_scalar($entry['keywords'] ?? null) ? (string) $entry['keywords'] : ''));
            $text = is_scalar($entry['text'] ?? null) ? (string) $entry['text'] : '';
            $text = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text))), 0, self::MAX_TEXT_CHARS);
            if ($keywords !== '' && $text !== '') {
                $notes[] = ['keywords' => $keywords, 'text' => $text];
            }
        }

        return array_slice($notes, 0, self::MAX_NOTES);
    }

    /**
     * Die Zeilen des Formulars im Dashboard: je Erklärung ein Feld mit Stichwörtern und eines mit dem Text.
     *
     * @return array<int, array{keywords: mixed, text: mixed}>
     */
    public static function fromForm(mixed $keywords, mixed $texts): array
    {
        $keywords = is_array($keywords) ? array_values($keywords) : [];
        $texts = is_array($texts) ? array_values($texts) : [];
        $notes = [];
        for ($i = 0, $count = max(count($keywords), count($texts)); $i < $count; $i++) {
            $notes[] = ['keywords' => $keywords[$i] ?? '', 'text' => $texts[$i] ?? ''];
        }

        return $notes;
    }

    /**
     * Die Erklärungen, deren Stichwörter in einem der Texte vorkommen – in der Reihenfolge der Einstellungen.
     * Groß- und Kleinschreibung sowie Umlaute spielen keine Rolle ("Stellplatz" trifft auch "Stellplätze" und
     * "Tiefgaragenstellplatz").
     *
     * @param array<int, array{keywords: string, text: string}> $notes
     * @param string ...$texts die Frage und, für Nachfragen, die Frage davor
     *
     * @return string[]
     */
    public static function match(array $notes, string ...$texts): array
    {
        $haystack = self::fold(implode(' ', $texts));
        if (trim($haystack) === '') {
            return [];
        }
        $matches = [];
        foreach ($notes as $note) {
            foreach (self::keywords((string) ($note['keywords'] ?? '')) as $keyword) {
                $keyword = self::fold($keyword);
                $pattern = mb_strlen($keyword) < self::MIN_PART_LENGTH
                    ? '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/u'
                    : '/' . preg_quote($keyword, '/') . '/u';
                if (preg_match($pattern, $haystack) === 1) {
                    $matches[] = (string) $note['text'];
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * Stichwörter einer Erklärung: durch Komma, Semikolon oder Zeilenumbruch getrennt, ohne Doppelte.
     *
     * @return string[]
     */
    public static function keywords(string $value): array
    {
        $value = mb_substr((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $value), 0, self::MAX_KEYWORDS_CHARS);
        $keywords = [];
        foreach (preg_split('/[,;\n]+/u', $value) ?: [] as $keyword) {
            $keyword = trim((string) preg_replace('/\s+/u', ' ', $keyword));
            // ein einzelnes Zeichen träfe fast jede Frage
            if (mb_strlen($keyword) >= 2) {
                $keywords[self::fold($keyword)] = $keyword;
            }
        }

        return array_values($keywords);
    }

    /**
     * Kleinschreibung ohne Umlaute und Akzente, damit Mehrzahl ("Stellplätze") und Schreibweisen ("Cafe") passen.
     */
    private static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
