<?php

namespace FewoTk2\Assistant;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Spricht mit Ollama (https://ollama.com): installierte Modelle auflisten und eine Unterhaltung führen.
 *
 * Verwendet die Ollama-eigene Schnittstelle (/api/tags, /api/chat). Die Antwort wird als Folge von
 * JSON-Zeilen gelesen, damit die ersten Wörter schon erscheinen, während das Modell noch schreibt.
 */
class OllamaClient
{
    /** Längere Antworten werden abgeschnitten – ein Chat auf einer Website braucht keine Aufsätze. */
    public const MAX_ANSWER_CHARS = 4000;
    public const MAX_ANSWER_TOKENS = 700;

    public function __construct(private readonly ClientInterface $http)
    {
    }

    /**
     * Namen der in Ollama installierten Modelle, z. B. "gemma3:4b".
     *
     * @return string[]
     */
    public function listModels(AssistantSettings $settings): array
    {
        $response = $this->request('GET', $settings, '/api/tags', ['timeout' => 8, 'connect_timeout' => 3]);
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($data) || !is_array($data['models'] ?? null)) {
            throw new OllamaException($this->describeError($response, (string) $response->getBody()));
        }
        $names = [];
        foreach ($data['models'] as $model) {
            $name = is_array($model) ? (string) ($model['name'] ?? $model['model'] ?? '') : '';
            if ($name !== '') {
                $names[] = $name;
            }
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * Schickt die Unterhaltung an das Modell und liefert seine Antwort.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param callable(string): void|null $onDelta wird mit jedem neuen Textstück aufgerufen
     * @param callable(): bool|null $shouldStop true beendet das Lesen vorzeitig (z. B. Besucher hat die Seite verlassen)
     * @param int $maxTokens Länge der Antwort in Token
     * @param int|null $contextWindow Kontextfenster in Token; null = so groß, wie es die Einstellungen höchstens erfordern
     */
    public function chat(AssistantSettings $settings, array $messages, ?callable $onDelta = null, ?callable $shouldStop = null, int $maxTokens = self::MAX_ANSWER_TOKENS, ?int $contextWindow = null): string
    {
        $deadline = microtime(true) + $settings->timeout;
        $response = $this->request('POST', $settings, '/api/chat', [
            'stream' => true,
            'timeout' => $settings->timeout,
            'read_timeout' => $settings->timeout,
            'json' => [
                'model' => $settings->model,
                'messages' => $messages,
                'stream' => true,
                // Modelle mit "Nachdenken" antworten ohne diesen Schritt deutlich schneller
                'think' => false,
                // Modell zwischen den Fragen geladen lassen, sonst dauert jede erste Antwort lange
                'keep_alive' => '30m',
                'options' => [
                    'temperature' => 0.2,
                    'num_ctx' => $contextWindow ?? $settings->getContextWindow(),
                    'num_predict' => $maxTokens,
                ],
            ],
        ]);

        $body = $response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new OllamaException($this->describeError($response, $this->readSafely($body)));
        }

        $answer = '';
        $buffer = '';
        $done = false;
        while (!$done && !$body->eof()) {
            $buffer .= $body->read(8192);
            while (!$done && ($end = strpos($buffer, "\n")) !== false) {
                $done = $this->readEvent(substr($buffer, 0, $end), $answer, $onDelta);
                $buffer = substr($buffer, $end + 1);
            }
            if (!$done && microtime(true) > $deadline) {
                $body->close();
                if ($answer === '') {
                    throw new OllamaException(sprintf('No answer within %d seconds.', $settings->timeout));
                }
                break;
            }
            if ($shouldStop !== null && $shouldStop()) {
                break;
            }
        }
        if (!$done && trim($buffer) !== '') {
            $this->readEvent($buffer, $answer, $onDelta);
        }
        // Verbindung schließen: Ollama bricht eine noch laufende Antwort dann ab
        $body->close();

        $answer = trim($answer);
        if ($answer === '') {
            throw new OllamaException('The model returned an empty answer.');
        }

        return $answer;
    }

    /**
     * Wertet eine Zeile der Antwort aus und hängt neuen Text an $answer an.
     *
     * @param callable(string): void|null $onDelta
     *
     * @return bool true, wenn die Antwort vollständig ist
     */
    private function readEvent(string $line, string &$answer, ?callable $onDelta): bool
    {
        $event = json_decode(trim($line), true);
        if (!is_array($event)) {
            return false;
        }
        if (isset($event['error'])) {
            throw new OllamaException(is_string($event['error']) ? $event['error'] : 'Ollama reported an error.');
        }
        $delta = mb_substr((string) ($event['message']['content'] ?? ''), 0, max(0, self::MAX_ANSWER_CHARS - mb_strlen($answer)));
        if ($delta !== '') {
            $answer .= $delta;
            if ($onDelta !== null) {
                $onDelta($delta);
            }
        }

        return !empty($event['done']) || mb_strlen($answer) >= self::MAX_ANSWER_CHARS;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, AssistantSettings $settings, string $path, array $options): ResponseInterface
    {
        $baseUrl = $settings->getBaseUrl();
        if ($baseUrl === null) {
            throw new OllamaException('The Ollama URL is not a valid http(s) address.');
        }
        $headers = ['Accept' => 'application/json'];
        if ($settings->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $settings->apiKey;
        }
        try {
            return $this->http->request($method, $baseUrl . $path, $options + [
                'connect_timeout' => 5,
                'http_errors' => false,
                'allow_redirects' => false,
                'headers' => $headers,
            ]);
        } catch (Throwable $e) {
            throw new OllamaException('Ollama cannot be reached at ' . $baseUrl . ': ' . $e->getMessage(), 0, $e);
        }
    }

    private function describeError(ResponseInterface $response, string $body): string
    {
        $data = json_decode($body, true);
        $detail = is_array($data) && is_string($data['error'] ?? null) ? $data['error'] : trim(mb_substr(strip_tags($body), 0, 200));
        $message = sprintf('Ollama answered with HTTP status %d%s', $response->getStatusCode(), $detail !== '' ? ': ' . $detail : '.');
        // Ollama selbst kennt keine Anmeldung: 401/403 kommen vom vorgeschalteten Proxy (z. B. Caddy mit Token)
        if (in_array($response->getStatusCode(), [401, 403], true)) {
            $message .= ' The API key is missing or wrong (the reverse proxy in front of Ollama refused the request).';
        }

        return $message;
    }

    /**
     * Anfang einer Fehlerantwort. Eine leere Antwort (etwa "401" eines Proxys ohne Text) lässt sich bei gestreamten
     * Anfragen nicht lesen – dann bleibt es beim Statuscode.
     */
    private function readSafely(\Psr\Http\Message\StreamInterface $body): string
    {
        try {
            return (string) $body->read(2000);
        } catch (Throwable) {
            return '';
        }
    }
}
