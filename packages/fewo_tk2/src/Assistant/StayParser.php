<?php

namespace FewoTk2\Assistant;

use DateTimeImmutable;

/**
 * Liest aus einer Nachricht des Chats, um welchen Aufenthalt es geht: "vom 20. bis 27. Dezember zu dritt",
 * "5 Nächte ab 3.2.", "from 12 July to 19 July, 2 adults and 1 child".
 *
 * Damit rechnet der Server den Preis selbst aus (wie der Preisrechner) und gibt dem Sprachmodell das
 * Ergebnis: Kleine Modelle verrechnen sich sonst – sie vergessen die Endreinigung oder den Wechsel der Saison.
 */
final class StayParser
{
    private const MONTHS = [
        'januar' => 1, 'january' => 1, 'jan' => 1,
        'februar' => 2, 'february' => 2, 'feb' => 2,
        'märz' => 3, 'maerz' => 3, 'march' => 3, 'mär' => 3, 'mrz' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mai' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'dezember' => 12, 'december' => 12, 'dez' => 12, 'dec' => 12,
    ];

    private const NUMBERS = [
        'ein' => 1, 'eine' => 1, 'einer' => 1, 'einen' => 1, 'eins' => 1, 'one' => 1, 'a' => 1,
        'zwei' => 2, 'two' => 2, 'drei' => 3, 'three' => 3, 'vier' => 4, 'four' => 4, 'fünf' => 5, 'five' => 5,
        'sechs' => 6, 'six' => 6, 'sieben' => 7, 'seven' => 7, 'acht' => 8, 'eight' => 8, 'neun' => 9, 'nine' => 9,
        'zehn' => 10, 'ten' => 10, 'elf' => 11, 'eleven' => 11, 'zwölf' => 12, 'twelve' => 12,
        'dreizehn' => 13, 'thirteen' => 13, 'vierzehn' => 14, 'fourteen' => 14,
    ];

    private const TOGETHER = ['zweit' => 2, 'dritt' => 3, 'viert' => 4, 'fünft' => 5, 'sechst' => 6, 'siebt' => 7, 'acht' => 8];

    /** Wörter, die zwei Daten zu einem Zeitraum verbinden. */
    private const RANGE = '(?:bis(?:\s+(?:zum|einschließlich|einschl\.))?|–|—|-|to|until|till|through)';

    /**
     * @param DateTimeImmutable $today Daten ohne Jahr gelten für das nächste Mal ab heute
     */
    public static function parse(string $text, DateTimeImmutable $today): StayRequest
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
        $today = $today->setTime(0, 0);
        $dates = self::dates($text);
        $nights = self::nights($text);

        $arrival = $departure = null;
        if ($dates !== []) {
            // "3. März bis 10. März 2027": Das Jahr der Abreise gilt auch für die Anreise (bzw. das Jahr davor)
            if ($dates[0]['year'] === null && $dates[0]['month'] !== null && isset($dates[1]['year'], $dates[1]['month'])) {
                $before = [$dates[0]['month'], $dates[0]['day']] < [$dates[1]['month'], $dates[1]['day']];
                $dates[0]['year'] = $dates[1]['year'] - ($before ? 0 : 1);
            }
            $arrival = self::resolve($dates[0], $today, null);
            if ($arrival !== null && isset($dates[1])) {
                $departure = self::resolve($dates[1], $today, $arrival);
            }
        }
        // "5 Nächte ab 3.2." nennt Dauer und ein Datum; zwei Daten gehen der Dauer vor
        if ($departure !== null) {
            $nights = null;
        }

