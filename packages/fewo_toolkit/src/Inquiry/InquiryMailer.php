<?php

namespace FewoToolkit\Inquiry;

use Concrete\Core\Application\Application;
use Concrete\Core\Mail\Service as MailService;
use FewoToolkit\Entity\Inquiry;
use FewoToolkit\Pricing\Quote;

/**
 * Versendet die Benachrichtigung an den Vermieter und die Eingangsbestätigung
 * an den Gast. Versand über die in Concrete konfigurierten Mail-Einstellungen
 * (Dashboard > System > E-Mail > SMTP).
 */
class InquiryMailer
{
    public function __construct(private readonly Application $app)
    {
    }

    public function sendNotification(Inquiry $inquiry, string $recipient, ?Quote $quote, ?string $dashboardUrl): void
    {
        $mail = $this->createMail();
        $mail->to($recipient);
        $mail->replyto($inquiry->getEmail(), $inquiry->getFullName());
        $mail->addParameter('inquiry', $inquiry);
        $mail->addParameter('quote', $quote);
        $mail->addParameter('dashboardUrl', $dashboardUrl);
        $mail->addParameter('siteName', $this->getSiteName());
        $mail->load('fewo_inquiry_notification', 'fewo_toolkit');
        $mail->sendMail();
    }

    /**
     * Die Bestätigung enthält bewusst keine Freitexte des Absenders, damit das
     * Formular nicht zum Versand von Spam an fremde Adressen missbraucht werden kann.
     */
    public function sendConfirmation(Inquiry $inquiry, ?Quote $quote, string $replyTo): void
    {
        $mail = $this->createMail();
        $mail->to($inquiry->getEmail());
        if ($replyTo !== '') {
            $mail->replyto($replyTo);
        }
        $mail->addParameter('inquiry', $inquiry);
        $mail->addParameter('quote', $quote);
        $mail->addParameter('siteName', $this->getSiteName());
        $mail->load('fewo_inquiry_confirmation', 'fewo_toolkit');
        $mail->sendMail();
    }

    private function createMail(): MailService
    {
        /** @var MailService $mail */
        $mail = $this->app->make('mail');
        // Fehler als Exception melden statt sie nur zu protokollieren
        $mail->setIsThrowOnFailure(true);

        return $mail;
    }

    private function getSiteName(): string
    {
        return (string) $this->app->make('site')->getSite()->getSiteName();
    }
}
