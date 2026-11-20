<?php

namespace FewoToolkit\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Eine Preisliste (typischerweise eine pro Ferienwohnung).
 *
 * Alle Geldbeträge werden als Integer in Cent gespeichert.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoPricelists")
 * @ORM\HasLifecycleCallbacks
 */
class Pricelist
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $name = '';

    /**
     * Maximale Anzahl Gäste.
     *
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $maxGuests = 2;

    /**
     * Mindestaufenthalt in Nächten (kann pro Saison überschrieben werden).
     *
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $minNights = 1;

    /**
     * Endreinigung in Cent.
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected int $cleaningFee = 0;

    /**
     * Kaution in Cent (wird separat ausgewiesen, nicht im Gesamtpreis enthalten).
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected int $securityDeposit = 0;

    /**
     * Anzahlung in Prozent des Mietpreises.
     *
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $downPaymentPercent = 0;

    /**
     * Inklusivleistungen, z. B. "inkl. Bettwäsche, Handtücher und Nebenkosten".
     *
     * @ORM\Column(type="string", length=255)
     */
    protected string $includedServices = '';

    /**
     * Hinweistext unter der Preisübersicht.
     *
     * @ORM\Column(type="text")
     */
    protected string $note = '';

    /**
     * @ORM\Column(type="string", length=3)
     */
    protected string $currency = 'EUR';

    /**
     * @ORM\OneToMany(targetEntity="Season", mappedBy="pricelist", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"dateFrom": "ASC"})
     *
     * @var Collection<int, Season>
     */
    protected Collection $seasons;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    protected DateTimeImmutable $createdAt;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    protected DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->seasons = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @ORM\PreUpdate
     */
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getMaxGuests(): int
    {
        return $this->maxGuests;
    }

    public function setMaxGuests(int $maxGuests): self
    {
        $this->maxGuests = max(1, $maxGuests);

        return $this;
    }

    public function getMinNights(): int
    {
        return $this->minNights;
    }

    public function setMinNights(int $minNights): self
    {
        $this->minNights = max(1, $minNights);

        return $this;
    }

    public function getCleaningFee(): int
    {
        return $this->cleaningFee;
    }

    public function setCleaningFee(int $cents): self
    {
        $this->cleaningFee = max(0, $cents);

        return $this;
    }

    public function getSecurityDeposit(): int
    {
        return $this->securityDeposit;
    }

    public function setSecurityDeposit(int $cents): self
    {
        $this->securityDeposit = max(0, $cents);

        return $this;
    }

    public function getDownPaymentPercent(): int
    {
        return $this->downPaymentPercent;
    }

    public function setDownPaymentPercent(int $percent): self
    {
        $this->downPaymentPercent = min(100, max(0, $percent));

        return $this;
    }

    public function getIncludedServices(): string
    {
        return $this->includedServices;
    }

    public function setIncludedServices(string $includedServices): self
    {
        $this->includedServices = trim($includedServices);

        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): self
    {
        $this->note = trim($note);

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(substr(trim($currency), 0, 3)) ?: 'EUR';

        return $this;
    }

    /**
     * @return Season[] sortiert nach Beginn
     */
    public function getSeasons(): array
    {
        $seasons = $this->seasons->toArray();
        usort($seasons, static fn (Season $a, Season $b) => $a->getDateFrom() <=> $b->getDateFrom());

        return $seasons;
    }

    /**
     * Saisons, die am angegebenen Tag noch nicht beendet sind.
     *
     * @return Season[]
     */
    public function getCurrentSeasons(?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);

        return array_values(array_filter(
            $this->getSeasons(),
            static fn (Season $season) => $season->getDateTo() >= $today
        ));
    }

    public function addSeason(Season $season): self
    {
        if (!$this->seasons->contains($season)) {
            $season->setPricelist($this);
            $this->seasons->add($season);
        }

        return $this;
    }

    public function removeSeason(Season $season): self
    {
        $this->seasons->removeElement($season);

        return $this;
    }

    public function clearSeasons(): self
    {
        $this->seasons->clear();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
