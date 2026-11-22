<?php

namespace FewoTk2\Mail;

use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\Mail\Transport\FactoryInterface as TransportFactory;
use Concrete\Core\User\UserInfoRepository;
use Symfony\Component\Mailer\Mailer;

/**
 * Liefert den Mail-Dienst von Concrete – mit dem eigenen SMTP-Postfach des Pakets, wenn eines eingerichtet ist,
 * sonst mit den Einstellungen von Concrete. Vorlagen, Protokoll und der Schalter "E-Mails verschicken" von Concrete
 * gelten in beiden Fällen.
 */
class MailerFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly MailSettingsRepository $repository,
    ) {
    }

    public function create(): MailService
    {
        $settings = $this->repository->get();
        $config = $this->app->make(Repository::class);
        if ($settings->usesOwnSmtp()) {
            $transport = $this->app->make(TransportFactory::class)->createSmtpTransportFromArray($settings->toTransportArray($this->getDefaultHelo()));
            $mailer = new Mailer($transport, null, $this->app->make('director')->getEventDispatcher());
            $emailClass = $config->get('concrete.email.email_class');
            $mail = $emailClass ? new MailService($config, $mailer, $emailClass) : new MailService($config, $mailer);
            // Die Absenderadresse muss zum Postfach gehören, sonst landen die Mails im Spam oder werden abgelehnt
            $mail->from($settings->fromAddress, $settings->fromName !== '' ? $settings->fromName : $this->getSiteName());
        } else {
            $mail = $this->app->make('mail');
            $sender = $this->getFallbackSender();
            if ($sender !== '') {
                $mail->from($sender);
            }
        }
        // Fehler als Exception melden statt sie nur zu protokollieren
        $mail->setIsThrowOnFailure(true);

        return $mail;
    }

    /**
     * Name, mit dem sich die Website beim SMTP-Server meldet, wenn keiner eingetragen ist: die Domain der Website
     * (kanonische Adresse, sonst die aufgerufene), sonst der Name aus den SMTP-Einstellungen von Concrete.
     */
    public function getDefaultHelo(): string
    {
        $config = $this->app->make(Repository::class);
        $candidates = [parse_url((string) $config->get('site.sites.default.seo.canonical_url'), PHP_URL_HOST)];
        try {
            $candidates[] = $this->app->make(\Concrete\Core\Http\Request::class)->getHost();
        } catch (\Throwable) {
        }
        $candidates[] = $config->get('concrete.mail.methods.smtp.helo_domain');
        foreach ($candidates as $candidate) {
            $name = MailSettings::heloName($candidate);
            if ($name !== '' && $name !== 'localhost') {
                return $name;
            }
        }

        return 'localhost';
    }

    /**
     * Ist der Versand in Concrete ganz abgeschaltet (Verwaltung › System & Einstellungen › E-Mail › Versandart)?
     * Dann verschickt auch das eigene Postfach nichts – Concrete protokolliert die Mails nur.
     */
    public function isSendingDisabled(): bool
    {
        return !$this->app->make(Repository::class)->get('concrete.email.enabled');
    }

    /**
     * Absender, falls in Concrete keine Standard-Absenderadresse hinterlegt ist (Auslieferungszustand):
     * wie beim Formular-Block von Concrete die Absenderadresse für Formulare, sonst die des Hauptbenutzers.
     * Leer, wenn Concrete seine Standardadresse verwenden kann.
     */
    private function getFallbackSender(): string
    {
        $config = $this->app->make(Repository::class);
        if (self::isAddress($config->get('concrete.email.default.address'))) {
            return '';
        }
        $address = $config->get('concrete.email.form_block.address');
        if (self::isAddress($address)) {
            return (string) $address;
        }
        $admin = $this->app->make(UserInfoRepository::class)->getByID(USER_SUPER_ID);
        $address = $admin !== null ? $admin->getUserEmail() : '';

        return self::isAddress($address) ? (string) $address : '';
    }

    private static function isAddress(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function getSiteName(): string
    {
        return (string) $this->app->make('site')->getSite()->getSiteName();
    }
}
