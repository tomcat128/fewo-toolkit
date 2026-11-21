<?php

namespace FewoTk2\Assistant;

use Concrete\Core\Application\Application;
use Concrete\Core\Localization\Localization;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Der KI-Assistent der Website: beantwortet eine Frage auf Grundlage der aktuellen Inhalte.
 *
 * Ablauf: Grenzen prüfen, Inhalte zusammenstellen, das Sprachmodell (Ollama) fragen.
 * Fragen und Antworten werden nicht gespeichert.
 *
 * Passen alle Inhalte der Website in das eingestellte Budget, bekommt das Modell immer alles in
 * derselben Reihenfolge. Das ist genauer als eine Auswahl – und schneller: Ollama verarbeitet den
 * gleichbleibenden Anfang nur einmal, danach beginnt jede Antwort nach wenigen Sekunden. Ist die
 * Website größer, werden je Frage die passendsten Abschnitte ausgewählt (siehe Retriever).
 */
class Assistant
{
    private const WARM_CACHE_KEY = 'fewo_tk2/assistant/warm';

    /** Ollama behält das Modell 30 Minuten im Speicher (siehe OllamaClient); etwas früher wird neu vorgewärmt. */
    private const WARM_SECONDS = 1500;

    public function __construct(
        private readonly Application $app,
        private readonly SettingsRepository $settingsRepository,
        private readonly KnowledgeBase $knowledgeBase,
        private readonly OllamaClient $ollama,
        private readonly UsageLimiter $limiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isReady(): bool
    {
        return $this->settingsRepository->get()->isReady();
    }

    /**
     * Prüft Frage und Grenzen und stellt alles für die Antwort zusammen. Danach kann die Sitzung des
     * Besuchers geschlossen und answer() aufgerufen werden.
     *
     * @param array<int, mixed> $history frühere Nachrichten des Gesprächs aus dem Browser
     * @param string|null $ipAddress Adresse des Besuchers für die Begrenzung; null = Testfrage aus dem Dashboard
     *                               (zählt nicht mit und geht auch, solange der Assistent noch ausgeschaltet ist)
     *
     * @throws AssistantException mit einer Meldung für den Besucher
     */
    public function prepare(string $question, array $history, ?string $ipAddress): PreparedQuestion
    {
        $settings = $this->settingsRepository->get();
        if ($ipAddress === null ? ($settings->model === '' || $settings->getBaseUrl() === null) : !$settings->isReady()) {
            throw new AssistantException(t('The assistant is currently not available.'), AssistantException::DISABLED);
        }
        $question = PromptBuilder::question($question);
        if (mb_strlen($question) < 2) {
            throw new AssistantException(t('Please enter your question.'), AssistantException::INVALID);
        }
        if ($ipAddress !== null && !$this->limiter->register($ipAddress, $settings)) {
            throw new AssistantException(t('The assistant is very busy at the moment. Please try again later or contact us directly.'), AssistantException::LIMIT);
        }

        [$pages, $facts, $complete, $allPages, $window] = $this->getContext($settings, $question);
        $calculation = $settings->includeApartments ? $this->calculateStay($question, $history) : '';
        // Erklärungen zu Stichwörtern: aus der Frage und – für Nachfragen wie "Und was kostet der?" – der Frage davor
        $previous = array_values(array_filter(PromptBuilder::history($history), static fn (array $message): bool => $message['role'] === 'user'));
        $notes = KeywordNotes::match($settings->notes, $question, $previous !== [] ? $previous[count($previous) - 1]['content'] : '');

        return new PreparedQuestion(
            $settings,
            PromptBuilder::build($this->getSiteName(), Localization::activeLocale(), $this->getToday(), $settings->instructions, $pages, $facts, $history, $question, $settings->name, $calculation, $notes),
            Retriever::sources($question, $allPages),
            $complete,
            $window
        );
    }

    /**
     * Angaben des Buchungssystems zur Frage, die das Sprachmodell nur wiedergeben soll.
     * Nennt die Frage einen Aufenthalt ("vom 20. bis 27.12. zu dritt"), rechnet der Server dessen Preis aus –
     * mit Saisonwechsel und Endreinigung, wie der Preisrechner. Das Sprachmodell gibt die Zahlen nur wieder.
     * Bei einer Preisfrage ohne Datum ("Was würde der Aufenthalt dann kosten?") gilt der Zeitraum, der im
     * Gespräch zuletzt genannt wurde – vom Besucher oder in einer Antwort.
     *
     * @param array<int, mixed> $history
     */
    private function calculateStay(string $question, array $history): string
    {
        $today = $this->getToday();
        $stay = StayParser::parse($question, $today);
        // "Was kostet eine Nacht im Dezember?": die Preise dieses Monats, mit beiden Saisons bei einem Wechsel
        $months = $stay->arrival === null && StayParser::isMonthPriceQuestion($question) ? StayParser::months($question, $today) : [];
        if ($months !== []) {
            return $this->knowledgeBase->getMonthPrices($months);
        }
        if (!$stay->hasDates() || $stay->guests === null) {
            // ohne Datum in der Frage: nur, wenn sie sich auf den Aufenthalt aus dem bisherigen Gespräch bezieht
            if ($stay->arrival === null && !StayParser::isFollowUpPriceQuestion($question)) {
                return '';
            }
            foreach (array_reverse(PromptBuilder::history($history)) as $message) {
                $stay = $stay->completedBy(StayParser::parse($message['content'], $today));
                if ($stay->hasDates() && $stay->guests !== null) {
                    break;
                }
            }
        }
        $departure = $stay->getDeparture();
        if ($stay->arrival === null || $departure === null || $departure <= $stay->arrival) {
            return '';
        }

        return $this->knowledgeBase->getStayCalculation($stay->arrival, $departure, $stay->guests);
    }

    /**
     * Lässt Ollama die Website-Inhalte schon einmal verarbeiten, bevor jemand fragt (Aufruf beim Anzeigen
     * des Chats). Ollama merkt sich den verarbeiteten Anfang – die erste Frage muss dann nicht mehr auf
     * das Einlesen warten. Höchstens einmal je WARM_SECONDS und nur, wenn sich seitdem etwas geändert hat
     * oder das Modell inzwischen entladen sein könnte.
     *
     * @return bool true, wenn Ollama angesprochen wurde
     */
    public function warmUp(): bool
    {
        $settings = $this->settingsRepository->get();
        if (!$settings->isReady()) {
            return false;
        }
        [$pages, $facts, $complete, , $window] = $this->getContext($settings, '');
        if (!$complete) {
            // Die Inhalte wechseln mit jeder Frage: nichts, was sich vorab verarbeiten ließe
            return false;
        }
        $system = PromptBuilder::system($this->getSiteName(), Localization::activeLocale(), $this->getToday(), $settings->instructions, $pages, $facts, $settings->name);
        if (!$this->markWarm($settings, $system, $window, true)) {
            return false;
        }
        try {
            $this->ollama->chat($settings, [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => 'Hallo']], null, null, 1, $window);
        } catch (Throwable) {
            // Nur eine Vorbereitung: Fehler zeigen sich bei der ersten echten Frage und stehen dann im Log
        }

        return true;
    }

