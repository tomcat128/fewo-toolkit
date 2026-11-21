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
        // Texte des Chats auf jeder Seite: Vorgaben, solange sie noch nie gespeichert wurden (leer gespeichert bleibt leer)
        return $this->settings ??= AssistantSettings::fromArray((array) $this->getConfig()->get(self::CONFIG_KEY . '.settings', []) + self::widgetDefaults());
    }

    public function save(AssistantSettings $settings): void
    {
        $this->getConfig()->save(self::CONFIG_KEY . '.settings', $settings->toArray());
        $this->settings = $settings;
    }

    /**
     * @return array<string, string>
     */
    public static function widgetDefaults(): array
    {
        return [
            'widgetHint' => t('Questions? I am happy to help.'),
            'widgetGreeting' => t('Hello! I am the digital assistant of this website. Ask me about amenities, prices, availability or the surroundings.'),
            'widgetQuestions' => implode("\n", [
                t('What does a night cost?'),
                t('When is the apartment available?'),
                t('What amenities does the apartment have?'),
            ]),
            'widgetNotice' => t('The answers are generated automatically by an AI and may contain errors. Only our offer is binding.'),
        ];
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
