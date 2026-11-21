<?php

namespace FewoTk2\Assistant;

use Concrete\Core\Application\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Beantwortet die Aufrufe eines Chats – für den Block "Chat" und für den Chat auf jeder Seite.
 * Das Token prüft der Aufrufer: Es hängt davon ab, woher die Frage kommt.
 */
class ChatResponder
{
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Beantwortet eine Frage. Die Antwort wird Zeile für Zeile als JSON geschickt, während das Modell schreibt:
     * {"delta": "…"} für neuen Text, am Ende {"done": true, "sources": […]} oder {"error": "…"}.
     */
    public function ask(Request $request): Response
    {
        $assistant = $this->app->make(Assistant::class);
        $input = $request->request;
        $history = json_decode((string) $input->get('history', '[]'), true);
        try {
            $prepared = $assistant->prepare((string) $input->get('question', ''), is_array($history) ? $history : [], (string) $request->getClientIp());
        } catch (AssistantException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getHttpStatus());
        }
        $this->releaseSession();

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
     * Aufruf beim Anzeigen bzw. Öffnen des Chats: Ollama verarbeitet die Website-Inhalte schon einmal, damit die
     * erste Frage nicht darauf warten muss (siehe Assistant::warmUp()). Kostet nichts, wenn das eben erst geschehen ist.
     */
    public function warm(): Response
    {
        $this->releaseSession();
        @set_time_limit(600);

        return new JsonResponse(['ok' => true, 'warmed' => $this->app->make(Assistant::class)->warmUp()]);
    }

    /**
     * Sitzung freigeben: Die Antwort kann dauern, andere Seitenaufrufe des Besuchers sollen nicht warten.
     */
    private function releaseSession(): void
    {
        $session = $this->app->make('session');
        if ($session->isStarted()) {
            $session->save();
        }
    }
}
