<?php

namespace FewoTk2\Inquiry;

use DateTimeImmutable;

/**
 * Die Angaben aus dem Anfrageformular, geprüft und gekürzt, für die E-Mails an Vermieter und Gast.
 *
 * Die Website speichert keine Anfragen – die Angaben leben nur für die Dauer des Absendens.
 */
class Inquiry
{
    /**
     * Name des Formulars (aus der Block-Konfiguration).
     */
    protected string $formName = '';

    protected string $accommodation = '';

    protected string $salutation = '';

    protected string $firstName = '';

    protected string $lastName = '';

    protected string $email = '';

    protected string $phone = '';

    protected string $street = '';

    protected string $postalCode = '';

    protected string $city = '';

    protected string $country = '';

    protected int $adults = 1;

    protected int $children = 0;

    protected DateTimeImmutable $arrival;

    protected DateTimeImmutable $departure;

    protected string $message = '';

    protected string $referralSource = '';

    public function __construct()
    {
        $this->arrival = new DateTimeImmutable('today');
        $this->departure = new DateTimeImmutable('tomorrow');
    }

    public function getFormName(): string
    {
        return $this->formName;
    }

    public function setFormName(string $formName): self
    {
        $this->formName = self::line($formName, 255);

        return $this;
    }

    public function getAccommodation(): string
    {
        return $this->accommodation;
    }

    public function setAccommodation(string $accommodation): self
    {
        $this->accommodation = self::line($accommodation, 255);

        return $this;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): self
    {
        $this->salutation = self::line($salutation, 32);

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = self::line($firstName, 255);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = self::line($lastName, 255);

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = self::line($email, 255);

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = self::line($phone, 64);

        return $this;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): self
    {
        $this->street = self::line($street, 255);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = self::line($postalCode, 16);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = self::line($city, 255);

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = self::line($country, 255);

        return $this;
    }

    public function getAdults(): int
    {
        return $this->adults;
    }

    public function setAdults(int $adults): self
    {
        $this->adults = max(1, $adults);

        return $this;
    }

    public function getChildren(): int
    {
        return $this->children;
    }

    public function setChildren(int $children): self
    {
        $this->children = max(0, $children);

        return $this;
    }

    public function getGuests(): int
    {
        return $this->adults + $this->children;
    }

    public function getArrival(): DateTimeImmutable
    {
        return $this->arrival;
    }

    public function setArrival(DateTimeImmutable $arrival): self
    {
        $this->arrival = $arrival->setTime(0, 0);

        return $this;
    }

    public function getDeparture(): DateTimeImmutable
    {
        return $this->departure;
    }

    public function setDeparture(DateTimeImmutable $departure): self
    {
        $this->departure = $departure->setTime(0, 0);

        return $this;
    }

    public function getNights(): int
    {
        return (int) $this->arrival->diff($this->departure)->days;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = self::cut($message, 10000);

        return $this;
    }

    public function getReferralSource(): string
    {
        return $this->referralSource;
    }

    public function setReferralSource(string $referralSource): self
    {
        $this->referralSource = self::line($referralSource, 255);

        return $this;
    }

    private static function cut(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }

    /**
     * Einzeiliger Text: Zeilenumbrüche und Steuerzeichen werden zu einem Leerzeichen.
     *
     * Diese Werte landen in Betreff und Antwortadresse der E-Mails – dort dürfen
     * eingeschleuste Zeilenumbrüche keine zusätzlichen Kopfzeilen erzeugen.
     */
    private static function line(string $value, int $length): string
    {
        return self::cut((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value), $length);
    }
}
