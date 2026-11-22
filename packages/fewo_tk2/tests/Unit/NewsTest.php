<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Assistant\ApartmentFactSheet;
use FewoTk2\Feed\InvalidFeedException;
use FewoTk2\News\NewsItem;
use FewoTk2\News\NewsReader;
use PHPUnit\Framework\TestCase;

/**
 * Neuigkeiten aus Fewo-Office. Die Dateien tests/fixtures/fewo-office-neuigkeiten.* sind unveränderte Antworten
 * der Schnittstelle (https://fewo-office.com/api/neuigkeiten/seeherz.json, .xml und .rss vom 22.11.2026).
 */
final class NewsTest extends TestCase
{
    private function fixture(string $format): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/fewo-office-neuigkeiten.' . $format);
    }

    /**
     * @return string[]
     */
    private function describe(array $items): array
    {
        return array_map(
            static fn (NewsItem $item): string => sprintf('#%d %s %s %s–%s', $item->id, $item->importance, $item->title, $item->dateFrom->format('Y-m-d'), $item->dateTo->format('Y-m-d')),
            $items
        );
    }

    private function json(array $news): string
    {
        return (string) json_encode(['status' => ['type' => 'OK', 'message' => 'Okay!'], 'news' => $news]);
    }

    public function testJsonAndXmlGiveTheSameNews(): void
    {
        $json = (new NewsReader())->read($this->fixture('json'));
        $xml = (new NewsReader())->read($this->fixture('xml'));

        // Reihenfolge von Fewo-Office (Beginn des Zeitraums, neueste zuerst)
        self::assertSame([
            '#2 notice Parksituation eingeschränkt 2026-11-05–2026-11-25',
            '#3 info Weihnachtsmarkt in Konstanz 2026-11-02–2026-12-31',
            '#1 important Schwimmbadpause 2026-10-01–2026-12-15',
        ], $this->describe($json));
        self::assertSame($this->describe($json), $this->describe($xml));
        self::assertSame(array_map(static fn (NewsItem $item) => $item->text, $json), array_map(static fn (NewsItem $item) => $item->text, $xml));
        self::assertStringStartsWith('In der Schlossstraße gibt es aufgrund von Bauarbeiten keine Stellplätze.', $json[0]->text);
    }

    public function testImportanceLevelsLikeInFewoOffice(): void
    {
        $items = (new NewsReader())->read($this->fixture('json'));
        self::assertSame(['Notice', 'Info', 'Important notice'], array_map(static fn (NewsItem $item) => $item->getImportanceLabel(), $items));

        // Ohne Angabe (Daten vor Einführung der Stufen) und bei unbekannter Stufe: Info
        $other = (new NewsReader())->read($this->json([
            ['id' => 1, 'title' => 'Ohne Stufe', 'text' => 'x', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 2, 'title' => 'Unbekannt', 'text' => 'x', 'importance' => 'urgent', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 3, 'title' => 'Groß geschrieben', 'text' => 'x', 'importance' => ' IMPORTANT ', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 4, 'title' => 'Kein Text', 'text' => 'x', 'importance' => ['notice'], 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
        ]));
        self::assertSame(['info', 'info', 'important', 'info'], array_map(static fn (NewsItem $item) => $item->importance, $other));
    }

    public function testCurrentNewsShowTheImportantOnesFirst(): void
    {
        $items = (new NewsReader())->read($this->fixture('json'));

        self::assertSame([
            '#1 important Schwimmbadpause 2026-10-01–2026-12-15',
            '#2 notice Parksituation eingeschränkt 2026-11-05–2026-11-25',
            '#3 info Weihnachtsmarkt in Konstanz 2026-11-02–2026-12-31',
        ], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-22'))));
        // Nach dem Ende des Hinweises und der Mitteilung bleibt der Weihnachtsmarkt
        self::assertSame(['#3 info Weihnachtsmarkt in Konstanz 2026-11-02–2026-12-31'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-12-16'))));

        // Gleich wichtige behalten die Reihenfolge von Fewo-Office
        $same = (new NewsReader())->read($this->json([
            ['id' => 3, 'title' => 'C', 'text' => 'x', 'importance' => 'info', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 2, 'title' => 'B', 'text' => 'x', 'importance' => 'notice', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 1, 'title' => 'A', 'text' => 'x', 'importance' => 'info', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
        ]));
        self::assertSame([2, 3, 1], array_map(static fn (NewsItem $item) => $item->id, NewsReader::current($same, new DateTimeImmutable('2026-11-15'))));
    }

    public function testRssIsRejectedBecauseItLacksTheEndOfThePeriod(): void
    {
        $this->expectException(InvalidFeedException::class);
        $this->expectExceptionMessage('.json');
        (new NewsReader())->read($this->fixture('rss'));
    }

    public function testOtherDataIsRejected(): void
    {
        // z. B. versehentlich die Adresse der Preisliste eingetragen
        $this->expectException(InvalidFeedException::class);
        (new NewsReader())->read((string) file_get_contents(__DIR__ . '/../fixtures/fewo-office-preise.json'));
    }

    public function testNoCurrentNewsIsNotAnError(): void
    {
        self::assertSame([], (new NewsReader())->read($this->json([])));
        self::assertSame([], (new NewsReader())->read('<?xml version="1.0"?><fewobacher data="news"><status><type>OK</type><message>Okay!</message></status><news/></fewobacher>'));
    }

    public function testOnlyNewsWhosePeriodIncludesTodayAreCurrent(): void
    {
        $items = (new NewsReader())->read($this->json([
            ['id' => 3, 'title' => 'Ab morgen', 'text' => 'x', 'date_start' => '2026-11-23', 'date_end' => '2026-11-30'],
            ['id' => 2, 'title' => 'Nur heute', 'text' => 'x', 'date_start' => '2026-11-22', 'date_end' => '2026-11-22'],
            ['id' => 1, 'title' => 'Bis gestern', 'text' => 'x', 'date_start' => '2026-11-01', 'date_end' => '2026-11-21'],
        ]));

        self::assertCount(3, $items);
        // Beginn und Ende zählen mit; die Uhrzeit spielt keine Rolle
        self::assertSame(['#2 info Nur heute 2026-11-22–2026-11-22'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-22 23:59'))));
        self::assertSame(['#3 info Ab morgen 2026-11-23–2026-11-30'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-23'))));
        self::assertSame(['#1 info Bis gestern 2026-11-01–2026-11-21'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-21'))));
        self::assertSame([], NewsReader::current($items, new DateTimeImmutable('2026-12-01')));
    }

    public function testBrokenEntriesAreSkippedAndTextsCleaned(): void
    {
        $items = (new NewsReader())->read($this->json([
            ['id' => 1, 'title' => "  Neue\n Sauna \x07", 'text' => "Erster Absatz\r\nzweite Zeile\r\n\r\n\r\nZweiter Absatz  ", 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            ['id' => 2, 'title' => 'Ende vor Beginn', 'text' => 'x', 'date_start' => '2026-11-30', 'date_end' => '2026-11-01'],
            ['id' => 3, 'title' => 'Ohne Datum', 'text' => 'x', 'date_start' => '', 'date_end' => '2026-11-30'],
            ['id' => 4, 'title' => 'Falsches Datum', 'text' => 'x', 'date_start' => '2026-02-30', 'date_end' => '2026-03-01'],
            ['id' => 5, 'title' => '', 'text' => '', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
            'kein Eintrag',
            ['title' => ['kein Text'], 'text' => 'Nur Text', 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
        ]));

        self::assertSame(['#1 info Neue Sauna 2026-11-01–2026-11-30', '#0 info  2026-11-01–2026-11-30'], $this->describe($items));
        self::assertSame("Erster Absatz\nzweite Zeile\n\n\nZweiter Absatz", $items[0]->text);
        self::assertSame(["Erster Absatz\nzweite Zeile", 'Zweiter Absatz'], $items[0]->paragraphs());
        self::assertSame(['Nur Text'], $items[1]->paragraphs());

        $long = (new NewsReader())->read($this->json([
            ['id' => 1, 'title' => str_repeat('T', 500), 'text' => str_repeat('a', 9000), 'date_start' => '2026-11-01', 'date_end' => '2026-11-30'],
        ]));
        self::assertSame(NewsReader::MAX_TITLE_CHARS, mb_strlen($long[0]->title));
        self::assertSame(NewsReader::MAX_TEXT_CHARS, mb_strlen($long[0]->text));
    }

    public function testFewoOfficeErrorIsReported(): void
    {
        $this->expectException(InvalidFeedException::class);
        (new NewsReader())->read((string) json_encode(['status' => ['type' => 'ERROR', 'message' => 'Unbekannte Wohnung']]));
    }

    public function testTheAssistantLearnsTheCurrentNews(): void
    {
        $items = NewsReader::current((new NewsReader())->read($this->fixture('json')), new DateTimeImmutable('2026-11-22'));

        self::assertSame(
            "Current news from the hosts:\n"
            . "- Important notice – Schwimmbadpause (valid until 15.12.2026): Das Schwimmbad macht Betriebspause! Wir bitten um Verständnis. Ab dem 15.11.2026 wird es wieder geöffnet haben.\n"
            . "- Notice – Parksituation eingeschränkt (valid until 25.11.2026): In der Schlossstraße gibt es aufgrund von Bauarbeiten keine Stellplätze. Bitte nutzen sie die Parkplätze entlang der Markusstraße.\n"
            . "- Info – Weihnachtsmarkt in Konstanz (valid until 31.12.2026): Der Weihnachtsmarkt öffnet am 28.11.2026 in Konstanz!",
            ApartmentFactSheet::news($items)
        );
        self::assertSame('', ApartmentFactSheet::news([]));
    }
}
