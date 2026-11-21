<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Pricing\PricelistLoader;

/**
 * Preise aller Wohnungen auf einen Blick: Saisons mit Preisen je Gästezahl, Mindestbelegung, Mindestdauer,
 * Endreinigung und Anzahlung – so, wie Fewo-Office sie liefert und wie die Blöcke und der Assistent sie verwenden.
 * Gepflegt werden die Preise in Fewo-Office; hier lassen sie sich nur ansehen und neu abrufen.
 */
class Prices extends DashboardPageController
{
    public function view()
    {
        $loader = $this->app->make(PricelistLoader::class);
        $rows = [];
        foreach ($this->app->make(ApartmentRepository::class)->findAll() as $apartment) {
            // Wie auf der Website: abgerufen wird nur, wenn der gespeicherte Stand älter als das Intervall der Wohnung ist
            $rows[] = [
                'apartment' => $apartment,
                'loaded' => $apartment->getPricelistUrl() !== '' ? $loader->load($apartment->getPricelistUrl(), $apartment->getRefreshMinutes()) : null,
            ];
        }
        $this->set('rows', $rows);
    }

    /**
     * "Jetzt aktualisieren": die Preisliste einer Wohnung – ohne $id die aller Wohnungen – sofort neu abrufen.
     */
    public function refresh($id = null)
    {
        if (!$this->token->validate('fewo_prices_refresh')) {
            return $this->buildRedirect($this->action('view'));
        }
        $repository = $this->app->make(ApartmentRepository::class);
        $apartments = $id !== null ? array_filter([$repository->find((int) $id)]) : $repository->findAll();
        $loader = $this->app->make(PricelistLoader::class);
        $updated = 0;
        $problems = [];
        foreach ($apartments as $apartment) {
            if ($apartment->getPricelistUrl() === '') {
                continue;
            }
            $feed = $loader->load($apartment->getPricelistUrl(), $apartment->getRefreshMinutes(), true)->feed;
            if ($feed !== null && $feed->getLastError()) {
                $problems[] = t('"%1$s": %2$s', $apartment->getName(), $feed->getLastError());
            } else {
                $updated++;
            }
        }

        if ($problems !== []) {
            $this->flash('error', t('The prices could not be loaded from Fewo-Office. %s', implode(' ', $problems)));
        } elseif ($updated > 0) {
            $this->flash('success', t2('The prices of %s apartment have been reloaded from Fewo-Office.', 'The prices of %s apartments have been reloaded from Fewo-Office.', $updated));
        }

        return $this->buildRedirect($this->action('view'));
    }
}
