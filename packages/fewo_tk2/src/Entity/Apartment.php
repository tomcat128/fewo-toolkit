<?php

namespace FewoTk2\Entity;

use Doctrine\ORM\Mapping as ORM;
use FewoTk2\Feed\FeedLoader;

/**
 * Eine Ferienwohnung mit ihren Adressen in Fewo-Office.
 *
 * Die Adressen werden einmal im Dashboard hinterlegt; die Blöcke wählen nur
 * noch die Wohnung aus. Belegung, Preise und Neuigkeiten selbst werden nicht gespeichert,
 * sondern aus Fewo-Office abgerufen (siehe RemoteFeed).
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoApartments")
 */
class Apartment
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
     * Adresse der Belegung, z. B. https://fewo-office.com/api/wohnungen/{kürzel}/belegung.json (leer = keine).
     *
     * @ORM\Column(type="text")
     */
    protected string $occupancyUrl = '';

    /**
     * Adresse der Preisliste, z. B. https://fewo-office.com/api/wohnungen/{kürzel}/preise.json (leer = keine).
     *
     * @ORM\Column(type="text")
     */
    protected string $pricelistUrl = '';

    /**
     * Adresse der Neuigkeiten, z. B. https://fewo-office.com/api/neuigkeiten/{kürzel}.json (leer = keine).
     *
     * @ORM\Column(type="text")
     */
    protected string $newsUrl = '';

    /**
     * Nach so vielen Minuten werden die Daten beim nächsten Seitenaufruf neu abgerufen.
     *
     * @ORM\Column(type="smallint", options={"unsigned": true})
     */
    protected int $refreshMinutes = FeedLoader::DEFAULT_REFRESH_MINUTES;

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
        $this->name = mb_substr(trim($name), 0, 255);

        return $this;
    }

    public function getOccupancyUrl(): string
    {
        return $this->occupancyUrl;
    }

    public function setOccupancyUrl(string $url): self
    {
        $this->occupancyUrl = trim($url);

        return $this;
    }

    public function getPricelistUrl(): string
    {
        return $this->pricelistUrl;
    }

    public function setPricelistUrl(string $url): self
    {
        $this->pricelistUrl = trim($url);

        return $this;
    }

    public function getNewsUrl(): string
    {
        return $this->newsUrl;
    }

    public function setNewsUrl(string $url): self
    {
        $this->newsUrl = trim($url);

        return $this;
    }

    public function getRefreshMinutes(): int
    {
        return $this->refreshMinutes;
    }

    public function setRefreshMinutes(int $minutes): self
    {
        $this->refreshMinutes = FeedLoader::sanitizeRefreshMinutes($minutes);

        return $this;
    }
}
