<?php

namespace Concrete\Package\FewoTk2;

use Concrete\Core\Application\UserInterface\Dashboard\Navigation\NavigationCache;
use Concrete\Core\Asset\Asset;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Http\Client\Client;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Routing\RouterInterface;
use Concrete\Core\View\View;
use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Assistant\Assistant;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\ChatResponder;
use FewoTk2\Assistant\KnowledgeBase;
use FewoTk2\Assistant\OllamaClient;
use FewoTk2\Assistant\SettingsRepository;
use FewoTk2\Assistant\UsageLimiter;
use FewoTk2\Feed\FeedLoader;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Fewo Toolkit – Werkzeuge für Ferienwohnungs-Websites.
 *
 * Belegung, Preise und Neuigkeiten werden nicht auf der Website gepflegt, sondern aus
 * Fewo-Office abgerufen (siehe FewoTk2\Feed). Der KI-Assistent (FewoTk2\Assistant) beantwortet
 * Fragen der Besucher mit einem Sprachmodell, das über Ollama auf dem eigenen Server läuft.
 */
class Controller extends Package implements ProviderAggregateInterface
{
    public const BLOCK_TYPE_SET = 'fewo_tk2';

    /**
     * Name in der Verwaltung von Concrete (Paket, Dashboard-Menü, Block-Set). Mit der "2", damit das Paket
     * neben dem alten "Fewo Toolkit" (fewo_toolkit) zu unterscheiden ist.
     */
    public const DISPLAY_NAME = 'Fewo Toolkit 2';

    protected $pkgHandle = 'fewo_tk2';

    protected $pkgVersion = '2.7.0';

    protected $appVersionRequired = '9.5.0';

    protected $phpVersionRequired = '8.1.0';

    protected $pkgAutoloaderRegistries = [
        'src' => 'FewoTk2',
    ];

    /**
     * Block-Handles in der Reihenfolge, in der sie im Block-Set erscheinen.
     */
    private const BLOCK_TYPES = [
        'fewo_belegungsplan',
        'fewo_preisuebersicht',
        'fewo_preisrechner',
        'fewo_neuigkeiten',
        'fewo_kontaktformular',
        'fewo_ausstattungsliste',
        'fewo_bildergalerie',
        'fewo_karte',
        'fewo_rundgang',
        'fewo_chat',
    ];

    /**
     * Dashboard-Seiten: Pfad => [Name, Beschreibung] (englisch, werden über t() übersetzt).
     */
    private const SINGLE_PAGES = [
        '/dashboard/fewo_tk2' => [self::DISPLAY_NAME, 'Apartments and the AI assistant.'],
        '/dashboard/fewo_tk2/apartments' => ['Apartments', 'Fewo-Office URLs for occupancy, prices and news of each apartment.'],
        '/dashboard/fewo_tk2/prices' => ['Prices', 'Prices, minimum occupancy, minimum stay and final cleaning of each apartment, as delivered by Fewo-Office.'],
        '/dashboard/fewo_tk2/assistant' => ['AI Assistant', 'Chat that answers visitors\' questions with a local language model (Ollama).'],
    ];

    /**
     * Nur für die Extraktion der Übersetzungen (c5:package:translate).
     */
    private static function translatableStrings(): void
    {
        t('Apartments and the AI assistant.');
        t('Apartments');
        t('Fewo-Office URLs for occupancy, prices and news of each apartment.');
        t('Prices');
        t('Prices, minimum occupancy, minimum stay and final cleaning of each apartment, as delivered by Fewo-Office.');
        t('AI Assistant');
        t('Chat that answers visitors\' questions with a local language model (Ollama).');
    }

    public function getPackageName()
    {
        return t('Fewo Toolkit 2');
    }