        return new StayRequest($arrival, $departure, $nights, self::guests($text));
    }

    /**
     * Fragt die Nachricht nach einem Preis? Nur dann wird ein Zeitraum aus dem bisherigen Gespräch übernommen.
     */
    public static function isPriceQuestion(string $text): bool
    {
        return preg_match('/kost|preis|teuer|günstig|zahl|gesamt|summe|betrag|price|cost|how much|total|rate|pay|charge|€|euro/iu', $text) === 1;
    }

    /**
     * Monate, nach denen die Nachricht fragt ("Was kostet eine Nacht im Dezember?"), als erster Tag des Monats –
     * ohne Jahr das nächste Mal ab dem laufenden Monat. Leer, wenn die Nachricht ein Datum nennt: Dann geht es
     * um einen Aufenthalt (siehe parse()).
     *
     * @return DateTimeImmutable[]
     */
    public static function months(string $text, DateTimeImmutable $today): array
    {
        $text = mb_strtolower($text);
        if (self::dates($text) !== []) {
            return [];
        }
        // nur ausgeschriebene Monatsnamen und gängige Kürzel mit Punkt – "mar", "may" oder "dec" sind zu vieldeutig
        $names = array_filter(array_keys(self::MONTHS), static fn (string $name): bool => mb_strlen($name) > 3 || in_array($name, ['mai', 'juni', 'juli', 'may', 'june', 'july'], true));
        $pattern = '/\b(' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $names)) . ')\b(?:\s(\d{4})(?!\d))?/u';
        $first = $today->setTime(0, 0)->modify('first day of this month');
        $months = [];
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $month = self::MONTHS[$match[1]];
                $year = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : (int) $first->format('Y') + ($month < (int) $first->format('n') ? 1 : 0);
                $date = $first->setDate($year, $month, 1);
                if ($date >= $first) {
                    $months[$date->format('Y-m')] = $date;
                }
            }
        }

        return array_slice(array_values($months), 0, 3);
    }

    /**
     * Fragt die Nachricht nach den Preisen eines Monats? Auch die kurze Nachfrage "Und im Januar?" –
     * aber keine Frage nach freien Terminen ("Ist im Januar noch frei?").
     */
    public static function isMonthPriceQuestion(string $text): bool
    {
        $text = mb_strtolower($text);
        if (preg_match('/\bwann\b|\bwhen\b|frei|verfügbar|available|\bfree\b|buch|book|belegt/u', $text)) {
            return false;
        }

        return self::isPriceQuestion($text) || str_word_count($text) <= 5;
    }

    /**
     * Preisfrage zu dem Aufenthalt, um den es im Gespräch schon ging ("Was würde das dann kosten?",
     * "Und für 10 Nächte?", "Und zu dritt?") – nicht aber eine Frage nach dem Preis pro Nacht oder nach
     * einer anderen Zeit ("Was kostet eine Nacht im Januar?"): Die beantworten die Preise der Website.
     */
    public static function isFollowUpPriceQuestion(string $text): bool
    {
        $text = mb_strtolower($text);
        $months = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys(self::MONTHS)));
        if (preg_match('/\b(?:' . $months . ')\b|\b(?:pro|je|per|eine|einer|one|a) (?:nacht|night|tag|day)\b|nightly|saison|season|sommer|winter|frühling|frühjahr|herbst|summer|spring|autumn|ostern|weihnacht|silvester|pfingst|ferien|easter|christmas/u', $text)) {
            return false;
        }
        if (self::isPriceQuestion($text)) {
            return true;
        }
        // kurze Nachfrage, die nur Dauer oder Gäste ändert – aber keine Frage nach freien Terminen
        $stay = self::parse($text, new DateTimeImmutable('today'));

        return ($stay->nights !== null || $stay->guests !== null)
            && str_word_count($text) <= 7
            && !preg_match('/\bwann\b|\bwhen\b|frei|verfügbar|available|\bfree\b|buch|book|belegt/u', $text);
    }

    /**
     * Alle Daten der Nachricht in der Reihenfolge, in der sie dastehen.
     *
     * @return array<int, array{day: int, month: int|null, year: int|null}>
     */
    private static function dates(string $text): array
    {
        $months = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys(self::MONTHS)));
        $found = [];
        $add = static function (int $position, int $length, int $day, ?int $month, ?int $year) use (&$found): void {
            foreach ($found as $other) {
                if ($position < $other['pos'] + $other['len'] && $other['pos'] < $position + $length) {
                    return; // überschneidet sich mit einem schon erkannten Datum
                }
            }
            if ($year !== null && $year < 100) {
                $year += 2000;
            }
            if ($day >= 1 && $day <= 31 && ($month === null || ($month >= 1 && $month <= 12))) {
                $found[] = ['pos' => $position, 'len' => $length, 'day' => $day, 'month' => $month, 'year' => $year];
            }
        };
        $each = static function (string $pattern, callable $callback) use ($text): void {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $value = static fn (int|string $group): ?int => isset($match[$group]) && $match[$group][0] !== '' ? (int) $match[$group][0] : null;
                    $name = static fn (int|string $group): string => $match[$group][0] ?? '';
                    $callback($match[0][1], strlen($match[0][0]), $value, $name);
                }
            }
        };

        // 2026-12-20
        $each('/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/', static fn ($pos, $len, $v) => $add($pos, $len, $v(3), $v(2), $v(1)));
        // 20.12.2026, 20.12.26, 20.12.
        $each('/(?<![\d.])(\d{1,2})\.\s?(\d{1,2})\.(?:\s?(\d{4}|\d{2})(?![\d.]))?/', static fn ($pos, $len, $v) => $add($pos, $len, $v(1), $v(2), $v(3)));
        // 20. Dezember 2026, 20 dec
        $each('/(?<![\d.])(\d{1,2})\.?\s?(' . $months . ')\b\.?(?:\s?(\d{4})(?!\d))?/u', static fn ($pos, $len, $v, $n) => $add($pos, $len, $v(1), self::MONTHS[$n(2)], $v(3)));
        // December 20th, 2026
        $each('/\b(' . $months . ')\.?\s(\d{1,2})(?:st|nd|rd|th)?(?![\d.])(?:,?\s(\d{4})(?!\d))?/u', static fn ($pos, $len, $v, $n) => $add($pos, $len, $v(2), self::MONTHS[$n(1)], $v(3)));
        // 20.12 ohne Punkt am Ende – nur als Teil eines Zeitraums ("20.12 bis 27.12", "ab 20.12")
        $each('/(?:(?<=\bvom |\bab |\bam |\bzum |\bbis |\bfrom |\bto |\bon )(?<![\d.])(\d{1,2})\.(\d{1,2})(?![\d.])|(?<![\d.])(\d{1,2})\.(\d{1,2})(?= ' . self::RANGE . ' ))/u',
            static fn ($pos, $len, $v) => $add($pos, $len, $v(1) ?? $v(3) ?? 0, $v(2) ?? $v(4), null));
        // "20. bis 27. Dezember", "20.–27.12.": der erste Tag übernimmt Monat und Jahr des zweiten
        $each('/(?<![\d.])(\d{1,2})\.?\s?' . self::RANGE . '\s?(?=\d)/u', static function ($pos, $len, $v) use (&$found, $add): void {
            foreach ($found as $date) {
                if ($date['pos'] === $pos + $len && $date['month'] !== null) {
                    $add($pos, $len, $v(1), $date['month'], $date['year']);
                }
            }
        });

        usort($found, static fn (array $a, array $b): int => $a['pos'] <=> $b['pos']);

        return array_map(static fn (array $date): array => ['day' => $date['day'], 'month' => $date['month'], 'year' => $date['year']], $found);
    }

    /**
     * @param array{day: int, month: int|null, year: int|null} $date
     * @param DateTimeImmutable|null $after Anreise, wenn es um die Abreise geht
     */
    private static function resolve(array $date, DateTimeImmutable $today, ?DateTimeImmutable $after): ?DateTimeImmutable
    {
        if ($date['month'] === null) {
            return null;
        }
        $make = static fn (int $year): ?DateTimeImmutable => checkdate($date['month'], $date['day'], $year)
            ? $today->setDate($year, $date['month'], $date['day'])
            : null;
        if ($date['year'] !== null) {
            return $make($date['year']);
        }
        // ohne Jahr: das nächste Mal ab heute bzw. nach der Anreise
        $from = $after ?? $today->modify('-1 day');
        foreach ([0, 1] as $offset) {
            $candidate = $make((int) $from->format('Y') + $offset);
            if ($candidate !== null && $candidate > $from) {
                return $candidate;
            }
        }

        return null;
    }

    private static function nights(string $text): ?int
    {
        $number = '(\d{1,3}|' . implode('|', array_keys(self::NUMBERS)) . ')';
        if (preg_match('/\b' . $number . '\s(?:nächte|nächten|nacht|übernachtung(?:en)?|nights?)\b/u', $text, $match)) {
            return self::number($match[1]);
        }
        if (preg_match('/\b' . $number . '\s(?:wochen?|weeks?)\b/u', $text, $match)) {
            return self::number($match[1]) * 7;
        }

        return null;
    }

    private static function guests(string $text): ?int
    {
        $number = '(\d{1,2}|' . implode('|', array_keys(self::NUMBERS)) . ')';
        $guests = 0;
        if (preg_match('/\b' . $number . '\s(?:personen|person|gäste[n]?|gast|leute[n]?|guests?|people|persons?)\b/u', $text, $match)) {
            $guests = self::number($match[1]);
        } else {
            if (preg_match('/\b' . $number . '\s(?:erwachsene[nr]?|adults?)\b/u', $text, $match)) {
                $guests += self::number($match[1]);
            }
            if (preg_match('/\b' . $number . '\s(?:kind(?:er|ern)?|kleinkind(?:er|ern)?|bab(?:y|ys|ies)|children|child|kids?)\b/u', $text, $match)) {
                $guests += self::number($match[1]);
            }
        }
        if ($guests === 0 && preg_match('/\bzu (' . implode('|', array_keys(self::TOGETHER)) . ')\b/u', $text, $match)) {
            $guests = self::TOGETHER[$match[1]];
        }
        if ($guests === 0 && preg_match('/\b(?:wir sind|we are)\s' . $number . '\b/u', $text, $match)) {
            $guests = self::number($match[1]);
        }
        if ($guests === 0 && preg_match('/\b(?:allein|alleine|alone)\b/u', $text)) {
            $guests = 1;
        }

        return $guests > 0 ? $guests : null;
    }

    private static function number(string $value): int
    {
        return ctype_digit($value) ? (int) $value : (self::NUMBERS[$value] ?? 0);
    }
}
