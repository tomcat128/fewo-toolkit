<?php

namespace FewoToolkit\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ein Saisonzeitraum einer Preisliste mit Preisen pro Nacht je Gästeanzahl.
 *
 * Der Zeitraum ist inklusive: Jede Nacht, die an einem Tag zwischen dateFrom
 * und dateTo beginnt (bzw. je nach Berechnungsmodus endet), gehört zur Saison.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoPricelistSeasons", indexes={@ORM\Index(name="pricelist_dates", columns={"pricelist_id", "dateFrom", "dateTo"})})
 */
class Season
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Pricelist", inversedBy="seasons")
     * @ORM\JoinColumn(name="pricelist_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected ?Pricelist $pricelist = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $name = '';

    /**
     * @ORM\Column(type="date_immutable")
     */
    protected DateTimeImmutable $dateFrom;

    /**
     * @ORM\Column(type="date_immutable")
     */
    protected DateTimeImmutable $dateTo;

    /**
     * Abweichender Mindestaufenthalt in dieser Saison (null = Wert der Preisliste).
     *
     * @ORM\Column(type="smallint", nullable=true, options={"unsigned": true})
     */
    protected ?int $minNights = null;

    /**
     * Preis pro Nacht in Cent je Gästeanzahl, z. B. {"1": 7500, "2": 8000}.
     *
     * @ORM\Column(type="json")
     *
     * @var array<string, int>
     */
    protected array $prices = [];

    public function __construct(string $name = '', ?DateTimeImmutable $dateFrom = null, ?DateTimeImmutable $dateTo = null)
    {
        $this->name = trim($name);
        $this->dateFrom = ($dateFrom ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $this->dateTo = ($dateTo ?? $this->dateFrom)->setTime(0, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPricelist(): ?Pricelist
    {
        return $this->pricelist;
    }

    public function setPricelist(?Pricelist $pricelist): self
    {
        $this->pricelist = $pricelist;

        return $this;
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

    public function getDateFrom(): DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function setDateFrom(DateTimeImmutable $dateFrom): self
    {
        $this->dateFrom = $dateFrom->setTime(0, 0);

        return $this;
    }

    public function getDateTo(): DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function setDateTo(DateTimeImmutable $dateTo): self
    {
        $this->dateTo = $dateTo->setTime(0, 0);

        return $this;
    }

    public function covers(DateTimeImmutable $day): bool
    {
        $day = $day->setTime(0, 0);

        return $day >= $this->dateFrom && $day <= $this->dateTo;
    }

    public function getMinNights(): ?int
    {
        return $this->minNights;
    }

    public function setMinNights(?int $minNights): self
    {
        $this->minNights = $minNights !== null && $minNights > 0 ? $minNights : null;

        return $this;
    }

    /**
     * @return array<int, int> Gästeanzahl => Cent, aufsteigend sortiert
     */
    public function getPrices(): array
    {
        $prices = [];
        foreach ($this->prices as $guests => $cents) {
            $prices[(int) $guests] = (int) $cents;
        }
        ksort($prices);

        return $prices;
    }

    /**
     * @param array<int|string, int|null> $prices Gästeanzahl => Cent; leere Werte werden entfernt
     */
    public function setPrices(array $prices): self
    {
        $clean = [];
        foreach ($prices as $guests => $cents) {
            if ($cents === null || (int) $guests < 1 || (int) $cents <= 0) {
                continue;
            }
            $clean[(string) (int) $guests] = (int) $cents;
        }
        ksort($clean, SORT_NUMERIC);
        $this->prices = $clean;

        return $this;
    }

    /**
     * Preis pro Nacht für die angegebene Gästeanzahl.
     *
     * Ist für genau diese Anzahl kein Preis hinterlegt, gilt der Preis der
     * nächstgrößeren hinterlegten Anzahl (z. B. "bis 4 Personen").
     */
    public function getPriceForGuests(int $guests): ?int
    {
        foreach ($this->getPrices() as $count => $cents) {
            if ($count >= $guests) {
                return $cents;
            }
        }

        return null;
    }

    /**
     * Fasst Gästeanzahlen mit gleichem Preis zu Bereichen zusammen.
     *
     * Berücksichtigt die Regel aus getPriceForGuests(): Bei {2: 75 €, 4: 90 €}
     * gilt 75 € für 1–2 Gäste und 90 € für 3–4 Gäste.
     *
     * @return array<int, array{min: int, max: int, price: int}>
     */
    public function getPriceRanges(): array
    {
        $ranges = [];
        $previous = 0;
        foreach ($this->getPrices() as $guests => $cents) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last]['price'] === $cents) {
                $ranges[$last]['max'] = $guests;
            } else {
                $ranges[] = ['min' => $previous + 1, 'max' => $guests, 'price' => $cents];
            }
            $previous = $guests;
        }

        return $ranges;
    }
}
