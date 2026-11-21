<?php

namespace Concrete\Package\FewoTk2\Block\FewoChat;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Support\Facade\Url;
use FewoTk2\Assistant\Assistant;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\ChatResponder;
use FewoTk2\Assistant\SettingsRepository;
use FewoTk2\Block\AddDefaults;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Chat mit dem KI-Assistenten der Website. Die Antworten erstellt ein Sprachmodell über Ollama
 * (Dashboard › Fewo Toolkit › KI-Assistent) aus den aktuellen Inhalten der Website.
 */
class Controller extends BlockController
{
    use AddDefaults;

    public $title = '';
    public $welcomeMessage = '';
    public $exampleQuestions = '';
    public $notice = '';

    protected $btTable = 'btFewoChat';
    protected $btInterfaceWidth = 640;
    protected $btInterfaceHeight = 560;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    // Enthält ein Token und hängt von den Einstellungen des Assistenten ab: nicht zwischenspeichern
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('Chat (AI Assistant)');
    }

    public function getBlockTypeDescription()
    {
        return t('Visitors ask questions and get answers based on the content of this website. The answers come from a local language model (Ollama).');
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->set('title', t('Questions? Ask our assistant'));
        $this->set('welcomeMessage', t('Hello! I am the digital assistant of this website. Ask me about amenities, prices, availability or the surroundings.'));
        $this->set('exampleQuestions', implode("\n", [
            t('What does a night cost?'),
            t('When is the apartment available?'),
            t('What amenities does the apartment have?'),
        ]));
        $this->set('notice', t('The answers are generated automatically by an AI and may contain errors. Only our offer is binding.'));
        $this->edit();
    }

    public function edit()
    {
        $this->set('assistantReady', $this->app->make(Assistant::class)->isReady());
        $this->set('assistantName', $this->app->make(SettingsRepository::class)->get()->name);
        $this->set('maxExamples', AssistantSettings::MAX_EXAMPLES);
        $this->set('maxLength', AssistantSettings::MAX_QUESTION_CHARS);
        $this->set('assistantUrl', (string) Url::to('/dashboard/fewo_tk2/assistant'));
    }

    public function view()
    {
        $page = Page::getCurrentPage();
        $this->set('ready', $this->app->make(Assistant::class)->isReady());
        $this->set('canEdit', $page instanceof Page && !$page->isError() && (new Checker($page))->canEditPageContents());
        $this->set('assistantUrl', (string) Url::to('/dashboard/fewo_tk2/assistant'));
        $this->set('examples', AssistantSettings::questionLines((string) $this->exampleQuestions));
        // Name aus den Einstellungen des Assistenten; ohne Namen heißt er im Chat "Assistent"
        $this->set('assistantName', $this->app->make(SettingsRepository::class)->get()->name ?: t('Assistant'));
        $this->set('tokenAction', $this->getTokenAction());
        $this->set('actionUrl', (string) $this->getActionURL('ask'));
        $this->set('warmUrl', (string) $this->getActionURL('warm'));
        $this->set('maxLength', AssistantSettings::MAX_QUESTION_CHARS);
    }

    /**
     * Beantwortet eine Frage. Die Antwort wird Zeile für Zeile als JSON geschickt, während das Modell schreibt:
     * {"delta": "…"} für neuen Text, am Ende {"done": true, "sources": […]} oder {"error": "…"}.
     */
    public function action_ask($bID = null)
    {
        if ((int) $bID !== (int) $this->bID || !$this->request->isMethod('POST')) {
            // Concrete ruft die Aktion bei allen Chat-Blöcken der Seite auf und überspringt danach view()
            $this->view();

            return null;
        }
        $input = $this->request->request;
        if (!$this->app->make('token')->validate($this->getTokenAction(), (string) $input->get('fewo_token', ''))) {
            return new JsonResponse(['error' => t('The chat has expired. Please reload the page.')], 403);
        }

        return $this->app->make(ChatResponder::class)->ask($this->request);
    }

    /**
     * Aufruf beim Anzeigen des Chats: Ollama verarbeitet die Website-Inhalte schon einmal, damit die erste
     * Frage nicht darauf warten muss (siehe Assistant::warmUp()). Kostet nichts, wenn das eben erst geschehen ist.
     */
    public function action_warm($bID = null)
    {
        if ((int) $bID !== (int) $this->bID || !$this->request->isMethod('POST')) {
            $this->view();

            return null;
        }
        if (!$this->app->make('token')->validate($this->getTokenAction(), (string) $this->request->request->get('fewo_token', ''))) {
            return new JsonResponse(['ok' => false], 403);
        }

        return $this->app->make(ChatResponder::class)->warm();
    }

    public function save($args)
    {
        // Das Formular schickt die Fragen einzeln (exampleQuestion[]); als Text mit einer Frage pro Zeile
        // kommen sie z. B. aus Skripten
        $questions = AssistantSettings::questionLines($args['exampleQuestion'] ?? ($args['exampleQuestions'] ?? ''));
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'welcomeMessage' => trim((string) ($args['welcomeMessage'] ?? '')),
            'exampleQuestions' => implode("\n", $questions),
            'notice' => trim((string) ($args['notice'] ?? '')),
        ]);
    }

    private function getTokenAction(): string
    {
        return 'fewo_chat_' . (int) $this->bID;
    }
}
