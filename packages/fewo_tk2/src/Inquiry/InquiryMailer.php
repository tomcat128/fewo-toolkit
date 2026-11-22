<?php

namespace FewoTk2\Inquiry;

use Concrete\Core\Application\Application;
use Concrete\Core\Mail\Service as MailService;
use FewoTk2\Mail\MailerFactory;

/**
 * Versendet die Benachrichtigung an den Vermieter und die Eingangsbestätigung
 * an den Gast – über das eigene SMTP-Postfach des Pakets (Verwaltung › Fewo Toolkit 2 › E-Mail-Versand) oder,
 * wenn keines eingerichtet ist, über die Mail-Einstellungen von Concrete (siehe MailerFactory).
 */
class InquiryMailer
{
    public function __construct(private readonly Application $app)
    {
    }

    public function sendNotification(Inquiry $inquiry, string $recipient): void
    {
        $mail = $this->createMail();
        $mail->to($recipient);
        $mail->replyto($inquiry->getEmail(), $inquiry->getFullName());
        $mail->addParameter('inquiry', $inquiry);
        $mail->addParameter('siteName', $this->getSiteName());
        $mail->load('fewo_inquiry_notification', 'fewo_tk2');
        $mail->sendMail();
    }

    /**
     * Die Bestätigung enthält bewusst keine Freitexte des Absenders, damit das
     * Formular nicht zum Versand von Spam an fremde Adressen missbraucht werden kann.
     */
    public function sendConfirmation(Inquiry $inquiry, string $replyTo): void
    {
        $mail = $this->createMail();
        $mail->to($inquiry->getEmail());
        if ($replyTo !== '') {
            $mail->replyto($replyTo);
        }
        $mail->addParameter('inquiry', $inquiry);
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
        return $this->app->make(MailerFactory::class)->create();
    }

    private function getSiteName(): string
    {
        return (string) $this->app->make('site')->getSite()->getSiteName();
    }
}
