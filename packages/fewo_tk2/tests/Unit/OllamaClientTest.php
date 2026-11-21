<?php

namespace FewoTk2\Tests\Unit;

use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\OllamaClient;
use FewoTk2\Assistant\OllamaException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Gespräch mit Ollama: Anfrage, Antwort in JSON-Zeilen, Fehler.
 */
final class OllamaClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $requests = [];

    private function client(Response|\Throwable ...$responses): OllamaClient
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));

        return new OllamaClient(new Client(['handler' => $stack]));
    }

    private function settings(array $values = []): AssistantSettings
    {
        return AssistantSettings::fromArray($values + ['enabled' => 1, 'ollamaUrl' => 'http://ollama:11434', 'model' => 'gemma3:4b']);
    }

    private static function line(string $content, bool $done = false): string
    {
        return json_encode(['model' => 'gemma3:4b', 'message' => ['role' => 'assistant', 'content' => $content], 'done' => $done]) . "\n";
    }

    public function testChatSendsTheConversationAndCollectsTheAnswer(): void
    {
        $client = $this->client(new Response(200, [], self::line('Eine Nacht ') . self::line('kostet 45 €.') . self::line('', true)));
        $messages = [['role' => 'system', 'content' => 'Regeln'], ['role' => 'user', 'content' => 'Was kostet eine Nacht?']];
        $deltas = [];

        $answer = $client->chat($this->settings(['apiKey' => 'geheim', 'contextChars' => 16000]), $messages, static function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        }, null, OllamaClient::MAX_ANSWER_TOKENS, 8192);

        self::assertSame('Eine Nacht kostet 45 €.', $answer);
        self::assertSame(['Eine Nacht ', 'kostet 45 €.'], $deltas);

        $request = $this->requests[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://ollama:11434/api/chat', (string) $request->getUri());
        self::assertSame('Bearer geheim', $request->getHeaderLine('Authorization'));
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('gemma3:4b', $body['model']);
        self::assertSame($messages, $body['messages']);
        self::assertTrue($body['stream']);
        self::assertFalse($body['think']);
        self::assertSame(8192, $body['options']['num_ctx']);
        self::assertSame(OllamaClient::MAX_ANSWER_TOKENS, $body['options']['num_predict']);
    }

    public function testAnswerWithoutFinalLineBreakAndWithoutKey(): void
    {
        $client = $this->client(new Response(200, [], self::line('Ja.') . rtrim(self::line(' Gern.', true))));

        self::assertSame('Ja. Gern.', $client->chat($this->settings(), []));
        // Ohne Angabe: das Fenster für das volle Budget
        self::assertSame(20480, json_decode((string) $this->requests[0]['request']->getBody(), true)['options']['num_ctx']);
        self::assertFalse($this->requests[0]['request']->hasHeader('Authorization'));
    }

    public function testVeryLongAnswersAreCutOff(): void
    {
        $client = $this->client(new Response(200, [], str_repeat(self::line(str_repeat('a', 1000)), 10) . self::line('', true)));

        self::assertSame(OllamaClient::MAX_ANSWER_CHARS, mb_strlen($client->chat($this->settings(), [])));
    }

    public function testReadingStopsWhenTheVisitorIsGone(): void
    {
        $client = $this->client(new Response(200, [], self::line('Erster Teil') . str_repeat(self::line(' und mehr'), 2000)));

        $answer = $client->chat($this->settings(), [], null, static fn (): bool => true);

        self::assertStringStartsWith('Erster Teil', $answer);
        self::assertLessThan(OllamaClient::MAX_ANSWER_CHARS, mb_strlen($answer));
    }

    public function testErrorsAreReportedWithTheReason(): void
    {
        $cases = [
            'HTTP status 404: model \'x\' not found' => new Response(404, [], '{"error":"model \'x\' not found"}'),
            'HTTP status 502' => new Response(502, [], '<html><body>Bad Gateway</body></html>'),
            'out of memory' => new Response(200, [], self::line('Teil') . '{"error":"out of memory"}' . "\n"),
            'empty answer' => new Response(200, [], self::line('', true)),
            'cannot be reached at http://ollama:11434' => new ConnectException('Connection refused', new Request('POST', 'http://ollama:11434/api/chat')),
        ];
        foreach ($cases as $expected => $response) {
            try {
                $this->client($response)->chat($this->settings(), []);
                self::fail('Erwartet: ' . $expected);
            } catch (OllamaException $e) {
                self::assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function testInvalidUrlIsNotRequested(): void
    {
        $client = $this->client();

        $this->expectException(OllamaException::class);
        $client->chat($this->settings(['ollamaUrl' => 'file:///etc/passwd']), []);
    }

    public function testInstalledModelsAreListedSorted(): void
    {
        $client = $this->client(new Response(200, [], json_encode(['models' => [['name' => 'qwen3:8b'], ['model' => 'gemma3:4b'], ['name' => ''], 'unsinn']])));

        self::assertSame(['gemma3:4b', 'qwen3:8b'], $client->listModels($this->settings()));
        self::assertSame('http://ollama:11434/api/tags', (string) $this->requests[0]['request']->getUri());
    }

    public function testListingModelsFailsOnAnythingButOllama(): void
    {
        foreach ([new Response(200, [], '<html>Willkommen</html>'), new Response(401, [], '{"error":"unauthorized"}')] as $response) {
            try {
                $this->client($response)->listModels($this->settings());
                self::fail('Erwartet: OllamaException');
            } catch (OllamaException $e) {
                self::assertStringContainsString('HTTP status', $e->getMessage());
            }
        }
    }
}
