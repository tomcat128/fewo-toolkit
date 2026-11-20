<?php

namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard\FewoToolkit;

use Concrete\Core\Page\Controller\DashboardPageController;
use DateTimeImmutable;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Entity\Season;
use FewoToolkit\Import\ImportException;
use FewoToolkit\Import\LegacyPricelistImporter;
use FewoToolkit\Pricing\PricelistRepository;
use FewoToolkit\Support\Money;

class Pricelists extends DashboardPageController
{
    private const MAX_GUESTS = 30;

    public function view()
    {
        $this->set('pricelists', $this->getRepository()->findAll());
        $this->set('today', new DateTimeImmutable('today'));
    }

    public function add()
    {
        $pricelist = (new Pricelist())->setMaxGuests(4)->setMinNights(3);
        $this->renderForm($pricelist);
    }

    public function edit($id = null)
    {
        $pricelist = $this->getRepository()->find((int) $id);
        if ($pricelist === null) {
            $this->flash('error', t('The price list was not found.'));

            return $this->buildRedirect($this->action('view'));
        }
        $this->renderForm($pricelist);
    }

    public function save($id = null)
    {
        if (!$this->token->validate('fewo_pricelist_save')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $repository = $this->getRepository();
        $pricelist = $id ? $repository->find((int) $id) : new Pricelist();
        if ($pricelist === null) {
            return $this->buildRedirect($this->action('view'));
        }

        $this->fillFromRequest($pricelist);

        if ($this->error->has()) {
            // Formular mit den eingegebenen Werten erneut anzeigen, nichts speichern.
            // Vom EntityManager lösen, damit ein späteres flush() die ungültigen Werte nicht schreibt.
            if ($pricelist->getId() !== null) {
                $this->getEntityManager()->detach($pricelist);
            }
            $this->renderForm($pricelist);

            return null;
        }

        $repository->save($pricelist);
        $this->flash('success', t('The price list has been saved.'));

        return $this->buildRedirect($this->action('edit', $pricelist->getId()));
    }

    public function duplicate($id = null)
    {
        $source = $this->getRepository()->find((int) $id);
        if ($source !== null && $this->token->validate('fewo_pricelist_duplicate')) {
            $copy = (new Pricelist())
                ->setName(t('%s (copy)', $source->getName()))
                ->setMaxGuests($source->getMaxGuests())
                ->setMinNights($source->getMinNights())
                ->setCleaningFee($source->getCleaningFee())
                ->setSecurityDeposit($source->getSecurityDeposit())
                ->setDownPaymentPercent($source->getDownPaymentPercent())
                ->setIncludedServices($source->getIncludedServices())
                ->setNote($source->getNote())
                ->setCurrency($source->getCurrency());
            foreach ($source->getSeasons() as $season) {
                $copy->addSeason((new Season($season->getName(), $season->getDateFrom(), $season->getDateTo()))
                    ->setMinNights($season->getMinNights())
                    ->setPrices($season->getPrices()));
            }
            $this->getRepository()->save($copy);
            $this->flash('success', t('The price list has been copied.'));

            return $this->buildRedirect($this->action('edit', $copy->getId()));
        }

        return $this->buildRedirect($this->action('view'));
    }

    public function delete($id = null)
    {
        $pricelist = $this->getRepository()->find((int) $id);
        if ($pricelist !== null && $this->token->validate('fewo_pricelist_delete')) {
            $this->getRepository()->delete($pricelist);
            $this->flash('success', t('The price list has been deleted.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    /**
     * Import im XML-Format des alten Fewo Toolkits.
     */
    public function import()
    {
        if (!$this->token->validate('fewo_pricelist_import')) {
            $this->flash('error', $this->token->getErrorMessage());

            return $this->buildRedirect($this->action('view'));
        }

        $xml = trim((string) $this->request->request->get('importXml', ''));
        $file = $this->request->files->get('importFile');
        if ($file !== null && $file->isValid()) {
            if ($file->getSize() > 2 * 1024 * 1024) {
                $this->flash('error', t('The file is too large.'));

                return $this->buildRedirect($this->action('view'));
            }
            $xml = (string) file_get_contents($file->getPathname());
        }

        $target = $this->getRepository()->find((int) $this->request->request->get('importTarget'));
        try {
            $pricelist = (new LegacyPricelistImporter())->import($xml, $target);
        } catch (ImportException $e) {
            $this->flash('error', t('Import failed: %s', $e->getMessage()));

            return $this->buildRedirect($this->action('view'));
        }

        $this->getRepository()->save($pricelist);
        $this->flash('success', t2('%s season has been imported. Please check the price list.', '%s seasons have been imported. Please check the price list.', count($pricelist->getSeasons())));

        return $this->buildRedirect($this->action('edit', $pricelist->getId()));
    }

    private function renderForm(Pricelist $pricelist): void
    {
        $this->set('pricelist', $pricelist);
        $this->set('today', new DateTimeImmutable('today'));
        $this->render('/dashboard/fewo_toolkit/pricelists/edit', 'fewo_toolkit');
    }

    private function fillFromRequest(Pricelist $pricelist): void
    {
        $post = $this->request->request;
        $name = trim((string) $post->get('name', ''));
        if ($name === '') {
            $this->error->add(t('Please enter a name.'));
        }
        $maxGuests = (int) $post->get('maxGuests', 0);
        if ($maxGuests < 1 || $maxGuests > self::MAX_GUESTS) {
            $this->error->add(t('The maximum number of guests must be between 1 and %s.', self::MAX_GUESTS));
            $maxGuests = max(1, min(self::MAX_GUESTS, $maxGuests));
        }

        $pricelist
            ->setName($name)
            ->setMaxGuests($maxGuests)
            ->setMinNights((int) $post->get('minNights', 1))
            ->setCleaningFee($this->money('cleaningFee', t('Final cleaning')))
            ->setSecurityDeposit($this->money('securityDeposit', t('Security deposit')))
            ->setDownPaymentPercent((int) $post->get('downPaymentPercent', 0))
            ->setIncludedServices((string) $post->get('includedServices', ''))
            ->setNote((string) $post->get('note', ''));

        $pricelist->clearSeasons();
        $rows = $post->all('seasons');
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $seasonName = trim((string) ($row['name'] ?? ''));
            $from = $this->date((string) ($row['from'] ?? ''));
            $to = $this->date((string) ($row['to'] ?? ''));
            $prices = [];
            foreach ((array) ($row['prices'] ?? []) as $guests => $value) {
                if ((int) $guests >= 1 && (int) $guests <= $maxGuests) {
                    $cents = Money::parse((string) $value);
                    if ($cents === null && trim((string) $value) !== '') {
                        $this->error->add(t('Season "%1$s": "%2$s" is not a valid price.', $seasonName, (string) $value));
                    }
                    $prices[(int) $guests] = $cents;
                }
            }

            // Komplett leere Zeilen ignorieren
            if ($seasonName === '' && $from === null && $to === null && array_filter($prices) === []) {
                continue;
            }

            $label = $seasonName !== '' ? $seasonName : t('Row %s', (int) $index + 1);
            if ($seasonName === '') {
                $this->error->add(t('%s: please enter a name for the season.', $label));
            }
            if ($from === null || $to === null) {
                $this->error->add(t('%s: please enter the start and end date.', $label));
            } elseif ($to < $from) {
                $this->error->add(t('%s: the end date is before the start date.', $label));
            }
            if (array_filter($prices) === []) {
                $this->error->add(t('%s: please enter at least one price.', $label));
            }

            $season = new Season($seasonName, $from, $to ?? $from);
            $season->setMinNights(($row['minNights'] ?? '') !== '' ? (int) $row['minNights'] : null);
            $season->setPrices($prices);
            $pricelist->addSeason($season);
        }
    }

    private function money(string $field, string $label): int
    {
        $value = (string) $this->request->request->get($field, '');
        $cents = Money::parse($value);
        if ($cents === null && trim($value) !== '') {
            $this->error->add(t('%s: please enter a valid amount.', $label));
        }

        return $cents ?? 0;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date !== false && $date->format('Y-m-d') === trim($value) ? $date : null;
    }

    private function getRepository(): PricelistRepository
    {
        return $this->app->make(PricelistRepository::class);
    }
}
