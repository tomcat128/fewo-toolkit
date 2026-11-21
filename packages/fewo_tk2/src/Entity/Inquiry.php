<?php

namespace FewoTk2\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Eine über das Anfrageformular eingegangene Buchungsanfrage.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoInquiries", indexes={@ORM\Index(name="created", columns={"createdAt"})})
 */
class Inquiry
{
    public const STATUS_NEW = 'new';
    public const STATUS_DONE = 'done';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    protected DateTimeImmutable $createdAt;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected string $status = self::STATUS_NEW;

    /**
     * Name des Formulars (aus der Block-Konfiguration).
     *
     * @ORM\Column(type="string", length=255)
     */
    protected string $formName = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $accommodation = '';

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected string $salutation = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $firstName = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $lastName = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $email = '';

    /**
     * @ORM\Column(type="string", length=64)
     */
    protected string $phone = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $street = '';

    /**
     * @ORM\Column(type="string", length=16)
     */
    protected string $postalCode = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $city = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $country = '';

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $adults = 1;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $children = 0;

    /**
     * @ORM\Column(type="date_immutable")
     */
    protected DateTimeImmutable $arrival;

    /**
     * @ORM\Column(type="date_immutable")
     */
    protected DateTimeImmutable $departure;

    /**
     * @ORM\Column(type="text")
     */
    protected string $message = '';

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $referralSource = '';

    /**
     * Unverbindlich berechneter Gesamtpreis in Cent (falls eine Preisliste verknüpft war).
     *
     * @ORM\Column(type="integer", nullable=true, options={"unsigned": true})
     */
    protected ?int $quotedTotal = null;

    /**
     * @ORM\Column(type="string", length=3)
     */
    protected string $currency = 'EUR';

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->arrival = new DateTimeImmutable('today');
        $this->departure = new DateTimeImmutable('tomorrow');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status === self::STATUS_DONE ? self::STATUS_DONE : self::STATUS_NEW;

        return $this;
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
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

    public function getQuotedTotal(): ?int
    {
        return $this->quotedTotal;
    }

    public function setQuotedTotal(?int $cents): self
    {
        $this->quotedTotal = $cents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(substr($currency, 0, 3)) ?: 'EUR';

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
