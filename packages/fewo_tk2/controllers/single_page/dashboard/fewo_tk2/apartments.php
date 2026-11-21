<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Calendar\OccupancyLoader;
use FewoTk2\Entity\Apartment;
use FewoTk2\Feed\FeedLoader;
use FewoTk2\Feed\InvalidFeedException;
use FewoTk2\Pricing\PricelistLoader;
use FewoTk2\Pricing\PricelistReader;

/**
 * Wohnungen mit ihren Adressen in Fewo-Office. Die Blöcke wählen nur noch die Wohnung aus.
 */
class Apartments extends DashboardPageController
{
    public function view()
    {
        $feedLoader = $this->app->make(FeedLoader::class);
        $rows = [];
        foreach ($this->getRepository()->findAll() as $apartment) {
            // Nur der zwischengespeicherte Stand: Die Übersicht ruft Fewo-Office nicht selbst ab.
            $occupancyFeed = $apartment->getOccupancyUrl() !== '' ? $feedLoader->getFeed(FeedLoader::OCCUPANCY, $apartment->getOccupancyUrl()) : null;
            $pricelistFeed = $apartment->getPricelistUrl() !== '' ? $feedLoader->getFeed(FeedLoader::PRICELIST, $apartment->getPricelistUrl()) : null;
            $rows[] = [
                'apartment' => $apartment,
                'occupancyFeed' => $occupancyFeed,
                'pricelistFeed' => $pricelistFeed,
                'seasonCount' => $this->countSeasons($pricelistFeed?->getBody()),
            ];
        }
        $this->set('rows', $rows);
    }

    public function add()
    {
        $this->renderForm(new Apartment());
    }

    public function edit($id = null)
    {
        $apartment = $this->getRepository()->find((int) $id);
        if ($apartment === null) {
            $this->flash('error', t('The apartment was not found.'));

            return $this->buildRedirect($this->action('view'));
        }
        $this->renderForm($apartment);
    }

    public function save($id = null)
    {
        if (!$this->token->validate('fewo_apartment_save')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $repository = $this->getRepository();
        $apartment = $id ? $repository->find((int) $id) : new Apartment();
        if ($apartment === null) {
            return $this->buildRedirect($this->action('view'));
        }

        $post = $this->request->request;
        $values = [
            'name' => trim((string) $post->get('name', '')),
            'occupancyUrl' => trim((string) $post->get('occupancyUrl', '')),
            'pricelistUrl' => trim((string) $post->get('pricelistUrl', '')),
            'refreshMinutes' => (int) $post->get('refreshMinutes', FeedLoader::DEFAULT_REFRESH_MINUTES),
        ];
        $occupancyUrl = FeedLoader::normalizeUrl($values['occupancyUrl']);
        $pricelistUrl = FeedLoader::normalizeUrl($values['pricelistUrl']);

        if ($values['name'] === '') {
            $this->error->add(t('Please enter a name.'));
        }
        if ($values['occupancyUrl'] !== '' && $occupancyUrl === null) {
            $this->error->add(t('Please enter a valid occupancy URL (https://…).'));
        }
        if ($values['pricelistUrl'] !== '' && $pricelistUrl === null) {
            $this->error->add(t('Please enter a valid price list URL (https://…).'));
        }
        if ($values['occupancyUrl'] === '' && $values['pricelistUrl'] === '') {
            $this->error->add(t('Please enter at least one of the two URLs.'));
        }

        if ($this->error->has()) {
            // Formular mit den eingegebenen Werten erneut anzeigen, nichts speichern.
            $this->renderForm($apartment, $values);

            return null;
        }

        $apartment
            ->setName($values['name'])
            ->setOccupancyUrl((string) $occupancyUrl)
            ->setPricelistUrl((string) $pricelistUrl)
            ->setRefreshMinutes($values['refreshMinutes']);
        $repository->save($apartment);

        // Sofort abrufen, damit Tippfehler in den Adressen gleich auffallen.
        $this->flashRefreshResult($apartment, t('The apartment has been saved.'));

        return $this->buildRedirect($this->action('view'));
    }

    /**
     * "Jetzt aktualisieren": Belegung und Preisliste sofort neu aus Fewo-Office abrufen.
     */
    public function refresh($id = null)
    {
        $apartment = $this->getRepository()->find((int) $id);
        if ($apartment !== null && $this->token->validate('fewo_apartment_refresh')) {
            $this->flashRefreshResult($apartment, t('The data of "%s" has been reloaded from Fewo-Office.', $apartment->getName()));
        }

        return $this->buildRedirect($this->action('view'));
    }

    public function delete($id = null)
    {
        $apartment = $this->getRepository()->find((int) $id);
        if ($apartment !== null && $this->token->validate('fewo_apartment_delete')) {
            $this->getRepository()->delete($apartment);
            $this->flash('success', t('The apartment has been deleted.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    /**
     * Ruft beide Adressen neu ab und meldet Erfolg oder die Gründe für einen Fehlschlag.
     */
    private function flashRefreshResult(Apartment $apartment, string $successMessage): void
    {
        $problems = [];
        if ($apartment->getOccupancyUrl() !== '') {
            $feed = $this->app->make(OccupancyLoader::class)->load($apartment->getOccupancyUrl(), $apartment->getRefreshMinutes(), true);
            if ($feed->getLastError()) {
                $problems[] = t('Occupancy: %s', $feed->getLastError());
            }
        }
        if ($apartment->getPricelistUrl() !== '') {
            $feed = $this->app->make(PricelistLoader::class)->load($apartment->getPricelistUrl(), $apartment->getRefreshMinutes(), true)->feed;
            if ($feed !== null && $feed->getLastError()) {
                $problems[] = t('Price list: %s', $feed->getLastError());
            }
        }

        if ($problems === []) {
            $this->flash('success', $successMessage);
        } else {
            $this->flash('error', t('"%1$s": The data could not be loaded from Fewo-Office. %2$s', $apartment->getName(), implode(' ', $problems)));
        }
    }

    private function countSeasons(?string $body): ?int
    {
        if ($body === null) {
            return null;
        }
        try {
            return count($this->app->make(PricelistReader::class)->read($body)->getSeasons());
        } catch (InvalidFeedException) {
            return null;
        }
    }

    /**
     * @param array{name: string, occupancyUrl: string, pricelistUrl: string, refreshMinutes: int}|null $values Eingaben nach einem Fehler
     */
    private function renderForm(Apartment $apartment, ?array $values = null): void
    {
        $refreshOptions = [];
        foreach (FeedLoader::REFRESH_OPTIONS as $minutes) {
            $refreshOptions[$minutes] = $minutes < 60
                ? t2('%s minute', '%s minutes', $minutes)
                : t2('%s hour', '%s hours', intdiv($minutes, 60));
        }
        $this->set('apartment', $apartment);
        $this->set('values', $values ?? [
            'name' => $apartment->getName(),
            'occupancyUrl' => $apartment->getOccupancyUrl(),
            'pricelistUrl' => $apartment->getPricelistUrl(),
            'refreshMinutes' => $apartment->getRefreshMinutes(),
        ]);
        $this->set('refreshOptions', $refreshOptions);
        $this->render('/dashboard/fewo_tk2/apartments/edit', 'fewo_tk2');
    }

    private function getRepository(): ApartmentRepository
    {
        return $this->app->make(ApartmentRepository::class);
    }
}
