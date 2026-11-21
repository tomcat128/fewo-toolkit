<?php

namespace FewoTk2\Block;

use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Support\Facade\Url;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Entity\Apartment;
use FewoTk2\Pricing\LoadedPricelist;
use FewoTk2\Pricing\PricelistLoader;

/**
 * Gemeinsames für Blöcke, die Daten einer Wohnung aus Fewo-Office anzeigen.
 *
 * Der Block speichert nur die Nummer der Wohnung ($apartmentID); die Adressen
 * in Fewo-Office stehen im Dashboard unter Fewo Toolkit › Wohnungen.
 */
trait ApartmentBlock
{
    private Apartment|false|null $selectedApartment = false;

    /**
     * Die im Block gewählte Wohnung – null, wenn keine gewählt ist oder sie gelöscht wurde.
     */
    protected function getApartment(): ?Apartment
    {
        if ($this->selectedApartment === false) {
            $this->selectedApartment = $this->app->make(ApartmentRepository::class)->find((int) $this->apartmentID);
        }

        return $this->selectedApartment;
    }

    /**
     * Preisliste der gewählten Wohnung.
     */
    protected function loadPricelist(): LoadedPricelist
    {
        $apartment = $this->getApartment();
        if ($apartment === null) {
            return new LoadedPricelist(null, null, $this->getMissingApartmentMessage());
        }

        return $this->app->make(PricelistLoader::class)->load($apartment->getPricelistUrl(), $apartment->getRefreshMinutes());
    }

    protected function getMissingApartmentMessage(): string
    {
        return t('No apartment has been selected or it no longer exists.');
    }

    /**
     * Übergibt dem Block-Formular die Auswahlliste der Wohnungen und den Link zum Dashboard.
     */
    protected function setApartmentOptions(string $emptyLabel): void
    {
        $this->set('apartmentOptions', $this->app->make(ApartmentRepository::class)->getSelectOptions($emptyLabel));
        $this->set('apartmentsUrl', (string) Url::to('/dashboard/fewo_tk2/apartments'));
    }

    /**
     * Hinweise zum Datenabruf sehen nur Personen, die die Seite bearbeiten dürfen.
     */
    protected function canEditPage(): bool
    {
        $page = Page::getCurrentPage();

        return $page instanceof Page && !$page->isError() && (new Checker($page))->canEditPageContents();
    }
}
