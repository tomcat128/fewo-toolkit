<?php

namespace FewoTk2\Assistant;

/**
 * Wählt die Abschnitte der Website aus, die zu einer Frage passen.
 *
 * Bewusst einfach gehalten: Wortübereinstimmung statt Vektorsuche. Für die wenigen Seiten einer
 * Ferienwohnungs-Website reicht das, braucht kein zweites Modell und ist nachvollziehbar.
 */
final class Retriever
{
    /** Ein Suchwort gilt als selten, wenn es in höchstens diesem Anteil der Abschnitte vorkommt. */
    private const RARE_SHARE = 0.2;

    /**
     * @param Chunk[] $chunks in der Reihenfolge der Website
     * @param int $budget höchstens so viele Zeichen
     *
     * @return Chunk[] passt alles ins Budget, dann alle Abschnitte in ihrer Reihenfolge (gleichbleibender
     *                 Text lässt Ollama die bereits verarbeiteten Inhalte wiederverwenden), sonst die
     *                 passendsten zuerst
     */
    public static function select(string $question, array $chunks, int $budget): array
    {
        $chunks = array_values($chunks);
        if (array_sum(array_map(static fn (Chunk $chunk) => $chunk->length(), $chunks)) <= $budget) {
            return $chunks;
        }

        [$scores] = self::scores($question, $chunks);
        $order = array_keys($chunks);
        // Bei gleicher Punktzahl entscheidet die Reihenfolge der Website (Startseite zuerst)
        usort($order, static fn (int $a, int $b) => [$scores[$b], $a] <=> [$scores[$a], $b]);

        $selected = [];
        $used = 0;
        foreach ($order as $index) {
            $length = $chunks[$index]->length();
            if ($used + $length > $budget) {
                continue;
            }
            $selected[] = $chunks[$index];
            $used += $length;
        }

        return $selected;
    }

    /**
     * Seiten der Abschnitte, die zur Frage passen – als Quellenangabe unter der Antwort.
     *
     * @param Chunk[] $chunks
     *
     * @return array<int, array{title: string, url: string}>
     */
    public static function sources(string $question, array $chunks, int $limit = 3): array
    {
        $chunks = array_values($chunks);
        [$scores, $matches, $rareTerms] = self::scores($question, $chunks);
        if ($rareTerms === 0) {
            return [];
        }
        // Ein einzelnes zufällig passendes Wort macht eine Seite noch nicht zur Quelle
        $required = min(2, $rareTerms);
        arsort($scores);
        $sources = [];
        foreach ($scores as $index => $score) {
            $chunk = $chunks[$index];
            if ($score <= 0 || count($sources) >= $limit) {
                break;
            }
            if ($matches[$index] >= $required && $chunk->url !== '' && !isset($sources[$chunk->url])) {
                $sources[$chunk->url] = ['title' => $chunk->title, 'url' => $chunk->url];
            }
        }

        return array_values($sources);
    }

    /**
     * Punktzahl je Abschnitt: seltene Wörter der Frage zählen mehr als häufige, Treffer im Seitentitel doppelt.
     * Ein Suchwort trifft auch längere Wörter, die mit ihm beginnen oder es enthalten ("hund" in "hundebett").
     *
     * @param Chunk[] $chunks
     *
     * @return array{0: array<int, float>, 1: array<int, int>, 2: int} Punktzahl und Zahl der getroffenen seltenen Suchwörter je Abschnitt,
     *                                                              Zahl der seltenen Suchwörter der Frage
     */
    private static function scores(string $question, array $chunks): array
    {
        $queryTerms = array_values(array_unique(TextTools::terms($question)));
        $chunkTerms = [];
        foreach ($chunks as $index => $chunk) {
            $chunkTerms[$index] = [
                'title' => array_count_values(TextTools::terms($chunk->title)),
                'text' => array_count_values(TextTools::terms($chunk->text)),
            ];
        }

        $scores = array_fill(0, count($chunks), 0.0);
        $matches = array_fill(0, count($chunks), 0);
        $rareTerms = 0;
        foreach ($queryTerms as $term) {
            $hits = [];
            foreach ($chunkTerms as $index => $terms) {
                $count = 2 * self::count($term, $terms['title']) + self::count($term, $terms['text']);
                if ($count > 0) {
                    $hits[$index] = $count;
                }
            }
            if ($hits === []) {
                continue;
            }
            $weight = log(1 + count($chunks) / count($hits));
            $rare = count($hits) <= max(1, (int) floor(count($chunks) * self::RARE_SHARE));
            $rareTerms += $rare ? 1 : 0;
            foreach ($hits as $index => $count) {
                $scores[$index] += $weight * $count / ($count + 1.5);
                // Für die Quellenangabe zählen nur Wörter, die auf der Website selten sind: "frei" oder
                // "Wohnung" stehen fast überall und sagen nichts darüber, welche Seite zur Frage gehört
                if ($rare) {
                    $matches[$index]++;
                }
            }
        }

        return [$scores, $matches, $rareTerms];
    }

    /**
     * @param array<string, int> $terms Wort => Häufigkeit
     */
    private static function count(string $term, array $terms): float
    {
        $count = (float) ($terms[$term] ?? 0);
        if (strlen($term) >= 4) {
            foreach ($terms as $word => $frequency) {
                $word = (string) $word;
                if ($word !== $term && str_contains($word, $term)) {
                    $count += 0.5 * $frequency;
                }
            }
        }

        return $count;
    }
}
