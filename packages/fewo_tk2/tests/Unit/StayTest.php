<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\PromptBuilder;
use FewoTk2\Assistant\StayParser;
use FewoTk2\Assistant\StayQuote;
use FewoTk2\Calendar\Booking;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PriceTable;
use FewoTk2\Pricing\Season;
use PHPUnit\Framework\TestCase;

/**
 * KI-Assistent: Aufenthalt aus einer Nachricht lesen, Preis vom Server berechnen lassen (statt vom
 * Sprachmodell), Name des Assistenten und Texte des Chats auf jeder Seite.
 */
final class StayTest extends TestCase
{
    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-21');
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: int|null}
     */
    private function stay(string $text): array
    {
        $stay = StayParser::parse($text, $this->today());

        return [$stay->arrival?->format('Y-m-d'), $stay->getDeparture()?->format('Y-m-d'), $stay->guests];
    }

    public function testDatesAndGuestsAreReadFromGermanAndEnglishMessages(): void
    {
        self::assertSame(['2026-12-20', '2026-12-27', 2], $this->stay('Was kostet es vom 20.12. bis 27.12. für 2 Personen?'));
        self::assertSame(['2026-12-20', '2026-12-27', 3], $this->stay('vom 20. bis 27. Dezember zu dritt'));
        self::assertSame(['2026-12-20', '2026-12-27', null], $this->stay('20.–27.12.2026'));
        self::assertSame(['2026-12-20', '2026-12-27', null], $this->stay('20.12 bis 27.12'));
        self::assertSame(['2026-12-28', '2027-01-03', 2], $this->stay('from December 28 to January 3, 2 adults'));
        self::assertSame(['2026-12-28', '2027-01-03', null], $this->stay('2026-12-28 to 2027-01-03'));
        self::assertSame(['2027-03-03', '2027-03-10', null], $this->stay('Check-in 15.30 Uhr am 3. März bis 10. März 2027'));
        // ohne Jahr: das nächste Mal ab heute, die Abreise nach der Anreise
        self::assertSame(['2027-05-01', '2027-05-08', 2], $this->stay('vom 1.5. bis zum 8.5. für 2 Gäste'));
        self::assertSame(['2026-11-21', '2026-11-23', null], $this->stay('heute, 21.11. bis 23.11.'));
    }

    public function testDurationReplacesTheDepartureDate(): void
    {
        self::assertSame(['2027-02-03', '2027-02-08', 3], $this->stay('5 Nächte ab 3.2. mit 2 Erwachsenen und 1 Kind'));
        self::assertSame(['2027-01-05', '2027-01-12', null], $this->stay('eine Woche ab dem 5. Januar'));
        self::assertSame(['2026-12-31', '2027-01-03', 4], $this->stay('Wir kommen am 31.12. und bleiben 3 Nächte, wir sind vier'));
        self::assertSame(['2026-12-01', '2026-12-15', null], $this->stay('two weeks from 1 December'));
    }

    public function testNumbersThatAreNoDatesAreIgnored(): void
    {
        self::assertSame([null, null, null], $this->stay('Was würde der Aufenthalt dann kosten?'));
        self::assertSame([null, null, null], $this->stay('Die Wohnung hat 2.5 Zimmer und kostet 45,00 €, Check-in 15.30 Uhr'));
        self::assertSame([null, null, null], $this->stay('Gibt es am 31.2. etwas Besonderes?'), 'den 31. Februar gibt es nicht');
        self::assertTrue(StayParser::isPriceQuestion('Was würde der Aufenthalt dann kosten?'));
        self::assertTrue(StayParser::isPriceQuestion('How much is it in total?'));
        self::assertFalse(StayParser::isPriceQuestion('Gibt es WLAN und einen Parkplatz?'));
    }

    public function testOnlyFollowUpQuestionsUseTheStayFromTheConversation(): void
    {
        foreach (['Was würde der Aufenthalt dann kosten?', 'Und für 10 Nächte?', 'Und zu dritt?', 'How much is that in total?', 'Und mit 2 Erwachsenen und 1 Kind?'] as $question) {
            self::assertTrue(StayParser::isFollowUpPriceQuestion($question), $question);
        }
        // Preis pro Nacht oder eine andere Zeit: Das beantworten die Preise der Website, nicht der frühere Aufenthalt
        foreach (['Wann ist die Wohnung als Nächstes für eine Woche frei?', 'Kann ich für 3 Nächte buchen?', 'Was kostet eine Nacht im Januar?', 'Was kostet es pro Nacht?', 'Wie teuer ist es im Sommer?', 'Was kostet die Hauptsaison?', 'How much per night?', 'Gibt es WLAN?', 'Was kostet es an Weihnachten?'] as $question) {
            self::assertFalse(StayParser::isFollowUpPriceQuestion($question), $question);
        }
    }

    public function testEarlierMessagesCompleteTheStay(): void
    {
        $question = StayParser::parse('Was würde der Aufenthalt dann zu dritt kosten?', $this->today());
        $answer = StayParser::parse('Die Wohnung ist frei vom 11.12.2026 bis 16.12.2026 (5 Nächte).', $this->today());
        $stay = $question->completedBy($answer);
        self::assertSame(['2026-12-11', '2026-12-16', 3], [$stay->arrival->format('Y-m-d'), $stay->getDeparture()->format('Y-m-d'), $stay->guests]);

        // "und für 10 Nächte?": neue Dauer ab der früher genannten Anreise
        $stay = StayParser::parse('Und für 10 Nächte?', $this->today())->completedBy($stay);
        self::assertSame(['2026-12-11', '2026-12-21', 3], [$stay->arrival->format('Y-m-d'), $stay->getDeparture()->format('Y-m-d'), $stay->guests]);

        // ein neu genannter Zeitraum gilt, frühere Gäste bleiben
        $stay = StayParser::parse('Und vom 2.1. bis 9.1.?', $this->today())->completedBy($stay);
        self::assertSame(['2027-01-02', '2027-01-09', 3], [$stay->arrival->format('Y-m-d'), $stay->getDeparture()->format('Y-m-d'), $stay->guests]);
    }

    public function testMonthsAreReadFromPriceQuestions(): void
    {
        $months = static fn (string $text): array => array_map(static fn (DateTimeImmutable $month): string => $month->format('Y-m-d'), StayParser::months($text, new DateTimeImmutable('2026-11-21')));

        self::assertSame(['2026-12-01'], $months('Was kostet eine Nacht im Dezember?'));
        self::assertSame(['2026-11-01'], $months('Preise im November'), 'der laufende Monat');
        self::assertSame(['2027-07-01', '2027-08-01'], $months('How much is it in July or August?'));
        self::assertSame(['2028-01-01'], $months('Preis im Januar 2028'));
        self::assertSame([], $months('Was kostet es vom 20. bis 27. Dezember?'), 'ein Datum: Aufenthalt, kein Monat');
        self::assertSame([], $months('Was kostet eine Nacht?'));
        self::assertSame([], $months('Preise im Oktober 2026'), 'schon vorbei');

        self::assertTrue(StayParser::isMonthPriceQuestion('Was kostet eine Nacht im Dezember?'));
        self::assertTrue(StayParser::isMonthPriceQuestion('Und im Januar?'), 'kurze Nachfrage');
        self::assertFalse(StayParser::isMonthPriceQuestion('Ist im Januar noch frei?'));
        self::assertFalse(StayParser::isMonthPriceQuestion('Was kann man im Januar auf der Insel Reichenau unternehmen?'));

        // Saisonwechsel mitten im Monat: beide Abschnitte mit ihren Tagen
        self::assertMatchesRegularExpression(
            '/^December 2026: 01\.12\.2026 to 18\.12\.2026 \(Wintersaison\): 1–2 guests 45\D+, 3 guests 55\D+; 19\.12\.2026 to 31\.12\.2026 \(Weihnachten\): 1–2 guests 70\D+, 3 guests 80\D+$/u',
            \FewoTk2\Assistant\ApartmentFactSheet::month($this->pricelist(), new DateTimeImmutable('2026-12-01'))
        );
        self::assertSame('', \FewoTk2\Assistant\ApartmentFactSheet::month($this->pricelist(), new DateTimeImmutable('2027-06-01')), 'keine Preise');
        // für die Antwort auf eine Monatsfrage: jeder Zeitraum in einer eigenen Zeile
        $lines = \FewoTk2\Assistant\ApartmentFactSheet::monthLines($this->pricelist(), new DateTimeImmutable('2026-12-01'));
        self::assertSame('December 2026 – 2 periods with different prices:', $lines[0]);
        self::assertStringStartsWith('- 01.12.2026 to 18.12.2026 (Wintersaison): 1–2 guests 45', $lines[1]);
        self::assertStringStartsWith('- 19.12.2026 to 31.12.2026 (Weihnachten): 1–2 guests 70', $lines[2]);
        self::assertSame('November 2026:', \FewoTk2\Assistant\ApartmentFactSheet::monthLines($this->pricelist(), new DateTimeImmutable('2026-11-21'))[0]);
        self::assertSame([], \FewoTk2\Assistant\ApartmentFactSheet::monthLines($this->pricelist(), new DateTimeImmutable('2027-06-01')));
    }

    private function pricelist(): Pricelist
    {
        $day = static fn (string $date) => new DateTimeImmutable($date);

        return new Pricelist('seeherz', 'Ferienapartment Seeherz', 3, 2, 6000, 25.0, [
            new Season('Wintersaison', $day('2026-11-09'), $day('2026-12-18'), new PriceTable([2 => 4500, 3 => 5500])),
            new Season('Weihnachten', $day('2026-12-19'), $day('2027-01-10'), new PriceTable([2 => 7000, 3 => 8000])),
        ], new PriceTable());
    }

    public function testQuoteCoversSeasonChangeAndFinalCleaning(): void
    {
        $day = static fn (string $date) => new DateTimeImmutable($date);
        // 16.–21.12.: zwei Nächte Wintersaison, dann drei Nächte Weihnachten – es zählt der Tag, an dem die Nacht
        // endet: Die Nacht vom 18. auf den 19. kostet schon den Preis ab dem 19.
        $text = StayQuote::describe('Ferienapartment Seeherz', $this->pricelist(), null, $day('2026-12-16'), $day('2026-12-21'), 2, $this->today());
        $lines = explode("\n", $text);

        self::assertSame('Ferienapartment Seeherz:', $lines[0]);
        self::assertCount(2, $lines);
        self::assertMatchesRegularExpression(
            '/^- 2 guests: total price 360\D+\(including final cleaning\) = 2 nights × 45\D+\(Wintersaison, 16\.12\.2026 to 18\.12\.2026\) \+ 3 nights × 70\D+\(Weihnachten, 18\.12\.2026 to 21\.12\.2026\) \+ final cleaning 60\D+; down payment 90\D+$/u',
            $lines[1]
        );
        // Die Zwischensumme der Nächte (300) steht nicht da: Das Modell soll sie nicht für den Preis halten
        self::assertStringNotContainsString('300', $lines[1]);
        self::assertSame('Calculation by the booking system for the stay from 16.12.2026 to 21.12.2026 (5 nights):', StayQuote::heading($day('2026-12-16'), $day('2026-12-21')));
        self::assertStringEndsWith('(1 night):', StayQuote::heading($day('2026-12-16'), $day('2026-12-17')));
    }

    public function testQuoteForUnknownGuestsListsEveryPriceLevelAndAvailability(): void
    {
        $day = static fn (string $date) => new DateTimeImmutable($date);
        $free = new Occupancy([new Booking($day('2026-12-06'), $day('2026-12-08'))]);
        $lines = explode("\n", StayQuote::describe('Seeherz', $this->pricelist(), $free, $day('2026-12-08'), $day('2026-12-10'), null, $this->today()));

        // die Belegung steht zuerst
        self::assertStringContainsString('still free', $lines[1]);
        self::assertMatchesRegularExpression('/^- 1–2 guests: total price 150\D+\(including final cleaning\) = 2 nights × 45\D+\(Wintersaison, .+\) \+ final cleaning 60/u', $lines[2]);
        self::assertMatchesRegularExpression('/^- 3 guests: total price 170\D+= 2 nights × 55/u', $lines[3]);

        $booked = new Occupancy([new Booking($day('2026-12-09'), $day('2026-12-12'))]);
        $lines = explode("\n", StayQuote::describe('Seeherz', $this->pricelist(), $booked, $day('2026-12-08'), $day('2026-12-10'), 2, $this->today()));
        self::assertStringContainsString('NOT free', $lines[1]);
        self::assertStringEndsWith('– for information only, because the period is already booked', $lines[2]);

        // Was der Preisrechner ablehnt, wird nicht geschätzt
        $short = StayQuote::describe('Seeherz', $this->pricelist(), null, $day('2026-12-08'), $day('2026-12-09'), null, $this->today());
        self::assertSame("Seeherz:\n- 1–3 guests: cannot be calculated: The minimum stay is 2 nights.", $short);
        $tooMany = StayQuote::describe('Seeherz', $this->pricelist(), null, $day('2026-12-08'), $day('2026-12-10'), 5, $this->today());
        self::assertStringContainsString('5 guests: cannot be calculated: A maximum of 3 guests can stay.', $tooMany);
    }

    public function testCalculationAndNameReachTheModel(): void
    {
        $build = static fn (string $name, string $calculation): array => PromptBuilder::build('Seeherz', 'de_DE', new DateTimeImmutable('2026-11-21'), '', [], [], [], 'Was kostet das?', $name, $calculation);

        $messages = $build('Emma', "Calculation by the booking system …\nSeeherz:\n- 2 guests: total price 360 €\n");
        self::assertStringStartsWith('Your name is "Emma". You are the friendly digital assistant on the website "Seeherz".', $messages[0]['content']);
        self::assertStringContainsString('never add up or multiply prices yourself', $messages[0]['content']);
        // Der berechnete Preis steht bei der Frage – der Anfang bleibt für Ollama unverändert –, danach die
        // Erinnerung, genau diese Zahlen zu nennen
        self::assertSame(
            "Was kostet das?\n\nCalculation by the booking system …\nSeeherz:\n- 2 guests: total price 360 €\n\n" . PromptBuilder::FIGURES_REMINDER,
            $messages[1]['content']
        );

        $plain = $build('', '');
        self::assertStringStartsWith('You are the friendly digital assistant on the website "Seeherz".', $plain[0]['content']);
        self::assertSame("Was kostet das?\n\n" . PromptBuilder::REMINDER, $plain[1]['content']);
        self::assertSame(substr($messages[0]['content'], strlen('Your name is "Emma". ')), $plain[0]['content']);
    }

    public function testNameAndWidgetTextsAreSanitized(): void
    {
        $settings = AssistantSettings::fromArray([
            'enabled' => 1,
            'model' => 'gemma3:4b',
            'name' => "  \"Emma\"\n vom\tSee " . str_repeat('x', 100),
            'widgetEnabled' => '1',
            'widgetHint' => "Fragen?\nIch helfe gern. " . str_repeat('y', 200),
            'widgetGreeting' => "  Hallo!\nIch bin Emma. ",
            'widgetQuestions' => ['Eins?', '', " Zwei?\nnoch Zwei ", 'Eins?', 'Drei?', 'Vier?', 'Fünf?', 'Sechs?', 'Sieben?'],
            'widgetNotice' => ' Hinweis ',
        ]);

        self::assertStringStartsWith('Emma vom See x', $settings->name);
        self::assertSame(AssistantSettings::MAX_NAME_CHARS, mb_strlen($settings->name));
        self::assertTrue($settings->isWidgetActive());
        self::assertStringStartsWith('Fragen? Ich helfe gern. y', $settings->widgetHint);
        self::assertSame(AssistantSettings::MAX_HINT_CHARS, mb_strlen($settings->widgetHint));
        self::assertSame("Hallo!\nIch bin Emma.", $settings->widgetGreeting);
        // einzelne Felder: ohne leere und doppelte, Zeilenumbrüche trennen keine Fragen, höchstens sechs
        self::assertSame(['Eins?', 'Zwei? noch Zwei', 'Drei?', 'Vier?', 'Fünf?', 'Sechs?'], $settings->getWidgetQuestions());
        self::assertSame('Hinweis', $settings->widgetNotice);
        self::assertEquals($settings, AssistantSettings::fromArray($settings->toArray()));

        // als Text: eine Frage pro Zeile
        self::assertSame(['A?', 'B?'], AssistantSettings::questionLines("A?\r\n\n B? \nA?"));
        self::assertSame([], AssistantSettings::questionLines([]));
        $off = AssistantSettings::fromArray(['widgetEnabled' => 1]);
        self::assertFalse($off->isWidgetActive(), 'nur mit eingeschaltetem Assistenten');
        self::assertSame('', (new AssistantSettings())->name);
    }
}
