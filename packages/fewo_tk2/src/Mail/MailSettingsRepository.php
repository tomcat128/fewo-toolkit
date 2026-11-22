<?php

namespace FewoTk2\Mail;

use Concrete\Core\Package\PackageService;

/**
 * Speichert die Einstellungen des E-Mail-Versands in der Konfiguration des Pakets
 * (application/config/generated_overrides/fewo_tk2/).
 *
 * Das SMTP-Passwort liegt dort nur verschlüsselt; der Schlüssel steht getrennt davon in der Datenbank. Eine Kopie
 * der Konfigurationsdateien allein verrät das Passwort also nicht. Fehlt der Schlüssel (z. B. Dateien einer anderen
 * Installation), lässt sich das Passwort nicht mehr lesen und muss neu eingetragen werden.
 */
class MailSettingsRepository
{
    private const CONFIG_KEY = 'mail';
    private const KEY_NAME = 'mail.key';

    private ?MailSettings $settings = null;

    public function __construct(private readonly PackageService $packageService)
    {
    }

    public function get(): MailSettings
    {
        if ($this->settings === null) {
            $data = (array) $this->getFileConfig()->get(self::CONFIG_KEY . '.settings', []);
            $password = self::decrypt((string) $this->getFileConfig()->get(self::CONFIG_KEY . '.password', ''), $this->getKey(false));
            $this->settings = MailSettings::fromArray($data)->withPassword($password ?? '');
        }

        return $this->settings;
    }

    /**
     * Ein gespeichertes Passwort ist vorhanden, lässt sich aber nicht entschlüsseln.
     */
    public function isPasswordUnreadable(): bool
    {
        $stored = (string) $this->getFileConfig()->get(self::CONFIG_KEY . '.password', '');

        return $stored !== '' && self::decrypt($stored, $this->getKey(false)) === null;
    }

    public function hasPassword(): bool
    {
        return (string) $this->getFileConfig()->get(self::CONFIG_KEY . '.password', '') !== '';
    }

    public function save(MailSettings $settings): void
    {
        $this->getFileConfig()->save(self::CONFIG_KEY . '.settings', $settings->toArray());
        $this->getFileConfig()->save(self::CONFIG_KEY . '.password', $settings->password !== '' ? self::encrypt($settings->password, $this->getKey(true)) : '');
        $this->settings = $settings;
    }

    /**
     * @return string Base64 aus Nonce und verschlüsseltem Passwort
     */
    public static function encrypt(string $plain, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    /**
     * @return string|null null, wenn sich der Wert mit diesem Schlüssel nicht entschlüsseln lässt
     */
    public static function decrypt(string $stored, ?string $key): ?string
    {
        if ($stored === '') {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($key === null || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);

        return $plain === false ? null : $plain;
    }

    private function getKey(bool $create): ?string
    {
        $config = $this->getController()->getDatabaseConfig();
        $key = base64_decode((string) $config->get(self::KEY_NAME, ''), true);
        if (is_string($key) && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $key;
        }
        if (!$create) {
            return null;
        }
        $key = sodium_crypto_secretbox_keygen();
        $config->save(self::KEY_NAME, base64_encode($key));

        return $key;
    }

    private function getFileConfig()
    {
        return $this->getController()->getFileConfig();
    }

    private function getController()
    {
        return $this->packageService->getByHandle('fewo_tk2')->getController();
    }
}