    /**
     * Fragt das Sprachmodell.
     *
     * @param callable(string): void|null $onDelta wird mit jedem neuen Textstück der Antwort aufgerufen
     * @param callable(): bool|null $shouldStop true bricht ab (Besucher hat die Seite verlassen)
     *
     * @throws AssistantException mit einer Meldung für den Besucher; der Grund steht im Log
     */
    public function answer(PreparedQuestion $prepared, ?callable $onDelta = null, ?callable $shouldStop = null): string
    {
        try {
            $answer = $this->ollama->chat($prepared->settings, $prepared->messages, $onDelta, $shouldStop, OllamaClient::MAX_ANSWER_TOKENS, $prepared->contextWindow);
            if ($prepared->complete) {
                // Ollama hat die Inhalte jetzt verarbeitet: Vorwärmen ist vorerst unnötig
                $this->markWarm($prepared->settings, $prepared->messages[0]['content'], (int) $prepared->contextWindow, false);
            }

            return $answer;
        } catch (Throwable $e) {
            $this->logger->error('Fewo Toolkit: the assistant could not answer: {error}', ['error' => $e->getMessage()]);

            throw new AssistantException(t('The assistant cannot answer at the moment. Please try again later or contact us directly.'));
        }
    }

    /**
     * Inhalte für eine Frage: Preise und freie Zeiträume sind immer dabei, der Rest des Budgets gehört den Seiten.
     *
     * @return array{0: Chunk[], 1: Chunk[], 2: bool, 3: Chunk[], 4: int} ausgewählte Seitenabschnitte, Angaben aus Fewo-Office,
     *                                                                   "alles enthalten", alle Seitenabschnitte,
     *                                                                   Kontextfenster in Token
     */
    private function getContext(AssistantSettings $settings, string $question): array
    {
        $allPages = $this->knowledgeBase->getPageChunks();
        $facts = $settings->includeApartments ? $this->knowledgeBase->getApartmentChunks() : [];
        $length = static fn (array $chunks): int => (int) array_sum(array_map(static fn (Chunk $chunk) => $chunk->length(), $chunks));
        $pages = Retriever::select($question, $allPages, max(1000, $settings->contextChars - $length($facts)));

        return [$pages, $facts, count($pages) === count($allPages), $allPages, $settings->getContextWindow($length($facts) + $length($allPages))];
    }

    /**
     * Merkt sich, wann Ollama welchen Stand der Inhalte zuletzt verarbeitet hat.
     *
     * @param bool $onlyIfDue true: nur vermerken (und true liefern), wenn ein Vorwärmen fällig ist
     */
    private function markWarm(AssistantSettings $settings, string $system, int $contextWindow, bool $onlyIfDue): bool
    {
        $hash = sha1($settings->ollamaUrl . '|' . $settings->model . '|' . $contextWindow . '|' . $system);
        $cache = $this->app->make('cache/expensive');
        $item = $cache->getItem(self::WARM_CACHE_KEY);
        $state = $item->isMiss() ? null : $item->get();
        if ($onlyIfDue && is_array($state) && ($state['hash'] ?? '') === $hash && ($state['time'] ?? 0) > time() - self::WARM_SECONDS) {
            return false;
        }
        $cache->save($item->set(['hash' => $hash, 'time' => time()])->expiresAfter(self::WARM_SECONDS));

        return true;
    }

    private function getSiteName(): string
    {
        $site = $this->app->make('site')->getSite();

        return $site !== null ? (string) $site->getSiteName() : '';
    }

    private function getToday(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    }
}
