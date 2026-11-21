<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use DateTimeImmutable;
use FewoTk2\Assistant\Assistant as AssistantService;
use FewoTk2\Assistant\AssistantException;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\KnowledgeBase;
use FewoTk2\Assistant\OllamaClient;
use FewoTk2\Assistant\OllamaException;
use FewoTk2\Assistant\SettingsRepository;
use FewoTk2\Assistant\UsageLimiter;

/**
 * Dashboard › Fewo Toolkit › KI-Assistent: Verbindung zu Ollama, Modell, Wissen und Grenzen des Chats.
 */
class Assistant extends DashboardPageController
{
    private const TEST_RESULT = 'fewo_assistant_test';

    public function view()
    {
        $settings = $this->app->make(SettingsRepository::class)->get();
        $models = [];
        $connectionError = null;
        if ($settings->getBaseUrl() === null) {
            $connectionError = t('Please enter a valid Ollama URL (http://… or https://…).');
        } else {
            try {
                $models = $this->app->make(OllamaClient::class)->listModels($settings);
            } catch (OllamaException $e) {
                $connectionError = $e->getMessage();
            }
        }

        $contextOptions = [];
        foreach (AssistantSettings::CONTEXT_OPTIONS as $chars) {
            $contextOptions[$chars] = t('%s characters', number_format($chars, 0, ',', '.'));
        }
        $limiter = $this->app->make(UsageLimiter::class);
        $now = new DateTimeImmutable();

        $this->set('settings', $settings);
        $this->set('models', $models);
        $this->set('connectionError', $connectionError);
        $this->set('contextOptions', $contextOptions);
        $this->set('overview', $this->app->make(KnowledgeBase::class)->getOverview());
        $this->set('questionsLastHour', $limiter->count($now->modify('-1 hour')));
        $this->set('questionsLastDay', $limiter->count($now->modify('-1 day')));
        // Ergebnis einer Testfrage (siehe ask())
        $test = (array) $this->app->make('session')->getFlashBag()->get(self::TEST_RESULT, []);
        $this->set('testQuestion', (string) ($test['question'] ?? ''));
        $this->set('testAnswer', $test['answer'] ?? null);
        $this->set('testError', $test['error'] ?? null);
        $this->set('testSeconds', $test['seconds'] ?? null);
    }

    public function save()
    {
        if (!$this->token->validate('fewo_assistant_save')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $repository = $this->app->make(SettingsRepository::class);
        $current = $repository->get();
        $input = $this->request->request->all();
        // Der Schlüssel wird nie ins Formular zurückgegeben: leer lassen behält ihn, das Häkchen löscht ihn
        if (!empty($input['clearApiKey'])) {
            $input['apiKey'] = '';
        } elseif (trim((string) ($input['apiKey'] ?? '')) === '') {
            $input['apiKey'] = $current->apiKey;
        }
        $input['includeApartments'] = !empty($input['includeApartments']);
        $settings = AssistantSettings::fromArray($input);

        if ($settings->getBaseUrl() === null) {
            $this->error->add(t('Please enter a valid Ollama URL (http://… or https://…).'));
        }
        if ($settings->enabled && $settings->model === '') {
            $this->error->add(t('Please choose a model before switching on the assistant.'));
        }
        if ($this->error->has()) {
            $this->view();
            $this->set('settings', $settings);

            return null;
        }

        $repository->save($settings);
        // Ausgeschlossene Seiten können sich geändert haben
        $this->app->make(KnowledgeBase::class)->clear();
        $this->flash('success', t('The settings have been saved.'));

        return $this->buildRedirect($this->action('view'));
    }

    public function refresh()
    {
        if ($this->token->validate('fewo_assistant_refresh')) {
            $this->app->make(KnowledgeBase::class)->clear();
            $this->flash('success', t('The content of the website has been read again.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    /**
     * Testfrage: wie im Chat, aber ohne Begrenzung und mit der technischen Fehlermeldung. Das Ergebnis
     * wird nach einer Weiterleitung angezeigt – ein erneutes Laden der Seite fragt so nicht noch einmal.
     */
    public function ask()
    {
        if (!$this->token->validate('fewo_assistant_ask')) {
            return $this->buildRedirect($this->action('view'));
        }
        $result = ['question' => trim((string) $this->request->request->get('testQuestion', ''))];
        $started = microtime(true);
        try {
            $prepared = $this->app->make(AssistantService::class)->prepare($result['question'], [], null);
            $result['answer'] = $this->app->make(OllamaClient::class)->chat($prepared->settings, $prepared->messages, null, null, OllamaClient::MAX_ANSWER_TOKENS, $prepared->contextWindow);
        } catch (AssistantException $e) {
            $result['error'] = $e->reason === AssistantException::DISABLED ? t('Please enter the Ollama URL and choose a model first.') : $e->getMessage();
        } catch (OllamaException $e) {
            $result['error'] = $e->getMessage();
        }
        $result['seconds'] = round(microtime(true) - $started, 1);
        $this->app->make('session')->getFlashBag()->set(self::TEST_RESULT, $result);

        return $this->buildRedirect((string) $this->action('view') . '#fewo-assistant-test');
    }
}
