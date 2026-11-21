<?php

namespace FewoTk2\Inquiry;

use DateTimeImmutable;

/**
 * Prüft und normalisiert die Eingaben des Anfrageformulars.
 */
class InquiryValidator
{
    /**
     * @param string[] $accommodations erlaubte Unterkünfte (leer = Feld wird nicht angezeigt)
     * @param string[] $referralOptions erlaubte Antworten auf "Wie haben Sie uns gefunden?"
     */
    public function __construct(
        private readonly int $maxGuests,
        private readonly array $accommodations = [],
        private readonly array $referralOptions = [],
        private readonly bool $askForAddress = true,
        private readonly bool $requireConsent = true,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, string> Feldname => Fehlermeldung (leer = gültig)
     */
    public function validate(array $input, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $errors = [];
        $value = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        if ($value('lastName') === '') {
            $errors['lastName'] = t('Please enter your last name.');
        }
        if ($value('email') === '') {
            $errors['email'] = t('Please enter your email address.');
        } elseif (filter_var($value('email'), FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = t('Please enter a valid email address.');
        }

        $arrival = $this->date($value('arrival'));
        $departure = $this->date($value('departure'));
        if ($arrival === null) {
            $errors['arrival'] = t('Please enter your arrival date.');
        } elseif ($arrival < $today) {
            $errors['arrival'] = t('The arrival date is in the past.');
        }
        if ($departure === null) {
            $errors['departure'] = t('Please enter your departure date.');
        } elseif ($arrival !== null && $departure <= $arrival) {
            $errors['departure'] = t('The departure date must be after the arrival date.');
        }

        $adults = (int) $value('adults');
        $children = (int) $value('children');
        if ($adults < 1) {
            $errors['adults'] = t('Please enter the number of adults.');
        } elseif ($children < 0 || $adults + $children > $this->maxGuests) {
            $errors['adults'] = t('A maximum of %s guests can stay.', $this->maxGuests);
        }

        if ($this->accommodations !== [] && !in_array($value('accommodation'), $this->accommodations, true)) {
            $errors['accommodation'] = t('Please choose an accommodation.');
        }
        if ($value('referralSource') !== '' && !in_array($value('referralSource'), $this->referralOptions, true)) {
            $errors['referralSource'] = t('Please choose an option from the list.');
        }
        if ($this->askForAddress && mb_strlen($value('postalCode')) > 16) {
            $errors['postalCode'] = t('The postal code is too long.');
        }
        if ($value('phone') !== '' && !preg_match('/^[0-9 +()\/.\-]{4,40}$/', $value('phone'))) {
            $errors['phone'] = t('Please enter a valid phone number.');
        }
        if (preg_match('~https?://|www\.~i', $value('message') . ' ' . $value('firstName') . ' ' . $value('lastName'))) {
            $errors['message'] = t('For security reasons, your message must not contain links.');
        }
        if (mb_strlen($value('message')) > 5000) {
            $errors['message'] = t('Your message is too long.');
        }
        if ($this->requireConsent && empty($input['consent'])) {
            $errors['consent'] = t('Please agree to the processing of your data.');
        }

        return $errors;
    }

    /**
     * Überträgt gültige Eingaben in eine Anfrage.
     *
     * @param array<string, mixed> $input
     */
    public function fill(Inquiry $inquiry, array $input): Inquiry
    {
        $value = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        $inquiry
            ->setSalutation(in_array($value('salutation'), self::salutations(), true) ? $value('salutation') : '')
            ->setFirstName($value('firstName'))
            ->setLastName($value('lastName'))
            ->setEmail($value('email'))
            ->setPhone($value('phone'))
            ->setAdults((int) $value('adults'))
            ->setChildren(max(0, (int) $value('children')))
            ->setArrival($this->date($value('arrival')) ?? new DateTimeImmutable('today'))
            ->setDeparture($this->date($value('departure')) ?? new DateTimeImmutable('tomorrow'))
            ->setAccommodation($this->accommodations !== [] ? $value('accommodation') : '')
            ->setReferralSource($value('referralSource'))
            ->setMessage($value('message'));

        if ($this->askForAddress) {
            $inquiry
                ->setStreet($value('street'))
                ->setPostalCode($value('postalCode'))
                ->setCity($value('city'))
                ->setCountry($value('country'));
        }

        return $inquiry;
    }

    /**
     * @return string[]
     */
    public static function salutations(): array
    {
        return [t('Ms.'), t('Mr.'), t('Mx.')];
    }

    /**
     * Zerlegt ein Textfeld mit einer Option pro Zeile.
     *
     * @return string[]
     */
    public static function lines(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), 'strlen')));
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
