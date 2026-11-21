<?php

namespace Concrete\Package\FewoTk2\Block\FewoPreisrechner;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Url;
use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Block\ApartmentBlock;
use FewoTk2\Calendar\BookingReader;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Calendar\OccupancyLoader;
use FewoTk2\Feed\FeedLoader;
use FewoTk2\Feed\InvalidFeedException;
use FewoTk2\Pricing\LoadedPricelist;
use FewoTk2\Pricing\PriceCalculator;
use FewoTk2\Pricing\PricingException;
use FewoTk2\Pricing\Quote;
use Symfony\Component\HttpFoundation\JsonResponse;

class Controller extends BlockController
{
    use AddDefaults;
    use ApartmentBlock;

    public $apartmentID = 0;
    public $title = '';
    public $inquiryPageID = 0;

    protected $btTable = 'btFewoPreisrechner';
    protected $btInterfaceWidth = 560;
    protected $btInterfaceHeight = 460;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;

    private bool $quoteHandled = false;

    /** So weit im Voraus lässt sich ein Aufenthalt berechnen. */
    private const MAX_ADVANCE = '+2 years';

    private ?LoadedPricelist $loaded = null;

    private Occupancy|false|null $occupancy = false;

    public function getBlockTypeName()
    {
        return t('Price Calculator');
    }

    public function getBlockTypeDescription()
    {
        return t('Lets guests calculate the price of their stay. Price list and occupancy are loaded from Fewo-Office; booked periods cannot be selected.');
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->edit();
    }

    public function edit()
    {
        $this->setApartmentOptions(t('** Please select'));
    }

    public function view()
    {
        $loaded = $this->getLoadedPricelist();
        $this->set('pricelist', $loaded->pricelist);
        $this->set('feed', $loaded->feed);
        $this->set('loadError', $loaded->error);
        $this->set('canEdit', $this->canEditPage());
        $today = new DateTimeImmutable('today');
        $this->set('today', $today);
        $this->set('maxDate', $today->modify(self::MAX_ADVANCE));
        // null = zur Wohnung ist keine Belegung bekannt: dann gibt es keinen Auswahlkalender
        $this->set('bookedRanges', $this->getOccupancy()?->getBookedRanges($today, $today->modify(self::MAX_ADVANCE)));
        $this->set('actionUrl', $loaded->pricelist ? (string) $this->getActionURL('quote') : '');
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
            // Concrete ruft die Aktion bei allen Preisrechnern der Seite auf und überspringt danach view().
            // Blöcke, die nicht gemeint sind, müssen sich deshalb selbst normal darstellen.
            $this->view();

            return null;
        }

        $pricelist = $this->getLoadedPricelist()->pricelist;
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
            $arrival = $this->parseDate($values['arrival'], t('Please enter a valid arrival date.'));
            $departure = $this->parseDate($values['departure'], t('Please enter a valid departure date.'));
            $quote = (new PriceCalculator())->quote($pricelist, $arrival, $departure, $values['guests']);
            // Für bereits belegte Zeiträume gibt es keinen Preis
            if ($this->getOccupancy()?->isFree($arrival, $departure) === false) {
                $quote = null;
                throw new PricingException(t('The apartment is already booked in the selected period. Please choose different dates.'));
            }
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
        if ($this->app->make(ApartmentRepository::class)->find((int) ($args['apartmentID'] ?? 0)) === null) {
            $errors->add(t('Please select an apartment.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'apartmentID' => (int) ($args['apartmentID'] ?? 0),
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

    /**
     * Belegung der Wohnung – null, wenn keine Adresse hinterlegt ist oder noch nichts geladen werden konnte.
     * Der Rechner funktioniert dann weiter, nur ohne Prüfung der Verfügbarkeit.
     */
    private function getOccupancy(): ?Occupancy
    {
        if ($this->occupancy === false) {
            $this->occupancy = null;
            $apartment = $this->getApartment();
            $url = $apartment !== null ? FeedLoader::normalizeUrl($apartment->getOccupancyUrl()) : null;
            $body = $url !== null ? $this->app->make(OccupancyLoader::class)->load($url, $apartment->getRefreshMinutes())->getBody() : null;
            if ($body !== null) {
                $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get()));
                try {
                    $this->occupancy = new Occupancy((new BookingReader($today->getTimezone()))->read(
                        $body,
                        $today->modify('-1 day'),
                        $today->modify(self::MAX_ADVANCE)->modify('+1 day')
                    ));
                } catch (InvalidFeedException) {
                    // unbrauchbare Daten: wie "keine Belegung bekannt"
                }
            }
        }

        return $this->occupancy;
    }

    private function getLoadedPricelist(): LoadedPricelist
    {
        return $this->loaded ??= $this->loadPricelist();
    }
}
