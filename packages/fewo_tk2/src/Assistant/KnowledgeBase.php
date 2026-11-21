<?php

namespace FewoTk2\Assistant;

use Concrete\Core\Application\Application;
use Concrete\Core\Block\Block;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Sitemap\PageListGenerator;
use Concrete\Core\Support\Facade\Url;
use DateTimeImmutable;
use DateTimeZone;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Calendar\BookingReader;
use FewoTk2\Calendar\Occupancy;
use FewoTk2\Calendar\OccupancyLoader;
use FewoTk2\Feed\FeedLoader;
use FewoTk2\Pricing\PricelistLoader;
use Throwable;

/**
 * Das Wissen des Assistenten: die Inhalte der öffentlichen Seiten und – aus Fewo-Office – Preise
 * und freie Zeiträume der im Dashboard hinterlegten Wohnungen.
 *
 * Seiten: dieselben, die Concrete in die sitemap.xml aufnimmt (veröffentlicht, für Gäste sichtbar,
 * keine Systemseiten), mit den Texten, die ihre Blöcke für die Suche bereitstellen. Der Stand wird
 * kurz zwischengespeichert und verworfen, sobald eine Seite freigegeben, verschoben oder gelöscht wird.
 */
class KnowledgeBase
{
    private const CACHE_KEY = 'fewo_tk2/assistant/pages';
    private const CACHE_SECONDS = 600;
    private const CHUNK_CHARS = 900;

    /** Blöcke ohne eigenen Inhalt (Navigation, Formulare, der Chat selbst). */
    private const SKIPPED_BLOCKS = ['fewo_chat', 'fewo_kontaktformular', 'autonav', 'search', 'form', 'express_form', 'switch_language', 'core_area_layout'];

    public function __construct(
        private readonly Application $app,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * Inhalte der Seiten in Abschnitten, in der Reihenfolge der Seitenpfade (Startseite zuerst).
     *
     * @return Chunk[]
     */
    public function getPageChunks(): array
    {
        $cache = $this->app->make('cache/expensive');
        $item = $cache->getItem(self::CACHE_KEY);
        $rows = $item->isMiss() ? null : $item->get();
        if (!is_array($rows)) {
            $rows = $this->collectPages();
            $cache->save($item->set($rows)->expiresAfter(self::CACHE_SECONDS));
        }

        $chunks = [];
        foreach ($rows as [$title, $url, $text]) {
            foreach (TextTools::split($text, self::CHUNK_CHARS) as $part) {
                $chunks[] = new Chunk($title, $url, $part);
            }
        }

        return $chunks;
    }

    /**
     * Preise und freie Zeiträume je Wohnung. Nicht zwischengespeichert: Die Daten aus Fewo-Office
     * haben ihren eigenen Zwischenspeicher (siehe FeedLoader) und sind so immer auf dessen Stand.
     *
     * @return Chunk[]
     */
    public function getApartmentChunks(): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get()));
        $chunks = [];
        foreach ($this->app->make(ApartmentRepository::class)->findAll() as $apartment) {
            $parts = [];
            try {
                $pricelist = $apartment->getPricelistUrl() !== ''
                    ? $this->app->make(PricelistLoader::class)->load($apartment->getPricelistUrl(), $apartment->getRefreshMinutes())->pricelist
                    : null;
                if ($pricelist !== null) {
                    $parts[] = ApartmentFactSheet::prices($pricelist, $today);
                }
                $url = FeedLoader::normalizeUrl($apartment->getOccupancyUrl());
                $body = $url !== null ? $this->app->make(OccupancyLoader::class)->load($url, $apartment->getRefreshMinutes())->getBody() : null;
                if ($body !== null) {
                    $bookings = (new BookingReader($today->getTimezone()))->read($body, $today->modify('-1 day'), $today->modify('+' . (ApartmentFactSheet::MONTHS_AHEAD + 1) . ' months'));
                    $parts[] = ApartmentFactSheet::availability(new Occupancy($bookings), $today, $pricelist?->minNights ?? 1);
                }
            } catch (Throwable) {
                // Daten nicht lesbar: Der Assistent kennt diese Angaben dann nicht und sagt das auch
            }
            $text = trim(implode("\n", array_filter($parts)));
            if ($text !== '') {
                $chunks[] = new Chunk(t('Prices and availability: %s', $apartment->getName()), '', $text);
            }
        }

        return $chunks;
    }

    /**
     * Verwirft den zwischengespeicherten Stand der Seiten; er wird bei der nächsten Frage neu gelesen.
     */
    public function clear(): void
    {
        $this->app->make('cache/expensive')->delete(self::CACHE_KEY);
    }

    /**
     * Übersicht für das Dashboard: was der Assistent kennt.
     *
     * @return array<int, array{title: string, url: string, chars: int}>
     */
    public function getOverview(): array
    {
        $overview = [];
        foreach (array_merge($this->getApartmentChunks(), $this->getPageChunks()) as $chunk) {
            $key = $chunk->title . '|' . $chunk->url;
            $overview[$key] ??= ['title' => $chunk->title, 'url' => $chunk->url, 'chars' => 0];
            $overview[$key]['chars'] += mb_strlen($chunk->text);
        }

        return array_values($overview);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}> je Seite [Titel, Adresse (Pfad), Text]
     */
    private function collectPages(): array
    {
        $settings = $this->settingsRepository->get();
        $pages = [];
        foreach ($this->app->make(PageListGenerator::class)->generatePageList() as $page) {
            $path = (string) $page->getCollectionPath();
            if ($settings->isPathExcluded($path) || $page->getAttribute('exclude_search_index')) {
                continue;
            }
            $text = $this->getPageText($page);
            if ($text !== '') {
                // Nur der Pfad: Die Website kann unter mehreren Adressen erreichbar sein
                $url = (string) (parse_url((string) Url::to($page), PHP_URL_PATH) ?: '/');
                $pages[$path] = [(string) $page->getCollectionName(), $url, $text];
            }
        }
        ksort($pages, SORT_STRING);

        return array_values($pages);
    }

    private function getPageText(Page $page): string
    {
        $parts = [(string) $page->getCollectionDescription()];
        // Jeder Block einzeln: Ein Block mit fehlerhaften Daten oder ein alter Blocktyp, der außerhalb
        // eines Seitenaufrufs nicht geladen werden kann, darf die übrigen Inhalte nicht verhindern.
        $rows = $this->app->make('database')->connection()->fetchAllAssociative(
            'SELECT bID, arHandle FROM CollectionVersionBlocks WHERE cID = ? AND cvID = ? ORDER BY arHandle = \'Main\' DESC, arHandle, cbDisplayOrder',
            [(int) $page->getCollectionID(), (int) $page->getVersionID()]
        );
        foreach ($rows as $row) {
            try {
                $block = Block::getByID((int) $row['bID'], $page, (string) $row['arHandle']);
                if (!is_object($block) || in_array($block->getBlockTypeHandle(), self::SKIPPED_BLOCKS, true)) {
                    continue;
                }
                $controller = $block->getController();
                $content = is_object($controller) && method_exists($controller, 'getSearchableContent') ? $controller->getSearchableContent() : '';
            } catch (Throwable) {
                continue;
            }
            $parts[] = TextTools::plain((string) $content);
        }

        return trim(implode("\n", array_filter($parts, static fn (string $part) => trim($part) !== '')));
    }
}
