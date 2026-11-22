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
 * der Schnittstelle (/api/neuigkeiten/{kürzel}.json, .xml und .rss).
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
            static fn (NewsItem $item): string => sprintf('#%d %s %s–%s', $item->id, $item->title, $item->dateFrom->format('Y-m-d'), $item->dateTo->format('Y-m-d')),
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

        self::assertSame(['#1 Schwimmbadpause 2026-10-01–2026-12-31'], $this->describe($json));
        self::assertSame($this->describe($json), $this->describe($xml));
        self::assertSame($json[0]->text, $xml[0]->text);
        self::assertStringStartsWith('Das Schwimmbad macht Betriebspause!', $json[0]->text);
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
        self::assertSame(['#2 Nur heute 2026-11-22–2026-11-22'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-22 23:59'))));
        self::assertSame(['#3 Ab morgen 2026-11-23–2026-11-30'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-23'))));
        self::assertSame(['#1 Bis gestern 2026-11-01–2026-11-21'], $this->describe(NewsReader::current($items, new DateTimeImmutable('2026-11-21'))));
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

        self::assertSame(['#1 Neue Sauna 2026-11-01–2026-11-30', '#0  2026-11-01–2026-11-30'], $this->describe($items));
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
        $items = (new NewsReader())->read($this->fixture('json'));

        self::assertSame(
            "Current news from the hosts:\n- Schwimmbadpause (valid until 31.12.2026): Das Schwimmbad macht Betriebspause! Wir bitten um Verständnis. Ab dem 15.11.2026 wird es wieder geöffnet haben.",
            ApartmentFactSheet::news($items)
        );
        self::assertSame('', ApartmentFactSheet::news([]));
    }
}
