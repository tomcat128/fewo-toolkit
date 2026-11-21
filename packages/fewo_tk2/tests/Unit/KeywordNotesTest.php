<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\KeywordNotes;
use FewoTk2\Assistant\PromptBuilder;
use PHPUnit\Framework\TestCase;

/**
 * KI-Assistent: Erklärungen zu Stichwörtern – welche Fragen sie auslösen und wie sie das Sprachmodell erreichen.
 */
final class KeywordNotesTest extends TestCase
{
    private const PARKING = 'Die Wohnung hat keinen eigenen Stellplatz. Parken ist an der Straße möglich.';
    private const WIFI = 'WLAN ist kostenlos.';

    /**
     * @return array<int, array{keywords: string, text: string}>
     */
    private function notes(): array
    {
        return [
            ['keywords' => 'Stellplatz, Parkplatz, parken, Auto', 'text' => self::PARKING],
            ['keywords' => 'WLAN, WiFi, Internet', 'text' => self::WIFI],
            ['keywords' => 'TV', 'text' => 'Es gibt einen Fernseher mit Satellitenempfang.'],
        ];
    }

    public function testKeywordsMatchRegardlessOfCaseUmlautsAndCompounds(): void
    {
        $notes = $this->notes();
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'Gibt es einen privaten Stellplatz?'));
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'wie viele stellplätze habt ihr'));
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'Ist ein Tiefgaragenstellplatz dabei?'));
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'Wo kann ich PARKEN?'));
        self::assertSame([self::WIFI], KeywordNotes::match($notes, 'Habt ihr Wlan?'));
        self::assertSame([], KeywordNotes::match($notes, 'Wie weit ist es zum See?'));
        self::assertSame([], KeywordNotes::match($notes, ''));
    }

    public function testShortKeywordsOnlyMatchWholeWords(): void
    {
        $notes = $this->notes();
        self::assertCount(1, KeywordNotes::match($notes, 'Gibt es einen TV?'));
        self::assertSame([], KeywordNotes::match($notes, 'Gibt es Aktivitäten in der Nähe?'));
    }

    public function testSeveralNotesAndThePreviousQuestion(): void
    {
        $notes = $this->notes();
        self::assertSame([self::PARKING, self::WIFI], KeywordNotes::match($notes, 'Gibt es Internet und einen Parkplatz?'));
        // Nachfrage ohne Stichwort: die Frage davor zählt mit
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'Und kostet der etwas?', 'Gibt es einen Stellplatz?'));
        // dieselbe Erklärung nur einmal, auch wenn mehrere ihrer Stichwörter vorkommen
        self::assertSame([self::PARKING], KeywordNotes::match($notes, 'Parkplatz fürs Auto?'));
    }

    public function testNotesAreSanitized(): void
    {
        $notes = KeywordNotes::sanitize(KeywordNotes::fromForm(
            ["  Stellplatz ,, parken;Stellplatz\n Auto ", '', 'x', 'Hund'],
            ["Kein eigener\r\n  Stellplatz.\x00", 'Text ohne Stichwort', 'Stichwort zu kurz', '  ']
        ));
        self::assertSame([['keywords' => 'Stellplatz, parken, Auto', 'text' => 'Kein eigener Stellplatz.']], $notes);

        self::assertSame([], KeywordNotes::sanitize('kein Feld'));
        self::assertSame([], KeywordNotes::sanitize([['keywords' => ['a'], 'text' => null], 'x']));
        $many = array_fill(0, 50, ['keywords' => 'See', 'text' => str_repeat('a', 2000)]);
        $clean = KeywordNotes::sanitize($many);
        self::assertCount(KeywordNotes::MAX_NOTES, $clean);
        self::assertSame(KeywordNotes::MAX_TEXT_CHARS, mb_strlen($clean[0]['text']));
    }

    public function testSettingsKeepNotes(): void
    {
        $settings = AssistantSettings::fromArray(['notes' => [['keywords' => 'Stellplatz', 'text' => self::PARKING], ['keywords' => '', 'text' => 'leer']]]);
        self::assertSame([['keywords' => 'Stellplatz', 'text' => self::PARKING]], $settings->notes);
        // gespeichert und wieder gelesen
        self::assertSame($settings->notes, AssistantSettings::fromArray($settings->toArray())->notes);
        self::assertSame([], AssistantSettings::fromArray([])->notes);
    }

    public function testNotesStandNextToTheQuestionAndLeaveTheBeginningUnchanged(): void
    {
        $build = static fn (array $notes, string $calculation = ''): array => PromptBuilder::build('Seeherz', 'de_DE', new DateTimeImmutable('2026-11-21'), '', [], [], [], 'Gibt es einen Stellplatz?', '', $calculation, $notes);

        $plain = $build([]);
        $with = $build([self::PARKING, '  ']);
        // Regeln und Inhalte bleiben gleich: Ollama muss den Anfang nicht neu einlesen
        self::assertSame($plain[0]['content'], $with[0]['content']);
        self::assertStringContainsString('Information from the hosts', $plain[0]['content']);

        $question = $with[count($with) - 1]['content'];
        self::assertStringStartsWith("Gibt es einen Stellplatz?\n\n" . PromptBuilder::notesHeading() . "\n- " . self::PARKING . "\n\n", $question);
        self::assertStringEndsWith("\n\n" . PromptBuilder::NOTES_REMINDER, $question);
        self::assertStringNotContainsString(PromptBuilder::REMINDER, $question);
        self::assertStringEndsWith("\n\n" . PromptBuilder::REMINDER, $plain[count($plain) - 1]['content']);
        self::assertStringNotContainsString(PromptBuilder::notesHeading(), $plain[count($plain) - 1]['content']);
        self::assertStringNotContainsString(PromptBuilder::NOTES_REMINDER, $plain[count($plain) - 1]['content']);

        // zusammen mit einer Berechnung des Buchungssystems
        $both = $build([self::PARKING], 'Berechnung: 360,00 €')[1]['content'];
        self::assertStringContainsString("- " . self::PARKING . "\n\nBerechnung: 360,00 €\n\n" . PromptBuilder::FIGURES_REMINDER . "\n" . PromptBuilder::NOTES_WITH_FIGURES_REMINDER, $both);
    }
}
