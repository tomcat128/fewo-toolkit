<?php

namespace FewoTk2\Mail;

/**
 * Wie das Anfrageformular E-Mails verschickt: über die Einstellungen von Concrete (Vorgabe) oder über ein
 * eigenes SMTP-Postfach – wie in der Mitgliederverwaltung: Host, Port, Verschlüsselung, Anmeldung und Absender.
 */
final class MailSettings
{
    /** Versand über Concrete (Verwaltung › System & Einstellungen › E-Mail). */
    public const MODE_CONCRETE = 'concrete';

    /** Versand über das hier eingetragene SMTP-Postfach. */
    public const MODE_SMTP = 'smtp';

    public const STARTTLS = 'starttls';
    public const SSL = 'ssl';
    public const NONE = 'none';

    /** Übliche Ports je Verschlüsselung. */
    public const PORTS = [self::STARTTLS => 587, self::SSL => 465, self::NONE => 25];

    public function __construct(
        public readonly string $mode = self::MODE_CONCRETE,
        public readonly string $host = '',
        public readonly int $port = 587,
        public readonly string $encryption = self::STARTTLS,
        public readonly string $username = '',
        public readonly string $password = '',
        public readonly string $fromAddress = '',
        public readonly string $fromName = '',
        public readonly string $heloDomain = '',
    ) {
    }

    /**
     * Aus gespeicherten Werten oder dem Formular; Ungültiges wird zur Vorgabe.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $encryption = in_array($data['encryption'] ?? null, array_keys(self::PORTS), true) ? (string) $data['encryption'] : self::STARTTLS;
        $port = (int) ($data['port'] ?? 0);

        return new self(
            ($data['mode'] ?? '') === self::MODE_SMTP ? self::MODE_SMTP : self::MODE_CONCRETE,
            self::line($data['host'] ?? '', 200),
            $port >= 1 && $port <= 65535 ? $port : self::PORTS[$encryption],
            $encryption,
            self::line($data['username'] ?? '', 200),
            // Passwörter nicht kürzen oder trimmen: Leerzeichen können dazugehören
            is_string($data['password'] ?? null) ? mb_substr($data['password'], 0, 200) : '',
            self::line($data['fromAddress'] ?? '', 200),
            self::line($data['fromName'] ?? '', 150),
            self::heloName($data['heloDomain'] ?? ''),
        );
    }

    /**
     * Zum Speichern – ohne Passwort, das verschlüsselt getrennt abgelegt wird.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'fromAddress' => $this->fromAddress,
            'fromName' => $this->fromName,
            'heloDomain' => $this->heloDomain,
        ];
    }

    public function withPassword(string $password): self
    {
        return new self($this->mode, $this->host, $this->port, $this->encryption, $this->username, $password, $this->fromAddress, $this->fromName, $this->heloDomain);
    }

    public function usesOwnSmtp(): bool
    {
        return $this->mode === self::MODE_SMTP;
    }

    /**
     * Eingabefehler für das Dashboard (leer = in Ordnung). Nur beim eigenen Postfach wird etwas verlangt.
     *
     * @return string[]
     */
    public function validate(): array
    {
        if (!$this->usesOwnSmtp()) {
            return [];
        }
        $errors = [];
        if ($this->host === '' || !preg_match('/^[A-Za-z0-9.\-]+$|^\[[0-9a-fA-F:.]+\]$/', $this->host)) {
            $errors[] = t('Please enter the SMTP server, e.g. smtp.example.com.');
        }
        if (filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = t('Please enter a valid sender address.');
        }
        if ($this->username === '' && $this->password !== '') {
            $errors[] = t('A password without a user name is not used. Please enter the user name as well.');
        }

        return $errors;
    }

    /**
     * Einstellungen für die SMTP-Anbindung von Concrete (Concrete\Core\Mail\Transport\Factory).
     *
     * Name für die Begrüßung (EHLO): der eingetragene, sonst $defaultHelo (die Domain der Website, wie die
     * Mitgliederverwaltung den Host aus APP_URL nimmt). Leer darf er nicht sein – Server antworten dann mit
     * "501 Syntax: HELO hostname". Nie die Domain der Absenderadresse: Damit hat GMX in der Mitgliederverwaltung Mails
     * angenommen und dann stillschweigend verworfen.
     *
     * @return array<string, int|string|null>
     */
    public function toTransportArray(string $defaultHelo = 'localhost'): array
    {
        return [
            'server' => $this->host,
            'port' => $this->port,
            'encryption' => match ($this->encryption) {
                self::SSL => 'SSL',
                self::STARTTLS => 'STARTTLS',
                default => '',
            },
            'username' => $this->username,
            'password' => $this->username !== '' ? $this->password : '',
            'messages_per_connection' => 100,
            'helo_domain' => $this->heloDomain !== '' ? $this->heloDomain : (self::heloName($defaultHelo) ?: 'localhost'),
        ];
    }

    /**
     * Gültiger Name für EHLO: ein Hostname oder eine IP-Adresse in eckigen Klammern; sonst leer.
     */
    public static function heloName(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = strtolower(trim($value, " \t\n\r\0\x0B[]"));
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return '[' . $value . ']';
        }

        return preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $value) ? $value : '';
    }

    private static function line(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)), 0, $max) : '';
    }
}
