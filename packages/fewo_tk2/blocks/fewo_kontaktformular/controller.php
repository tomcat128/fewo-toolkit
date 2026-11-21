<?php

namespace Concrete\Package\FewoTk2\Block\FewoKontaktformular;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Url;
use DateTimeImmutable;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Block\ApartmentBlock;
use FewoTk2\Inquiry\Inquiry;
use FewoTk2\Inquiry\InquiryMailer;
use FewoTk2\Inquiry\InquiryValidator;
use FewoTk2\Pricing\PriceCalculator;
use FewoTk2\Pricing\Pricelist;
use FewoTk2\Pricing\PricingException;
use FewoTk2\Pricing\Quote;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class Controller extends BlockController
{
    use AddDefaults;
    use ApartmentBlock;

    /** Mindestzeit in Sekunden zwischen Seitenaufruf und Absenden (Spamschutz). */
    private const MIN_FILL_SECONDS = 3;

    public $formName = '';
    public $recipientEmail = '';
    public $apartmentID = 0;
    public $maxGuests = 6;
    public $accommodations = '';
    public $referralOptions = '';
    public $askForAddress = true;
    public $privacyPageID = 0;
    public $useCaptcha = false;
    public $sendConfirmation = true;
    public $successMessage = '';

    protected $btTable = 'btFewoKontaktformular';
    protected $btInterfaceWidth = 640;
    protected $btInterfaceHeight = 620;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    // Formular mit Token: niemals zwischenspeichern
    protected $btCacheBlockOutput = false;

    private bool $submitted = false;

    private Pricelist|false|null $loadedPricelist = false;

    public function getBlockTypeName()
    {
        return t('Booking Inquiry Form');
    }

    public function getBlockTypeDescription()
    {
        return t('Inquiry form for travel dates, guests and contact details. Every inquiry is sent by email to the address set in the block.');
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->set('formName', '');
        $this->set('recipientEmail', (string) $this->app->make('config')->get('concrete.email.default.address'));
        $this->set('referralOptions', implode("\n", [
            t('Search engine'),
            t('Holiday rental portal'),
            t('Recommendation by friends'),
            t('Tourist information'),
        ]));
        $this->set('successMessage', t('Thank you for your inquiry! We will get back to you as soon as possible.'));
        $this->edit();
    }

    public function edit()
    {
        $this->setApartmentOptions(t('None'));
        $this->set('captchaConfigured', $this->app->make('helper/validation/captcha') !== null);
    }

    public function view()
    {
        if ($this->submitted) {
            return;
        }
        $query = $this->request->query;
        $this->prepareView([
            // Vorbelegung, z. B. vom Preisrechner
            'arrival' => (string) $query->get('arrival', ''),
            'departure' => (string) $query->get('departure', ''),
            'adults' => (string) $query->get('guests', ''),
        ], []);
        $this->set('sent', (int) $query->get('fewo_inquiry') === (int) $this->bID);
    }

    public function action_submit($bID = null)
    {
        if ((int) $bID !== (int) $this->bID || !$this->request->isMethod('POST')) {
            // Concrete ruft die Aktion bei allen Anfrageformularen der Seite auf und überspringt danach view().
            // Formulare, die nicht gemeint sind, müssen sich deshalb selbst normal darstellen.
            $this->view();

            return null;
        }
        $this->submitted = true;
        $input = $this->request->request->all();
        $errors = [];

        $tokenValue = (string) ($input['fewo_token'] ?? '');
        $token = $this->app->make('token');
        if (!$token->validate($this->getTokenAction(), $tokenValue)) {
            $errors['form'] = t('The form has expired. Please submit it again.');
        } elseif (!empty($input['website'])) {
            // Honeypot ausgefüllt: so tun, als wäre alles in Ordnung
            return $this->redirectToSuccess();
        } elseif (time() - (int) strtok($tokenValue, ':') < self::MIN_FILL_SECONDS) {
            $errors['form'] = t('Please check your details and submit the form again.');
        }

        $validator = $this->getValidator();
        $errors += $validator->validate($input);

        if ($this->useCaptcha) {
            $captcha = $this->app->make('helper/validation/captcha');
            if ($captcha !== null && !$captcha->check()) {
                $errors['captcha'] = t('The security code is incorrect.');
            }
        }

        if ($errors === []) {
            $inquiry = $validator->fill(new Inquiry(), $input);
            $page = Page::getCurrentPage();
            $inquiry->setFormName($this->formName ?: ($page instanceof Page ? (string) $page->getCollectionName() : ''));
            $quote = $this->tryQuote($inquiry);
            if ($quote !== null) {
                $inquiry->setQuotedTotal($quote->getTotal())->setCurrency($quote->currency);
            }

            if ($this->deliver($inquiry, $quote)) {
                return $this->redirectToSuccess();
            }
            $errors['form'] = t('Your inquiry could not be sent. Please try again later or contact us directly by email or phone.');
        }

        $this->prepareView($input, $errors);
        $this->set('sent', false);

        return null;
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if (filter_var(trim((string) ($args['recipientEmail'] ?? '')), FILTER_VALIDATE_EMAIL) === false) {
            $errors->add(t('Please enter a valid recipient email address.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'formName' => trim((string) ($args['formName'] ?? '')),
            'recipientEmail' => trim((string) ($args['recipientEmail'] ?? '')),
            'apartmentID' => (int) ($args['apartmentID'] ?? 0),
            'maxGuests' => max(1, min(50, (int) ($args['maxGuests'] ?? 6))),
            'accommodations' => implode("\n", InquiryValidator::lines((string) ($args['accommodations'] ?? ''))),
            'referralOptions' => implode("\n", InquiryValidator::lines((string) ($args['referralOptions'] ?? ''))),
            'askForAddress' => !empty($args['askForAddress']) ? 1 : 0,
            'privacyPageID' => (int) ($args['privacyPageID'] ?? 0),
            'useCaptcha' => !empty($args['useCaptcha']) ? 1 : 0,
            'sendConfirmation' => !empty($args['sendConfirmation']) ? 1 : 0,
            'successMessage' => trim((string) ($args['successMessage'] ?? '')),
        ]);
    }

    public function getMaxGuests(): int
    {
        return $this->getPricelist()?->maxGuests ?? max(1, (int) $this->maxGuests);
    }

    /**
     * Preisliste aus Fewo-Office, falls im Block eine Wohnung gewählt ist und sich ihre Preisliste laden lässt.
     */
    private function getPricelist(): ?Pricelist
    {
        if ($this->loadedPricelist === false) {
            $this->loadedPricelist = $this->getApartment() !== null ? $this->loadPricelist()->pricelist : null;
        }

        return $this->loadedPricelist;
    }

    private function prepareView(array $values, array $errors): void
    {
        $privacyPage = $this->privacyPageID ? Page::getByID((int) $this->privacyPageID) : null;
        $this->set('values', $values);
        $this->set('errors', $errors);
        $this->set('maxGuests', $this->getMaxGuests());
        $this->set('accommodationList', InquiryValidator::lines((string) $this->accommodations));
        $this->set('referralList', InquiryValidator::lines((string) $this->referralOptions));
        $this->set('salutations', InquiryValidator::salutations());
        $this->set('privacyUrl', $privacyPage instanceof Page && !$privacyPage->isError() ? (string) Url::to($privacyPage) : null);
        $this->set('captcha', $this->useCaptcha ? $this->app->make('helper/validation/captcha') : null);
        $this->set('tokenAction', $this->getTokenAction());
        $this->set('actionUrl', (string) $this->getActionURL('submit'));
        $this->set('today', new DateTimeImmutable('today'));
    }

    private function getValidator(): InquiryValidator
    {
        return new InquiryValidator(
            $this->getMaxGuests(),
            InquiryValidator::lines((string) $this->accommodations),
            InquiryValidator::lines((string) $this->referralOptions),
            (bool) $this->askForAddress,
        );
    }

    private function getTokenAction(): string
    {
        return 'fewo_inquiry_' . (int) $this->bID;
    }

    private function tryQuote(Inquiry $inquiry): ?Quote
    {
        $pricelist = $this->getPricelist();
        if ($pricelist === null) {
            return null;
        }
        try {
            return (new PriceCalculator())->quote($pricelist, $inquiry->getArrival(), $inquiry->getDeparture(), $inquiry->getGuests());
        } catch (PricingException) {
            return null;
        }
    }

    /**
     * Verschickt die Anfrage per E-Mail an die im Block hinterlegte Adresse. Gespeichert wird nichts.
     *
     * @return bool false, wenn die E-Mail an den Vermieter nicht verschickt werden konnte
     */
    private function deliver(Inquiry $inquiry, ?Quote $quote): bool
    {
        $logger = $this->app->make(LoggerFactory::class)->createLogger(Channels::CHANNEL_APPLICATION);
        $mailer = $this->app->make(InquiryMailer::class);
        try {
            $mailer->sendNotification($inquiry, (string) $this->recipientEmail, $quote);
        } catch (Throwable $e) {
            $logger->error('Fewo Toolkit: inquiry notification could not be sent: ' . $e->getMessage());

            return false;
        }

        if ($this->sendConfirmation) {
            try {
                $mailer->sendConfirmation($inquiry, $quote, (string) $this->recipientEmail);
            } catch (Throwable $e) {
                $logger->warning('Fewo Toolkit: inquiry confirmation could not be sent: ' . $e->getMessage());
            }
        }

        return true;
    }

    private function redirectToSuccess(): RedirectResponse
    {
        $url = Url::to(Page::getCurrentPage())->setQuery(['fewo_inquiry' => (int) $this->bID]);

        return new RedirectResponse((string) $url . '#fewo-inquiry-' . (int) $this->bID, 303);
    }
}
