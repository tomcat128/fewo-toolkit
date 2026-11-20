<?php

namespace Concrete\Package\FewoToolkit;

use Concrete\Core\Asset\Asset;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Block\BlockType\Set as BlockTypeSet;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;

/**
 * Fewo Toolkit – Werkzeuge für Ferienwohnungs-Websites.
 */
class Controller extends Package implements ProviderAggregateInterface
{
    public const BLOCK_TYPE_SET = 'fewo_toolkit';

    protected $pkgHandle = 'fewo_toolkit';

    protected $pkgVersion = '2.0.0';

    protected $appVersionRequired = '9.5.0';

    protected $phpVersionRequired = '8.1.0';

    protected $pkgAutoloaderRegistries = [
        'src' => 'FewoToolkit',
    ];

    /**
     * Block-Handles in der Reihenfolge, in der sie im Block-Set erscheinen.
     */
    private const BLOCK_TYPES = [
        'belegungsplan',
        'preisuebersicht',
        'preisrechner',
        'kontaktformular',
        'ausstattungsliste',
        'bildergalerie',
        'karte',
    ];

    /**
     * Dashboard-Seiten: Pfad => [Name, Beschreibung] (englisch, werden über t() übersetzt).
     */
    private const SINGLE_PAGES = [
        '/dashboard/fewo_toolkit' => ['Fewo Toolkit', 'Price lists and booking inquiries for holiday apartments.'],
        '/dashboard/fewo_toolkit/pricelists' => ['Price Lists', 'Seasons and prices per night.'],
        '/dashboard/fewo_toolkit/inquiries' => ['Booking Inquiries', 'Inquiries received through the booking inquiry form.'],
    ];

    /**
     * Nur für die Extraktion der Übersetzungen (c5:package:translate).
     */
    private static function translatableStrings(): void
    {
        t('Price Lists');
        t('Seasons and prices per night.');
        t('Booking Inquiries');
        t('Inquiries received through the booking inquiry form.');
        t('Price lists and booking inquiries for holiday apartments.');
    }

    public function getPackageName()
    {
        return t('Fewo Toolkit');
    }

    public function getPackageDescription()
    {
        return t('Occupancy calendar, price lists, price calculator, booking inquiries and more for holiday apartment websites.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'FewoToolkit\Entity',
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
        $this->installOrUpgrade($this->getPackageEntity());
    }

    public function on_start()
    {
        $assets = AssetList::getInstance();
        $assets->register('javascript', 'fewo_toolkit/leaflet', 'assets/leaflet/leaflet.js', ['position' => Asset::ASSET_POSITION_FOOTER, 'minify' => false, 'combine' => false], $this);
        $assets->register('css', 'fewo_toolkit/leaflet', 'assets/leaflet/leaflet.css', ['minify' => false, 'combine' => false], $this);
        $assets->registerGroup('fewo_toolkit/leaflet', [
            ['javascript', 'fewo_toolkit/leaflet'],
            ['css', 'fewo_toolkit/leaflet'],
        ]);
    }

    private function installOrUpgrade($pkg): void
    {
        $set = BlockTypeSet::getByHandle(self::BLOCK_TYPE_SET);
        if ($set === null) {
            $set = BlockTypeSet::add(self::BLOCK_TYPE_SET, 'Fewo Toolkit', $pkg);
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

        foreach (self::SINGLE_PAGES as $path => [$name, $description]) {
            $page = Page::getByPath($path);
            if (!is_object($page) || $page->isError()) {
                $page = SinglePage::add($path, $pkg);
            }
            if (is_object($page) && !$page->isError()) {
                $page->update(['cName' => $name, 'cDescription' => $description]);
            }
        }
    }
}
