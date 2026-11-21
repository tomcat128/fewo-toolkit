<?php

namespace Concrete\Package\FewoTk2\Block\FewoChat;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Support\Facade\Url;
use FewoTk2\Assistant\Assistant;
use FewoTk2\Assistant\AssistantException;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Block\AddDefaults;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $this->set('assistantUrl', (string) Url::to('/dashboard/fewo_tk2/assistant'));
    }

    public function view()
    {
        $page = Page::getCurrentPage();
        $this->set('ready', $this->app->make(Assistant::class)->isReady());
        $this->set('canEdit', $page instanceof Page && !$page->isError() && (new Checker($page))->canEditPageContents());
        $this->set('assistantUrl', (string) Url::to('/dashboard/fewo_tk2/assistant'));
        $this->set('examples', self::lines((string) $this->exampleQuestions));
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

        $assistant = $this->app->make(Assistant::class);
        $history = json_decode((string) $input->get('history', '[]'), true);
        try {
            $prepared = $assistant->prepare((string) $input->get('question', ''), is_array($history) ? $history : [], (string) $this->request->getClientIp());
        } catch (AssistantException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        // Sitzung freigeben: Die Antwort kann dauern, andere Seitenaufrufe des Besuchers sollen nicht warten
        $session = $this->app->make('session');
        if ($session->isStarted()) {
            $session->save();
        }

        return new StreamedResponse(static function () use ($assistant, $prepared) {
            @set_time_limit($prepared->settings->timeout + 30);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $send = static function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
                flush();
            };
            try {
                $assistant->answer(
                    $prepared,
                    static fn (string $delta) => $send(['delta' => $delta]),
                    static fn (): bool => connection_aborted() === 1
                );
                $send(['done' => true, 'sources' => $prepared->sources]);
            } catch (AssistantException $e) {
                $send(['error' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Cache-Control' => 'no-store',
            // Reverse-Proxys (nginx) sollen die Antwort nicht sammeln, sondern sofort weiterreichen
            'X-Accel-Buffering' => 'no',
        ]);
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
        $session = $this->app->make('session');
        if ($session->isStarted()) {
            $session->save();
        }
        @set_time_limit(600);

        return new JsonResponse(['ok' => true, 'warmed' => $this->app->make(Assistant::class)->warmUp()]);
    }

    public function save($args)
    {
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'welcomeMessage' => trim((string) ($args['welcomeMessage'] ?? '')),
            'exampleQuestions' => implode("\n", self::lines((string) ($args['exampleQuestions'] ?? ''))),
            'notice' => trim((string) ($args['notice'] ?? '')),
        ]);
    }

    /**
     * @return string[] höchstens sechs Beispielfragen, je eine pro Zeile
     */
    private static function lines(string $value): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = mb_substr(trim($line), 0, AssistantSettings::MAX_QUESTION_CHARS);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return array_slice(array_values(array_unique($lines)), 0, 6);
    }

    private function getTokenAction(): string
    {
        return 'fewo_chat_' . (int) $this->bID;
    }
}
