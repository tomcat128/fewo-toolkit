<?php

namespace FewoTk2\Inquiry;

use Concrete\Core\Application\Application;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\User\UserInfoRepository;
use FewoTk2\Pricing\Quote;

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

    public function sendNotification(Inquiry $inquiry, string $recipient, ?Quote $quote): void
    {
        $mail = $this->createMail();
        $mail->to($recipient);
        $mail->replyto($inquiry->getEmail(), $inquiry->getFullName());
        $mail->addParameter('inquiry', $inquiry);
        $mail->addParameter('quote', $quote);
        $mail->addParameter('siteName', $this->getSiteName());
        $mail->load('fewo_inquiry_notification', 'fewo_tk2');
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
        $mail->load('fewo_inquiry_confirmation', 'fewo_tk2');
        $mail->sendMail();
    }

    /**
     * Formatiert "Bezeichnung: Wert"-Zeilen für die Klartext-Mail in zwei Spalten.
     *
     * Zählt Zeichen statt Bytes (Umlaute) und lässt auch nach langen Bezeichnungen ein Leerzeichen.
     *
     * @param array<string, string> $rows
     */
    public static function textRows(array $rows, int $labelWidth = 24): string
    {
        $text = '';
        foreach ($rows as $label => $value) {
            $label .= ':';
            $text .= $label . str_repeat(' ', max(1, $labelWidth - mb_strlen($label))) . $value . "\n";
        }

        return $text;
    }

    private function createMail(): MailService
    {
        /** @var MailService $mail */
        $mail = $this->app->make('mail');
        // Fehler als Exception melden statt sie nur zu protokollieren
        $mail->setIsThrowOnFailure(true);
        $sender = $this->getFallbackSender();
        if ($sender !== '') {
            $mail->from($sender);
        }

        return $mail;
    }

    /**
     * Absender, falls in Concrete keine Standard-Absenderadresse hinterlegt ist (Auslieferungszustand):
     * wie beim Formular-Block von Concrete die Absenderadresse für Formulare, sonst die des Hauptbenutzers.
     * Leer, wenn Concrete seine Standardadresse verwenden kann.
     */
    private function getFallbackSender(): string
    {
        $config = $this->app->make('config');
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