    public function getPackageDescription()
    {
        return t('Occupancy calendar, prices, price calculator and news with data from Fewo-Office, an inquiry form, 360° tours, an AI assistant and more for holiday apartment websites.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'FewoTk2\Entity',
        ]);
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->removeStoredInquiries();
        $this->installOrUpgrade($this->getPackageEntity());
    }

    public function on_start()
    {
        $this->app->bind(FeedLoader::class, static fn ($app) => new FeedLoader(
            $app->make(EntityManagerInterface::class),
            $app->make(Client::class),
            $app->make(LoggerFactory::class)->createLogger(Channels::CHANNEL_APPLICATION)
        ));

        // KI-Assistent
        $this->app->singleton(SettingsRepository::class);
        // Eigener HTTP-Client statt des Clients von Concrete: Ollama läuft im eigenen Netz (kein Proxy der Website),
        // und ein für Concrete eingeschaltetes Protokoll der HTTP-Aufrufe würde die Antworten mitschreiben und
        // erst vollständig statt Wort für Wort durchreichen.
        $this->app->bind(OllamaClient::class, static fn () => new OllamaClient(new GuzzleClient()));
        $this->app->bind(KnowledgeBase::class, static fn ($app) => new KnowledgeBase($app, $app->make(SettingsRepository::class)));
        $this->app->bind(Assistant::class, static fn ($app) => new Assistant(
            $app,
            $app->make(SettingsRepository::class),
            $app->make(KnowledgeBase::class),
            $app->make(OllamaClient::class),
            $app->make(UsageLimiter::class),
            $app->make(LoggerFactory::class)->createLogger(Channels::CHANNEL_APPLICATION)
        ));
        // Der Assistent soll den neuesten Stand kennen: Bei Änderungen an Seiten wird sein Wissen neu eingelesen
        $director = $this->app->make('director');
        foreach (['on_page_version_approve', 'on_page_update', 'on_page_move', 'on_page_delete', 'on_page_move_to_trash'] as $event) {
            $director->addListener($event, fn () => $this->app->make(KnowledgeBase::class)->clear());
        }

        // Chat unten rechts auf der Website (alle oder bestimmte Seiten), wenn er im Dashboard eingeschaltet ist
        $this->app->bind(ChatResponder::class, static fn ($app) => new ChatResponder($app));
        $router = $this->app->make(RouterInterface::class);
        $router->get('/fewo_tk2/chat/token', 'Concrete\Package\FewoTk2\Controller\ChatWidget::token');
        $router->post('/fewo_tk2/chat/ask', 'Concrete\Package\FewoTk2\Controller\ChatWidget::ask');
        $router->post('/fewo_tk2/chat/warm', 'Concrete\Package\FewoTk2\Controller\ChatWidget::warm');
        $director->addListener('on_page_view', function ($event): void {
            $this->addChatWidget($event->getPageObject());
        });

        $assets = AssetList::getInstance();
        // dieselben Dateien wie der Block "Chat"
        $assets->register('javascript', 'fewo_tk2/chat', 'blocks/fewo_chat/view.js', ['position' => Asset::ASSET_POSITION_FOOTER, 'minify' => false, 'combine' => false], $this);
        $assets->register('css', 'fewo_tk2/chat', 'blocks/fewo_chat/view.css', ['minify' => false, 'combine' => false], $this);
        $assets->registerGroup('fewo_tk2/chat', [
            ['javascript', 'fewo_tk2/chat'],
            ['css', 'fewo_tk2/chat'],
        ]);
        $assets->register('javascript', 'fewo_tk2/leaflet', 'assets/leaflet/leaflet.js', ['position' => Asset::ASSET_POSITION_FOOTER, 'minify' => false, 'combine' => false], $this);
        $assets->register('css', 'fewo_tk2/leaflet', 'assets/leaflet/leaflet.css', ['minify' => false, 'combine' => false], $this);
        $assets->registerGroup('fewo_tk2/leaflet', [
            ['javascript', 'fewo_tk2/leaflet'],
            ['css', 'fewo_tk2/leaflet'],
        ]);
        $assets->register('javascript', 'fewo_tk2/pannellum', 'assets/pannellum/pannellum.js', ['position' => Asset::ASSET_POSITION_FOOTER, 'minify' => false, 'combine' => false], $this);
        $assets->register('css', 'fewo_tk2/pannellum', 'assets/pannellum/pannellum.css', ['minify' => false, 'combine' => false], $this);
        $assets->registerGroup('fewo_tk2/pannellum', [
            ['javascript', 'fewo_tk2/pannellum'],
            ['css', 'fewo_tk2/pannellum'],
        ]);
    }

    /**
     * Hängt den Chat an das Ende der Seite – auf allen Seiten oder nur auf den im Dashboard genannten. Nicht im Dashboard, auf Systemseiten (Anmeldung …) und im
     * Bearbeitungsmodus – dort würde er die Werkzeuge von Concrete verdecken.
     */
    private function addChatWidget($page): void
    {
        if (!$page instanceof Page || $page->isError() || $page->isAdminArea() || $page->isSystemPage() || $page->isEditMode() || $page->isMasterCollection()) {
            return;
        }
        $settings = $this->app->make(SettingsRepository::class)->get();
        if (!$settings->isWidgetActive() || !$settings->isWidgetPage((string) $page->getCollectionPath())) {
            return;
        }
        ob_start();
        View::element('chat_widget', ['settings' => $settings, 'maxLength' => AssistantSettings::MAX_QUESTION_CHARS], 'fewo_tk2');
        $view = View::getInstance();
        $view->requireAsset('fewo_tk2/chat');
        $view->addFooterItem((string) ob_get_clean());
    }

    /**
     * Bis Version 2.2 speicherte das Anfrageformular jede Anfrage, und das Dashboard zeigte sie unter
     * "Buchungsanfragen". Seit 2.3 geht eine Anfrage nur noch per E-Mail an den Vermieter – die Seite und die
     * Tabelle mit den gespeicherten Anfragen entfallen.
     */
    private function removeStoredInquiries(): void
    {
        $page = Page::getByPath('/dashboard/fewo_tk2/inquiries');
        if (is_object($page) && !$page->isError()) {
            $page->delete();
        }
        $this->app->make(Connection::class)->executeStatement('DROP TABLE IF EXISTS FewoInquiries');
    }

    private function installOrUpgrade($pkg): void
    {
        $set = BlockTypeSet::getByHandle(self::BLOCK_TYPE_SET);
        if ($set === null) {
            $set = BlockTypeSet::add(self::BLOCK_TYPE_SET, self::DISPLAY_NAME, $pkg);
        } elseif ($set->getBlockTypeSetName() !== self::DISPLAY_NAME) {
            // bis Version 2.2.0 hieß das Set wie das alte Paket
            $set->updateBlockTypeSetName(self::DISPLAY_NAME);
        }

        foreach (self::BLOCK_TYPES as $handle) {
            $blockType = BlockType::getByHandle($handle);
            if (!is_object($blockType)) {
                $blockType = BlockType::installBlockType($handle, $pkg);
            }
            if (is_object($blockType) && !$set->contains($blockType)) {
                $set->addBlockType($blockType);
            }
        }

        $order = 0;
        foreach (self::SINGLE_PAGES as $path => [$name, $description]) {
            $page = Page::getByPath($path);
            if (!is_object($page) || $page->isError()) {
                $page = SinglePage::add($path, $pkg);
            }
            if (is_object($page) && !$page->isError()) {
                $page->update(['cName' => $name, 'cDescription' => $description]);
                if ($path !== '/dashboard/fewo_tk2') {
                    // Reihenfolge im Menü wie in SINGLE_PAGES – später hinzugekommene Seiten stünden sonst am Ende
                    $page->updateDisplayOrder($order++);
                }
            }
        }

        // Das Dashboard-Menü liegt in der Sitzung: Ohne diesen Schritt erscheinen neue Seiten nach einem
        // Update erst nach erneuter Anmeldung.
        if (!$this->app->isRunThroughCommandLineInterface()) {
            $this->app->make(NavigationCache::class)->clear();
        }
    }
}
