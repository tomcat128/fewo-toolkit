<?php

namespace FewoTk2\Tests\Unit;

use DateTimeImmutable;
use FewoTk2\Inquiry\Inquiry;
use FewoTk2\Inquiry\InquiryMailer;
use FewoTk2\Inquiry\InquiryValidator;
use PHPUnit\Framework\TestCase;

final class InquiryValidatorTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2027-03-01');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'salutation' => 'Ms.',
            'firstName' => ' Erika ',
            'lastName' => 'Mustermann',
            'email' => 'erika@example.org',
            'phone' => '+49 (7531) 12 34-56',
            'arrival' => '2027-07-10',
            'departure' => '2027-07-17',
            'adults' => '2',
            'children' => '1',
            'accommodation' => 'Seeherz',
            'referralSource' => 'Empfehlung',
            'street' => 'Seestraße 5',
            'postalCode' => '78462',
            'city' => 'Konstanz',
            'country' => 'Deutschland',
            'message' => "Wir kommen mit Hund.\nIst das möglich?",
            'consent' => '1',
        ];
    }

    private function validator(bool $askForAddress = true, bool $requireConsent = true): InquiryValidator
    {
        return new InquiryValidator(4, ['Seeherz', 'Seeherzchen'], ['Suchmaschine', 'Empfehlung'], $askForAddress, $requireConsent);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return string[] Namen der Felder mit Fehlern
     */
    private function invalidFields(array $overrides, ?InquiryValidator $validator = null): array
    {
        return array_keys(($validator ?? $this->validator())->validate($this->input($overrides), $this->today));
    }

    public function testValidInputHasNoErrors(): void
    {
        self::assertSame([], $this->validator()->validate($this->input(), $this->today));
    }

    public function testOptionalFieldsMayBeEmpty(): void
    {
        self::assertSame([], $this->invalidFields([
            'salutation' => '', 'firstName' => '', 'phone' => '', 'children' => '', 'referralSource' => '',
            'street' => '', 'postalCode' => '', 'city' => '', 'country' => '', 'message' => '',
        ]));
    }

    public function testRequiredFields(): void
    {
        self::assertSame(
            ['lastName', 'email', 'arrival', 'departure', 'adults', 'accommodation', 'consent'],
            array_keys($this->validator()->validate([], $this->today))
        );
        // Nur Leerzeichen zählen nicht als Eingabe
        self::assertSame(['lastName'], $this->invalidFields(['lastName' => '   ']));
    }

    public function testEmail(): void
    {
        self::assertSame(['email'], $this->invalidFields(['email' => 'erika@']));
        self::assertSame(['email'], $this->invalidFields(['email' => 'erika example.org']));
        // Versuch, zusätzliche Mail-Kopfzeilen einzuschleusen
        self::assertSame(['email'], $this->invalidFields(['email' => "erika@example.org\r\nBcc: alle@example.org"]));
        self::assertSame([], $this->invalidFields(['email' => 'erika.mustermann+urlaub@sub.example.org']));
    }

    public function testDates(): void
    {
        self::assertSame(['arrival'], $this->invalidFields(['arrival' => '10.07.2027']));
        self::assertSame(['arrival'], $this->invalidFields(['arrival' => '2027-02-30', 'departure' => '2027-03-05']));
        self::assertSame(['arrival'], $this->invalidFields(['arrival' => '2027-02-28', 'departure' => '2027-03-05']), 'Anreise in der Vergangenheit');
        self::assertSame([], $this->invalidFields(['arrival' => '2027-03-01', 'departure' => '2027-03-02']), 'Anreise heute ist erlaubt');
        self::assertSame(['departure'], $this->invalidFields(['departure' => '2027-07-10']), 'Abreise am Anreisetag');
        self::assertSame(['departure'], $this->invalidFields(['departure' => '2027-07-01']));
        self::assertSame(['departure'], $this->invalidFields(['departure' => 'morgen']));
    }

    public function testGuests(): void
    {
        self::assertSame(['adults'], $this->invalidFields(['adults' => '0']));
        self::assertSame(['adults'], $this->invalidFields(['adults' => 'zwei']));
        self::assertSame([], $this->invalidFields(['adults' => '4', 'children' => '0']));
        self::assertSame(['adults'], $this->invalidFields(['adults' => '3', 'children' => '2']), 'mehr Gäste als erlaubt');
        self::assertSame(['adults'], $this->invalidFields(['adults' => '2', 'children' => '-1']));
        self::assertStringContainsString('4', $this->validator()->validate($this->input(['adults' => '5']), $this->today)['adults']);
    }

    public function testChoicesMustComeFromTheConfiguredLists(): void
    {
        self::assertSame(['accommodation'], $this->invalidFields(['accommodation' => 'Schloss Neuschwanstein']));
        self::assertSame(['accommodation'], $this->invalidFields(['accommodation' => '']));
        self::assertSame(['referralSource'], $this->invalidFields(['referralSource' => 'Zufall']));

        // Ohne konfigurierte Unterkünfte gibt es das Feld nicht
        $validator = new InquiryValidator(4);
        self::assertSame([], array_keys($validator->validate($this->input(['accommodation' => '', 'referralSource' => '']), $this->today)));
    }

    public function testPhoneAndPostalCode(): void
    {
        self::assertSame(['phone'], $this->invalidFields(['phone' => 'bitte per Mail']));
        self::assertSame(['phone'], $this->invalidFields(['phone' => '123']));
        self::assertSame([], $this->invalidFields(['phone' => '0049/7531/123456']));
        self::assertSame(['postalCode'], $this->invalidFields(['postalCode' => str_repeat('9', 17)]));
        // Ohne Adressfelder wird die Postleitzahl nicht geprüft
        self::assertSame([], $this->invalidFields(['postalCode' => str_repeat('9', 17)], $this->validator(false)));
    }

    public function testMessage(): void
    {
        self::assertSame(['message'], $this->invalidFields(['message' => 'Schauen Sie: https://example.org/angebot']));
        self::assertSame(['message'], $this->invalidFields(['message' => 'Besuchen Sie WWW.example.org']));
        self::assertSame(['message'], $this->invalidFields(['lastName' => 'http://spam.example']), 'Links im Namen');
        self::assertSame([], $this->invalidFields(['message' => str_repeat('ä', 5000)]), 'Umlaute zählen als ein Zeichen');
        self::assertSame(['message'], $this->invalidFields(['message' => str_repeat('ä', 5001)]));
    }

    public function testConsent(): void
    {
        self::assertSame(['consent'], $this->invalidFields(['consent' => '']));
        self::assertSame(['consent'], $this->invalidFields(['consent' => '0']));
        self::assertSame([], $this->invalidFields(['consent' => ''], $this->validator(true, false)));
    }

    public function testFillTransfersTrimmedValues(): void
    {
        $inquiry = $this->validator()->fill(new Inquiry(), $this->input(['children' => '-3']));

        self::assertSame('Ms.', $inquiry->getSalutation());
        self::assertSame('Erika', $inquiry->getFirstName());
        self::assertSame('Erika Mustermann', $inquiry->getFullName());
        self::assertSame('erika@example.org', $inquiry->getEmail());
        self::assertSame('2027-07-10', $inquiry->getArrival()->format('Y-m-d'));
        self::assertSame('2027-07-17', $inquiry->getDeparture()->format('Y-m-d'));
        self::assertSame(7, $inquiry->getNights());
        self::assertSame(2, $inquiry->getAdults());
        self::assertSame(0, $inquiry->getChildren());
        self::assertSame(2, $inquiry->getGuests());
        self::assertSame('Seeherz', $inquiry->getAccommodation());
        self::assertSame('78462', $inquiry->getPostalCode());
        self::assertSame("Wir kommen mit Hund.\nIst das möglich?", $inquiry->getMessage());
    }

    public function testFillIgnoresUnknownSalutationAndHiddenFields(): void
    {
        $inquiry = (new InquiryValidator(4, [], [], false))->fill(new Inquiry(), $this->input(['salutation' => 'Eure Majestät']));

        self::assertSame('', $inquiry->getSalutation());
        self::assertSame('', $inquiry->getAccommodation(), 'Unterkunft ohne Auswahlliste');
        self::assertSame('', $inquiry->getStreet(), 'Adresse wird nicht abgefragt');
        self::assertSame('', $inquiry->getCity());
    }

    public function testSingleLineFieldsCannotCarryLineBreaks(): void
    {
        // Ein manipuliertes Formular darf über den Namen keine Mail-Kopfzeilen einschleusen
        $inquiry = $this->validator()->fill(new Inquiry(), $this->input([
            'firstName' => "Erika\r\nBcc: alle@example.org",
            'lastName' => "Muster\tmann\n",
            'city' => "Konstanz\0",
        ]));

        self::assertSame('Erika Bcc: alle@example.org Muster mann', $inquiry->getFullName());
        self::assertSame('Konstanz', $inquiry->getCity());
        self::assertSame("Wir kommen mit Hund.\nIst das möglich?", $inquiry->getMessage(), 'Die Nachricht bleibt mehrzeilig');
    }

    public function testLines(): void
    {
        self::assertSame(['Seeherz', 'Seeherzchen'], InquiryValidator::lines("Seeherz\r\n\r\n  Seeherzchen \nSeeherz\n"));
        self::assertSame([], InquiryValidator::lines(" \n\n"));
        self::assertSame(['0'], InquiryValidator::lines('0'));
    }

    public function testMailTextRowsAreAlignedByCharacters(): void
    {
        $text = InquiryMailer::textRows([
            'Nächte' => '5',
            'Unverbindlicher Preis' => '490,00 €',
            'Wie sind Sie auf uns aufmerksam geworden?' => 'Empfehlung',
        ]);

        self::assertSame(
            "Nächte:                 5\n"
            . "Unverbindlicher Preis:  490,00 €\n"
            . "Wie sind Sie auf uns aufmerksam geworden?: Empfehlung\n",
            $text
        );
    }
}
