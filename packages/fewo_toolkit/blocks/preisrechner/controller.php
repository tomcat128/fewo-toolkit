<?php

namespace Concrete\Package\FewoToolkit\Block\Preisrechner;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Url;
use DateTimeImmutable;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Pricing\CalculationMode;
use FewoToolkit\Pricing\PriceCalculator;
use FewoToolkit\Pricing\PricelistRepository;
use FewoToolkit\Pricing\PricingException;
use FewoToolkit\Pricing\Quote;
use Symfony\Component\HttpFoundation\JsonResponse;

class Controller extends BlockController
{
    public $pricelistID = 0;
    public $calculationMode = 'arrival';
    public $title = '';
    public $inquiryPageID = 0;

    protected $btTable = 'btFewoPreisrechner';
    protected $btInterfaceWidth = 500;
    protected $btInterfaceHeight = 420;
    protected $btDefaultSet = 'fewo_toolkit';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;

    private bool $quoteHandled = false;

    public function getBlockTypeName()
    {
        return t('Price Calculator');
    }

    public function getBlockTypeDescription()
    {
        return t('Lets guests calculate the price of their stay.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('pricelistOptions', $this->getRepository()->getSelectOptions(t('** Please select')));
        $this->set('modeOptions', [
            CalculationMode::ArrivalDay->value => CalculationMode::ArrivalDay->label(),
            CalculationMode::FollowingDay->value => CalculationMode::FollowingDay->label(),
        ]);
        $this->set('dashboardUrl', (string) Url::to('/dashboard/fewo_toolkit/pricelists'));
    }

    public function view()
    {
        $pricelist = $this->getPricelist();
        $this->set('pricelist', $pricelist);
        $this->set('today', new DateTimeImmutable('today'));
        $this->set('actionUrl', $pricelist ? (string) $this->getActionURL('quote') : '');
        // Falls action_quote bereits gelaufen ist, dessen Ergebnis nicht überschreiben.
        if (!$this->quoteHandled) {
            $this->set('values', []);
            $this->setDefaultResult();
        }
    }

    /**
     * Berechnet den Preis. Antwortet bei Ajax-Anfragen mit JSON, sonst wird
     * die Seite mit dem Ergebnis neu dargestellt (funktioniert auch ohne JavaScript).
     */
    public function action_quote($bID = null)
    {
        if ((int) $bID !== (int) $this->bID) {
            return null;
        }

        $pricelist = $this->getPricelist();
        $values = [
            'arrival' => (string) $this->request->request->get('arrival', ''),
            'departure' => (string) $this->request->request->get('departure', ''),
            'guests' => (int) $this->request->request->get('guests', 0),
        ];
        $quote = null;
        $error = null;

        try {
            if ($pricelist === null) {
                throw new PricingException(t('The price calculator is not configured.'));
            }
            $quote = (new PriceCalculator())->quote(
                $pricelist,
                $this->parseDate($values['arrival'], t('Please enter a valid arrival date.')),
                $this->parseDate($values['departure'], t('Please enter a valid departure date.')),
                $values['guests'],
                CalculationMode::fromValue($this->calculationMode)
            );
        } catch (PricingException $e) {
            $error = $e->getMessage();
        }

        if ($this->wantsJson()) {
            return new JsonResponse([
                'ok' => $quote !== null,
                'html' => $this->renderResult($quote, $error),
                'quote' => $quote?->toArray(),
                'error' => $error,
            ]);
        }

        $this->quoteHandled = true;
        $this->view();
        $this->set('values', $values);
        $this->set('quote', $quote);
        $this->set('error', $error);
        $this->set('inquiryUrl', $quote ? $this->getInquiryUrl($quote) : null);

        return null;
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if ($this->getRepository()->find((int) ($args['pricelistID'] ?? 0)) === null) {
            $errors->add(t('Please select a price list.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'pricelistID' => (int) ($args['pricelistID'] ?? 0),
            'calculationMode' => CalculationMode::fromValue($args['calculationMode'] ?? null)->value,
            'title' => trim((string) ($args['title'] ?? '')),
            'inquiryPageID' => (int) ($args['inquiryPageID'] ?? 0),
        ]);
    }

    /**
     * HTML des Ergebnisses (wird von view.php und für Ajax-Antworten genutzt).
     */
    public function renderResult(?Quote $quote, ?string $error): string
    {
        $inquiryUrl = $quote ? $this->getInquiryUrl($quote) : null;
        ob_start();
        include __DIR__ . '/result.php';

        return (string) ob_get_clean();
    }

    private function setDefaultResult(): void
    {
        $this->set('quote', null);
        $this->set('error', null);
        $this->set('inquiryUrl', null);
    }

    private function getInquiryUrl(Quote $quote): ?string
    {
        $page = $this->inquiryPageID ? Page::getByID((int) $this->inquiryPageID) : null;
        if (!$page instanceof Page || $page->isError()) {
            return null;
        }

        return (string) Url::to($page)->setQuery([
            'arrival' => $quote->arrival->format('Y-m-d'),
            'departure' => $quote->departure->format('Y-m-d'),
            'guests' => $quote->guests,
        ]);
    }

    private function parseDate(string $value, string $error): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new PricingException($error);
        }

        return $date;
    }

    private function wantsJson(): bool
    {
        return $this->request->isXmlHttpRequest()
            || str_contains((string) $this->request->headers->get('Accept'), 'application/json');
    }

    private function getPricelist(): ?Pricelist
    {
        return $this->getRepository()->find((int) $this->pricelistID);
    }

    private function getRepository(): PricelistRepository
    {
        return $this->app->make(PricelistRepository::class);
    }
}
