<?php

/**
 * Richtet die aus dem Backup wiederhergestellte Website für den lokalen Betrieb ein:
 * Adresse der Website, E-Mail-Versand nur an Mailpit, lokales Kennwort für den Hauptbenutzer.
 * Ändert nur die lokale Kopie (Volumes des Testprojekts), nie das Backup.
 *
 *   docker compose exec -u www-data web ./concrete/bin/concrete c5:exec /seed/local.php <Kennwort>
 */

use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\UserInfoRepository;

$app = Application::getFacadeApplication();
$password = (string) (($args ?? [])[0] ?? 'seeherz-test-admin');

// Keine feste Adresse der Website: Concrete bildet Links dann aus der aufgerufenen Adresse. So funktioniert
// die Instanz unter http://localhost:8096 genauso wie von einem anderen Rechner unter http://<Rechner>:8096.
$site = $app->make('site')->getSite();
$siteConfig = $site->getConfigRepository();
$siteConfig->save('seo.canonical_url', '');
$siteConfig->save('seo.canonical_url_alternative', '');

$config = $app->make('config');
// Die echte Website leitet auf https://www.seeherz.com um – hier nicht
$config->save('concrete.seo.redirect_to_canonical_url', 0);
// E-Mails (Kontaktformulare, Benachrichtigungen) landen in Mailpit, nie bei echten Empfängern
$config->save('concrete.mail.method', 'smtp');
$config->save('concrete.mail.methods.smtp.server', 'mailpit');
$config->save('concrete.mail.methods.smtp.port', 1025);
$config->save('concrete.mail.methods.smtp.encryption', '');
$config->save('concrete.mail.methods.smtp.username', '');
$config->save('concrete.mail.methods.smtp.password', '');
// Alle PHP-Meldungen ins Log (docker compose logs web)
$config->save('concrete.debug.error_reporting', E_ALL);
// Sitzungs-Cookie auch über http
$config->save('concrete.session.cookie.cookie_secure', false);

// Das alte Kontaktformular versendet selbst über ein im Block gespeichertes SMTP-Konto – auch das nur an Mailpit
$app->make('database')->connection()->executeStatement(
    'UPDATE btFwbKontaktformular SET email_empfaenger = ?, smtp_auth = ? WHERE email_empfaenger <> \'\'',
    ['vermieter@seeherz.test', serialize(['smtp_host' => 'mailpit:1025', 'smtp_security' => '', 'smtp_username' => 'buchung@seeherz.test', 'smtp_password' => ''])]
);

$admin = $app->make(UserInfoRepository::class)->getByID(USER_SUPER_ID);
$admin->changePassword($password);

echo 'Anmeldung: ' . $admin->getUserName() . ' / ' . $password . "\n";

return 0;
