<?php

namespace FewoTk2\News;

use Concrete\Core\Package\PackageService;

/**
 * Liest und speichert die Darstellung der Neuigkeiten in der Konfiguration des Pakets
 * (application/config/generated_overrides/fewo_tk2/).
 */
class NewsSettingsRepository
{
    private const CONFIG_KEY = 'news.settings';

    private ?NewsSettings $settings = null;

    public function __construct(private readonly PackageService $packageService)
    {
    }

    public function get(): NewsSettings
    {
        return $this->settings ??= NewsSettings::fromArray((array) $this->getConfig()->get(self::CONFIG_KEY, []));
    }

    public function isSaved(): bool
    {
        return $this->getConfig()->get(self::CONFIG_KEY) !== null;
    }

    public function save(NewsSettings $settings): void
    {
        $this->getConfig()->save(self::CONFIG_KEY, $settings->toArray());
        $this->settings = $settings;
    }

    private function getConfig()
    {
        return $this->packageService->getByHandle('fewo_tk2')->getController()->getFileConfig();
    }
}
