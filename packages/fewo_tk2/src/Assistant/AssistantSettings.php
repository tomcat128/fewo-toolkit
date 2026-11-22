<?php

namespace FewoTk2\Assistant;

/**
 * Einstellungen des KI-Assistenten (Dashboard › Fewo Toolkit › KI-Assistent).
 *
 * Der Assistent beantwortet Fragen der Besucher mit einem Sprachmodell, das Ollama
 * bereitstellt – auf demselben Server oder im eigenen Netz, nicht bei einem Cloud-Dienst.
 */
final class AssistantSettings
{
    public const DEFAULT_URL = 'http://localhost:11434';

    /**
     * Wählbarer Umfang der Website-Inhalte je Frage, in Zeichen. Die Vorgabe fasst eine übliche
     * Ferienwohnungs-Website vollständig – das ist der schnelle und genaue Fall (siehe Assistant).
     */
    public const CONTEXT_OPTIONS = [8000, 16000, 32000, 48000, 64000];
    public const DEFAULT_CONTEXT = 48000;

    public const MAX_QUESTION_CHARS = 500;
    public const MAX_NAME_CHARS = 60;
    /** So viele Beispielfragen zeigt ein Chat höchstens. */
    public const MAX_EXAMPLES = 6;
    public const MAX_HINT_CHARS = 80;

    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $ollamaUrl = self::DEFAULT_URL,
        public readonly string $model = '',
        public readonly string $apiKey = '',
        public readonly string $instructions = '',
        public readonly string $excludedPaths = '',
        public readonly bool $includeApartments = true,
        public readonly int $contextChars = self::DEFAULT_CONTEXT,
        public readonly int $timeout = 120,
        public readonly int $visitorLimit = 20,
        public readonly int $siteLimit = 200,
        /** Name des Assistenten, z. B. "Emma"; leer = kein eigener Name (im Chat steht dann "Assistent") */
        public readonly string $name = '',
        /** Chat unten rechts auf der Website, unabhängig von Chat-Blöcken – auf allen oder nur auf bestimmten Seiten */
        public readonly bool $widgetEnabled = false,
        /** Kurzer Text der Schaltfläche, die den Chat öffnet, z. B. "Fragen? Ich helfe gern." */
        public readonly string $widgetHint = '',
        /** Erste Nachricht des Assistenten im Chat auf jeder Seite */
        public readonly string $widgetGreeting = '',
        /** Beispielfragen des Chats auf jeder Seite, eine pro Zeile */
        public readonly string $widgetQuestions = '',
        /** Hinweis unter dem Eingabefeld, z. B. dass eine KI antwortet */
        public readonly string $widgetNotice = '',
        /**
         * Erklärungen zu Stichwörtern (siehe KeywordNotes): Kommt ein Stichwort in einer Frage vor, bekommt das
         * Sprachmodell die Erklärung mit.
         *
         * @var array<int, array{keywords: string, text: string}>
         */
        public readonly array $notes = [],
        /** true: Der Chat unten rechts erscheint nur auf den Seiten aus $widgetPages, false: auf allen Seiten */
        public readonly bool $widgetPagesOnly = false,
        /** Pfade der Seiten mit Chat unten rechts, einer pro Zeile; gilt auch für Unterseiten, "/" ist die Startseite */
        public readonly string $widgetPages = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data z. B. aus der Konfiguration oder dem Formular; unbekannte und ungültige Werte werden bereinigt
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key, int $length): string => mb_substr(trim((string) ($data[$key] ?? '')), 0, $length);
        $int = static fn (string $key, int $default, int $min, int $max): int => is_numeric($data[$key] ?? null)
            ? max($min, min($max, (int) $data[$key]))
            : $default;
        $context = (int) ($data['contextChars'] ?? self::DEFAULT_CONTEXT);

        return new self(
            !empty($data['enabled']),
            rtrim($text('ollamaUrl', 500), '/') ?: self::DEFAULT_URL,
            $text('model', 200),
            $text('apiKey', 500),
            $text('instructions', 4000),
            implode("\n", self::lines((string) ($data['excludedPaths'] ?? ''))),
            !array_key_exists('includeApartments', $data) || !empty($data['includeApartments']),
            in_array($context, self::CONTEXT_OPTIONS, true) ? $context : self::DEFAULT_CONTEXT,
            $int('timeout', 120, 10, 600),
            $int('visitorLimit', 20, 1, 1000),
            $int('siteLimit', 200, 1, 100000),
            // einzeilig und ohne Steuerzeichen: Der Name steht im Chat und in den Regeln für das Sprachmodell
            mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F"\s]+/u', ' ', (string) ($data['name'] ?? ''))), 0, self::MAX_NAME_CHARS),
            !empty($data['widgetEnabled']),
            mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($data['widgetHint'] ?? ''))), 0, self::MAX_HINT_CHARS),
            $text('widgetGreeting', 1000),
            implode("\n", self::questionLines($data['widgetQuestions'] ?? '')),
            $text('widgetNotice', 1000),
            KeywordNotes::sanitize($data['notes'] ?? []),
            !empty($data['widgetPagesOnly']),
            implode("\n", self::lines((string) ($data['widgetPages'] ?? ''), true)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Beispielfragen eines Chats: ohne Leerzeilen und Doppelte, auf die zulässige Länge gekürzt, höchstens MAX_EXAMPLES.
     *
     * @param mixed $value Text mit einer Frage pro Zeile oder Liste der Fragen (Formularfelder)
     *
     * @return string[]
     */
    public static function questionLines(mixed $value): array
    {
        if (is_array($value)) {
            // ein Feld je Frage: Zeilenumbrüche darin trennen keine Fragen
            $value = implode("\n", array_map(static fn ($line): string => is_scalar($line) ? (string) preg_replace('/\s+/u', ' ', (string) $line) : '', $value));
        }
        $lines = [];
        foreach (preg_split('/\R/u', is_scalar($value) ? (string) $value : '') ?: [] as $line) {
            $line = mb_substr(trim($line), 0, self::MAX_QUESTION_CHARS);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return array_slice(array_values(array_unique($lines)), 0, self::MAX_EXAMPLES);
    }

    /**
     * @return string[] Beispielfragen des Chats auf jeder Seite
     */
    public function getWidgetQuestions(): array
    {
        return self::questionLines($this->widgetQuestions);
    }

    /**
     * Ist der Chat unten rechts eingeschaltet? Auf welchen Seiten er erscheint, sagt isWidgetPage().
     */
    public function isWidgetActive(): bool
    {
        return $this->widgetEnabled && $this->isReady();
    }

    /**
     * @return string[] Pfade der Seiten, auf die der Chat unten rechts beschränkt ist
     */
    public function getWidgetPages(): array
    {
        return self::lines($this->widgetPages, true);
    }

    /**
     * Gehört die Seite mit diesem Pfad zu denen, auf denen der Chat unten rechts erscheint?
     * Ohne Beschränkung jede. Sonst die genannten Seiten und ihre Unterseiten; "/" meint nur die Startseite.
     *
     * @param string $path Pfad der Seite; leer oder "/" = Startseite
     */
    public function isWidgetPage(string $path): bool
    {
        if (!$this->widgetPagesOnly) {
            return true;
        }
        $path = '/' . trim($path, '/');
        foreach ($this->getWidgetPages() as $allowed) {
            if ($path === $allowed || ($allowed !== '/' && str_starts_with($path, $allowed . '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adresse von Ollama ohne abschließenden Schrägstrich; null, wenn sie keine gültige http(s)-Adresse ist.
     */
    public function getBaseUrl(): ?string
    {
        $url = rtrim(trim($this->ollamaUrl), '/');

        return preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /**
     * Kann der Assistent Fragen beantworten?
     */
    public function isReady(): bool
    {
        return $this->enabled && $this->model !== '' && $this->getBaseUrl() !== null;
    }

    /**
     * Kontextfenster des Modells in Token (Ollama: num_ctx) für Inhalte dieses Umfangs – höchstens für das
     * eingestellte Budget. Gerechnet wird mit drei Zeichen je Token (deutscher Fließtext braucht etwa 3,5),
     * dazu Platz für Regeln, Gesprächsverlauf und Antwort. Der Wert ist auf 4096 gerundet und bleibt
     * deshalb von Frage zu Frage gleich: Ein wechselnder Wert ließe Ollama das Modell jedes Mal neu laden.
     *
     * @param int|null $contentChars Umfang aller Inhalte der Website; null = volles Budget
     */
    public function getContextWindow(?int $contentChars = null): int
    {
        $chars = min($this->contextChars, $contentChars ?? $this->contextChars);
        $tokens = (int) ceil(($chars + 6000) / 3) + 1024;

        return (int) min(32768, max(4096, ceil($tokens / 4096) * 4096));
    }

    /**
     * Pfade, deren Seiten der Assistent nicht kennt, z. B. "/impressum" (gilt auch für Unterseiten).
     *
     * @return string[]
     */
    public function getExcludedPaths(): array
    {
        return self::lines($this->excludedPaths);
    }

    public function isPathExcluded(string $path): bool
    {
        $path = '/' . trim($path, '/');
        foreach ($this->getExcludedPaths() as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $allowHome true: "/" (die Startseite) ist ein gültiger Eintrag
     *
     * @return string[] bereinigte Pfade, je einer pro Zeile der Eingabe
     */
    private static function lines(string $value, bool $allowHome = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim($line);
            // aus der Adresszeile kopiert: nur der Pfad zählt ("https://example.com/index.php/wohnung?x=1" -> "/wohnung")
            if (preg_match('~^https?://[^/]+(/[^?#]*)?~i', $line, $match)) {
                $line = $match[1] ?? '/';
            }
            $line = (string) preg_replace('~^/?index\.php(?=/|$)~', '', $line);
            if ($line === '') {
                continue;
            }
            $line = '/' . trim(mb_substr($line, 0, 255), '/');
            if ($line !== '/' || $allowHome) {
                $lines[] = $line;
            }
        }

        return array_values(array_unique($lines));
    }
}
