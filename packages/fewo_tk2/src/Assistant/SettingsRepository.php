<?php

namespace FewoTk2\Assistant;

use Concrete\Core\Package\PackageService;

/**
 * Liest und speichert die Einstellungen des Assistenten in der Konfiguration des Pakets
 * (application/config/generated_overrides/fewo_tk2/).
 */
class SettingsRepository
{
    private const CONFIG_KEY = 'assistant';

    private ?AssistantSettings $settings = null;

    public function __construct(private readonly PackageService $packageService)
    {
    }

    public function get(): AssistantSettings
    {
        return $this->settings ??= AssistantSettings::fromArray((array) $this->getConfig()->get(self::CONFIG_KEY . '.settings', []));
    }

    public function save(AssistantSettings $settings): void
    {
        $this->getConfig()->save(self::CONFIG_KEY . '.settings', $settings->toArray());
        $this->settings = $settings;
    }

    /**
     * Zufallswert dieser Website, mit dem IP-Adressen vor dem Speichern unkenntlich gemacht werden.
     */
    public function getSalt(): string
    {
        $salt = (string) $this->getConfig()->get(self::CONFIG_KEY . '.salt', '');
        if ($salt === '') {
            $salt = bin2hex(random_bytes(16));
            $this->getConfig()->save(self::CONFIG_KEY . '.salt', $salt);
        }

        return $salt;
    }

    private function getConfig()
    {
        return $this->packageService->getByHandle('fewo_tk2')->getController()->getFileConfig();
    }
}
