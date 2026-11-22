<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfoRepository;
use FewoTk2\Mail\MailerFactory;
use FewoTk2\Mail\MailSettings;
use FewoTk2\Mail\MailSettingsRepository;
use Throwable;

/**
 * E-Mail-Versand des Anfrageformulars: über Concrete oder über ein eigenes SMTP-Postfach, mit Testversand.
 */
class Mail extends DashboardPageController
{
    /** Eingaben nach einem Fehler, die das Formular erneut zeigt. */
    private ?MailSettings $formSettings = null;
    private ?string $formRecipient = null;

    public function view()
    {
        $repository = $this->getRepository();
        $config = $this->app->make('config');
        $this->set('settings', $this->formSettings ?? $repository->get());
        $this->set('hasPassword', $repository->hasPassword());
        $this->set('passwordUnreadable', $repository->isPasswordUnreadable());
        $this->set('sendingDisabled', $this->app->make(MailerFactory::class)->isSendingDisabled());
        $this->set('concreteMethod', (string) $config->get('concrete.mail.method'));
        $this->set('concreteServer', (string) $config->get('concrete.mail.methods.smtp.server'));
        $this->set('testRecipient', $this->formRecipient ?? $this->getCurrentUserEmail());
        $this->set('defaultHelo', $this->app->make(MailerFactory::class)->getDefaultHelo());
    }

    public function save()
    {
        if (!$this->token->validate('fewo_mail_save')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $post = $this->request->request;
        $repository = $this->getRepository();
        $password = (string) $post->get('password', '');
        // Leeres Passwortfeld: das gespeicherte behalten, es sei denn, es soll ausdrücklich entfernt werden
        if ($password === '' && !$post->get('removePassword')) {
            $password = $repository->get()->password;
        }
        $settings = MailSettings::fromArray([
            'mode' => $post->get('mode'),
            'host' => $post->get('host'),
            'port' => $post->get('port'),
            'encryption' => $post->get('encryption'),
            'username' => $post->get('username'),
            'password' => $password,
            'fromAddress' => $post->get('fromAddress'),
            'fromName' => $post->get('fromName'),
            'heloDomain' => $post->get('heloDomain'),
        ]);
        foreach ($settings->validate() as $message) {
            $this->error->add($message);
        }
        if ($this->error->has()) {
            $this->formSettings = $settings;
            $this->view();

            return null;
        }
        $repository->save($settings);
        $this->flash('success', t('The settings for sending emails have been saved.'));

        return $this->buildRedirect($this->action('view'));
    }

    /**
     * Testmail über die gespeicherten Einstellungen.
     */
    public function test()
    {
        $recipient = trim((string) $this->request->request->get('testRecipient', ''));
        if (!$this->token->validate('fewo_mail_test')) {
            $this->error->add($this->token->getErrorMessage());
        } elseif (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error->add(t('Please enter a valid address for the test email.'));
        }
        if ($this->error->has()) {
            $this->formRecipient = $recipient;
            $this->view();

            return null;
        }

        $factory = $this->app->make(MailerFactory::class);
        $settings = $this->getRepository()->get();
        try {
            $mail = $factory->create();
            $mail->to($recipient);
            $mail->setSubject(t('Test email from %s', $this->getSiteName()));
            $mail->setBody(implode("\n\n", [
                t('Hello,'),
                t('this test email confirms that the inquiry form of %s can send emails.', $this->getSiteName()),
                $settings->usesOwnSmtp()
                    ? t('Sent via the SMTP mailbox %1$s (port %2$s) with the sender %3$s.', $settings->host, $settings->port, $settings->fromAddress)
                    : t('Sent with the email settings of Concrete.'),
                t('Sent on %s.', $this->app->make('date')->formatDateTime('now', true)),
            ]));
            $mail->sendMail();
        } catch (Throwable $e) {
            $this->flash('error', t('The test email could not be sent: %s', $e->getMessage()));

            return $this->buildRedirect($this->action('view'));
        }

        if ($factory->isSendingDisabled()) {
            $this->flash('message', t('Sending emails is switched off in Concrete. The test email was only logged, not sent.'));
        } else {
            $this->flash('success', t('The test email has been sent to %s.', $recipient));
        }

        return $this->buildRedirect($this->action('view'));
    }

    private function getRepository(): MailSettingsRepository
    {
        return $this->app->make(MailSettingsRepository::class);
    }

    private function getCurrentUserEmail(): string
    {
        $user = $this->app->make(User::class);
        $info = $user->isRegistered() ? $this->app->make(UserInfoRepository::class)->getByID($user->getUserID()) : null;

        return $info !== null ? (string) $info->getUserEmail() : '';
    }

    private function getSiteName(): string
    {
        return (string) $this->app->make('site')->getSite()->getSiteName();
    }
}
