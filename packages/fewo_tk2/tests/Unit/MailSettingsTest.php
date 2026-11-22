<?php

namespace FewoTk2\Tests\Unit;

use FewoTk2\Mail\MailSettings;
use FewoTk2\Mail\MailSettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * E-Mail-Versand des Anfrageformulars: eigenes SMTP-Postfach oder Concrete.
 */
final class MailSettingsTest extends TestCase
{
    public function testDefaultIsConcrete(): void
    {
        $settings = MailSettings::fromArray([]);
        self::assertFalse($settings->usesOwnSmtp());
        self::assertSame(587, $settings->port);
        self::assertSame(MailSettings::STARTTLS, $settings->encryption);
        // Für den Versand über Concrete wird nichts verlangt
        self::assertSame([], $settings->validate());
    }

    public function testValuesAreCleanedAndPortsFollowTheEncryption(): void
    {
        $settings = MailSettings::fromArray([
            'mode' => 'smtp', 'host' => " smtp.example.com\n", 'port' => '0', 'encryption' => 'ssl',
            'username' => ' postfach@example.com ', 'password' => ' geheim ', 'fromAddress' => 'anfragen@example.com', 'fromName' => 'Ferienwohnung Seeherz',
        ]);
        self::assertTrue($settings->usesOwnSmtp());
        self::assertSame('smtp.example.com', $settings->host);
        self::assertSame(465, $settings->port);
        self::assertSame('postfach@example.com', $settings->username);
        // Leerzeichen können zum Passwort gehören
        self::assertSame(' geheim ', $settings->password);
        self::assertSame([], $settings->validate());
        self::assertSame(25, MailSettings::fromArray(['encryption' => 'none', 'port' => 99999])->port);
        self::assertSame(MailSettings::STARTTLS, MailSettings::fromArray(['encryption' => 'tls'])->encryption);
        self::assertSame(MailSettings::MODE_CONCRETE, MailSettings::fromArray(['mode' => 'sendmail'])->mode);
    }

    public function testOwnSmtpNeedsServerAndSender(): void
    {
        self::assertSame(
            ['Please enter the SMTP server, e.g. smtp.example.com.', 'Please enter a valid sender address.'],
            MailSettings::fromArray(['mode' => 'smtp', 'host' => 'smtp example', 'fromAddress' => 'kein@'])->validate()
        );
        self::assertSame(
            ['A password without a user name is not used. Please enter the user name as well.'],
            MailSettings::fromArray(['mode' => 'smtp', 'host' => 'mail.example.com', 'fromAddress' => 'a@example.com', 'password' => 'x'])->validate()
        );
    }

    public function testTransportForConcrete(): void
    {
        $settings = MailSettings::fromArray(['mode' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'starttls', 'username' => 'u', 'password' => 'p']);
        self::assertSame(['server' => 'smtp.example.com', 'port' => 587, 'encryption' => 'STARTTLS', 'username' => 'u', 'password' => 'p', 'messages_per_connection' => 100, 'helo_domain' => 'seeherz.de'], $settings->toTransportArray('seeherz.de'));
        self::assertSame('SSL', MailSettings::fromArray(['encryption' => 'ssl'])->toTransportArray()['encryption']);
        self::assertSame('', MailSettings::fromArray(['encryption' => 'none'])->toTransportArray()['encryption']);
        // Ohne Benutzername keine Anmeldung, auch wenn noch ein Passwort gespeichert ist
        self::assertSame('', MailSettings::fromArray(['password' => 'p'])->toTransportArray()['password']);
        // Das Passwort wird nicht mit den übrigen Einstellungen gespeichert
        self::assertArrayNotHasKey('password', $settings->toArray());
        self::assertSame($settings->toArray(), MailSettings::fromArray($settings->toArray())->toArray());
    }

    public function testEhloNameIsNeverEmpty(): void
    {
        // Leerer Name: Server antworten mit "501 Syntax: HELO hostname"
        self::assertSame('localhost', MailSettings::fromArray([])->toTransportArray('')['helo_domain']);
        self::assertSame('localhost', MailSettings::fromArray([])->toTransportArray('kein name!')['helo_domain']);
        self::assertSame('www.seeherz.de', MailSettings::fromArray([])->toTransportArray('WWW.Seeherz.de')['helo_domain']);
        // IP-Adressen in eckigen Klammern
        self::assertSame('[192.168.178.127]', MailSettings::fromArray([])->toTransportArray('192.168.178.127')['helo_domain']);
        self::assertSame('[2001:db8::1]', MailSettings::heloName('[2001:db8::1]'));
        // eingetragener Name hat Vorrang, ungültige Eingaben werden verworfen
        self::assertSame('mail.seeherz.de', MailSettings::fromArray(['heloDomain' => ' mail.seeherz.de '])->toTransportArray('seeherz.de')['helo_domain']);
        self::assertSame('', MailSettings::fromArray(['heloDomain' => 'seeherz de'])->heloDomain);
        self::assertSame('', MailSettings::heloName('-seeherz.de'));
    }

    public function testPasswordIsStoredEncrypted(): void
    {
        $key = sodium_crypto_secretbox_keygen();
        $stored = MailSettingsRepository::encrypt('mein Passwort', $key);
        self::assertStringNotContainsString('Passwort', base64_decode($stored));
        self::assertNotSame($stored, MailSettingsRepository::encrypt('mein Passwort', $key));
        self::assertSame('mein Passwort', MailSettingsRepository::decrypt($stored, $key));
        // anderer Schlüssel (andere Installation), kein Schlüssel, beschädigter Wert: nicht lesbar
        self::assertNull(MailSettingsRepository::decrypt($stored, sodium_crypto_secretbox_keygen()));
        self::assertNull(MailSettingsRepository::decrypt($stored, null));
        self::assertNull(MailSettingsRepository::decrypt('kaputt', $key));
        self::assertSame('', MailSettingsRepository::decrypt('', null));
    }
}
