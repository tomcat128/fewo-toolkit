<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use FewoTk2\News\ImportanceLevels;
use FewoTk2\News\NewsSettings;
use FewoTk2\News\NewsSettingsRepository;

/**
 * Darstellung der Neuigkeiten für alle Blöcke "Neuigkeiten": Stufen der Wichtigkeit und Gültigkeitszeitraum.
 */
class News extends DashboardPageController
{
    /** Eingaben nach einem Fehler, die das Formular erneut zeigt. */
    private ?NewsSettings $formSettings = null;

    public function view()
    {
        $this->set('settings', $this->formSettings ?? $this->app->make(NewsSettingsRepository::class)->get());
    }

    public function save()
    {
        $post = $this->request->request;
        if (!$this->token->validate('fewo_news_save')) {
            $this->error->add($this->token->getErrorMessage());
        }
        foreach (NewsSettings::validateCodes($post->all()['levelCode'] ?? []) as $message) {
            $this->error->add($message);
        }
        $all = $post->all();
        $levels = ImportanceLevels::fromForm($all['levelCode'] ?? [], $all['levelLabel'] ?? [], $all['levelColor'] ?? []);
        $settings = new NewsSettings($levels !== [] ? $levels : ImportanceLevels::defaults(), (bool) $post->get('showPeriod'));
        if ($this->error->has()) {
            // Zeilen so zeigen, wie sie eingegeben wurden – auch die fehlerhafte, damit sie sich korrigieren lässt
            $rows = [];
            foreach (array_values((array) ($all['levelCode'] ?? [])) as $i => $code) {
                $rows[] = [
                    'code' => (string) $code,
                    'label' => (string) (array_values((array) ($all['levelLabel'] ?? []))[$i] ?? ''),
                    'color' => ImportanceLevels::colors((string) (array_values((array) ($all['levelColor'] ?? []))[$i] ?? ''))['color'],
                ];
            }
            $this->formSettings = new NewsSettings($rows, $settings->showPeriod);
            $this->view();

            return null;
        }
        $this->app->make(NewsSettingsRepository::class)->save($settings);
        $this->flash('success', t('The settings for the news have been saved.'));

        return $this->buildRedirect($this->action('view'));
    }
}
