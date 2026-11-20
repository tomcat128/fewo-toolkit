<?php

/**
 * Legt Demo-Inhalte für die lokale Testumgebung an:
 * eine Preisliste, Beispielbilder und je eine Testseite pro Block.
 *
 *   docker compose exec -u www-data web ./vendor/bin/concrete c5:exec /seed/seed.php
 */

use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\File\Filesystem;
use Concrete\Core\File\Import\FileImporter;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Template;
use Concrete\Core\Page\Type\Type as PageType;
use Concrete\Core\Support\Facade\Application;
use Doctrine\ORM\EntityManagerInterface;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Entity\Season;

$app = Application::getFacadeApplication();
$em = $app->make(EntityManagerInterface::class);

// 1. Preisliste
$pricelist = $em->getRepository(Pricelist::class)->findOneBy(['name' => 'Seeherz (Demo)']) ?? new Pricelist();
$pricelist->setName('Seeherz (Demo)')
    ->setMaxGuests(4)
    ->setMinNights(3)
    ->setCleaningFee(6500)
    ->setSecurityDeposit(20000)
    ->setDownPaymentPercent(30)
    ->setIncludedServices('inkl. Bettwäsche, Handtücher, WLAN und Nebenkosten')
    ->setNote('Preise in anderen als den genannten Zeiträumen auf Anfrage.');
$pricelist->clearSeasons();
$year = (int) date('Y');
$seasons = [
    ['Nebensaison', "{$year}-01-01", "{$year}-05-31", [2 => 7500, 4 => 9000], null],
    ['Ostern', ($year + 1) . '-03-28', ($year + 1) . '-04-06', [2 => 9000, 4 => 10500], 5],
    ['Hauptsaison', "{$year}-06-01", "{$year}-09-15", [1 => 8500, 2 => 9500, 4 => 11500], 7],
    ['Nebensaison', "{$year}-09-16", "{$year}-12-31", [2 => 7500, 3 => 8500, 4 => 9000], null],
    ['Nebensaison', ($year + 1) . '-01-01', ($year + 1) . '-05-31', [2 => 7800, 4 => 9300], null],
];
foreach ($seasons as [$name, $from, $to, $prices, $minNights]) {
    $pricelist->addSeason((new Season($name, new DateTimeImmutable($from), new DateTimeImmutable($to)))
        ->setPrices($prices)
        ->setMinNights($minNights));
}
$em->persist($pricelist);
$em->flush();
echo 'Preisliste #' . $pricelist->getId() . "\n";

// 2. Beispielbilder in einem Ordner "Fewo Demo"
$filesystem = new Filesystem();
$root = $filesystem->getRootFolder();
$folder = null;
$root->populateDirectChildrenOnly();
foreach ($root->getChildNodes() as $child) {
    if ($child instanceof Concrete\Core\Tree\Node\Type\FileFolder && $child->getTreeNodeName() === 'Fewo Demo') {
        $folder = $child;
    }
}
if ($folder === null) {
    $folder = $filesystem->addFolder($root, 'Fewo Demo');
}
$folder->populateDirectChildrenOnly();
if ($folder->getChildNodes() === []) {
    $importer = $app->make(FileImporter::class);
    $options = $app->make(Concrete\Core\File\Import\ImportOptions::class)->setImportToFolder($folder);
    $colors = [[47, 111, 160], [222, 140, 60], [80, 150, 90], [170, 70, 90], [110, 90, 160], [60, 140, 140]];
    foreach ($colors as $i => [$r, $g, $b]) {
        $image = imagecreatetruecolor(1600, 1067);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        imagefilledellipse($image, 1200, 300, 300, 300, imagecolorallocate($image, 255, 230, 120));
        imagestring($image, 5, 60, 60, 'Fewo Demo ' . ($i + 1), imagecolorallocate($image, 255, 255, 255));
        $tmp = sys_get_temp_dir() . '/fewo-demo-' . ($i + 1) . '.jpg';
        imagejpeg($image, $tmp, 85);
        $version = $importer->importLocalFile($tmp, 'fewo-demo-' . ($i + 1) . '.jpg', $options);
        $version->updateTitle('Ansicht ' . ($i + 1));
        $version->updateDescription('Blick in die Ferienwohnung – Bild ' . ($i + 1));
    }
}
echo 'Bilderordner #' . $folder->getTreeNodeID() . "\n";

// 3. Testkalender (wird vom Webserver selbst ausgeliefert)
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Fewo Demo//EN\r\n";
foreach ([[3, 8, 'Reserved'], [8, 12, 'Reserved'], [20, 40, 'Auf Anfrage'], [40, 47, 'Booked'], [70, 77, 'Reserved']] as $i => [$s, $e, $summary]) {
    $ics .= "BEGIN:VEVENT\r\nUID:demo-{$i}@fewo\r\nDTSTART;VALUE=DATE:" . date('Ymd', strtotime("+{$s} days"))
        . "\r\nDTEND;VALUE=DATE:" . date('Ymd', strtotime("+{$e} days")) . "\r\nSUMMARY:{$summary}\r\nEND:VEVENT\r\n";
}
file_put_contents(DIR_BASE . '/fewo-demo-calendar.ics', $ics . "END:VCALENDAR\r\n");

// 4. Testseiten
$home = Page::getByID(Page::getHomePageID());
$pageType = PageType::getByHandle('page');
$template = Template::getByHandle('full');
$makePage = static function (string $handle, string $name, array $blocks) use ($home, $pageType, $template): Page {
    $page = Page::getByPath('/' . $handle);
    if (!$page || $page->isError()) {
        $page = $home->add($pageType, ['cName' => $name, 'cHandle' => $handle], $template);
    }
    foreach ($page->getBlocks('Main') as $block) {
        $block->deleteBlock();
    }
    foreach ($blocks as [$blockHandle, $data]) {
        $page->addBlock(BlockType::getByHandle($blockHandle), 'Main', $data);
    }
    echo "Seite {$page->getCollectionPath()}\n";

    return $page;
};

$inquiryPage = $makePage('fewo-anfrage', 'Anfrage', [
    ['kontaktformular', [
        'formName' => 'Seeherz',
        'recipientEmail' => 'vermieter@fewo-dev.test',
        'pricelistID' => $pricelist->getId(),
        'accommodations' => "Seeherz, 42 m², max. 4 Personen\nSeeherzchen, 24 m², max. 2 Personen",
        'referralOptions' => "Suchmaschine\nUrlaubsportal\nEmpfehlung",
        'askForAddress' => 1,
        'storeInquiries' => 1,
        'sendConfirmation' => 1,
        'successMessage' => 'Vielen Dank für Ihre Anfrage! Wir melden uns schnellstmöglich.',
    ]],
]);

$makePage('fewo-demo', 'Fewo Demo', [
    ['belegungsplan', [
        'title' => 'Belegungsplan',
        'icsUrl' => 'http://localhost/fewo-demo-calendar.ics',
        'refreshMinutes' => 60,
        'monthsVisible' => 3,
        'monthsTotal' => 12,
        'onRequestKeywords' => 'Auf Anfrage',
        'showLegend' => 1,
    ]],
    ['preisuebersicht', ['pricelistID' => $pricelist->getId(), 'title' => 'Preise', 'showConditions' => 1]],
    ['preisrechner', ['pricelistID' => $pricelist->getId(), 'title' => 'Preisrechner', 'calculationMode' => 'arrival', 'inquiryPageID' => $inquiryPage->getCollectionID()]],
    ['ausstattungsliste', [
        'title' => 'Küche',
        'itemName' => ['Geschirrspüler', 'Ceranfeld', 'Kaffeemaschine', 'Kühlschrank mit Gefrierfach', 'Mikrowelle', 'Wasserkocher'],
        'itemNote' => ['', '', 'Filterkaffee und Nespresso', '', '', ''],
        'columnCount' => 2,
        'collapsible' => 0,
    ]],
    ['bildergalerie', ['source' => 'folder', 'folderID' => $folder->getTreeNodeID(), 'sortOrder' => 'title', 'columnCount' => 3, 'aspectRatio' => '3:2', 'showCaptions' => 1]],
    ['karte', [
        'markerLat' => ['47.7102', '47.6995'],
        'markerLng' => ['9.1024', '9.0631'],
        'markerTitle' => ['Ferienwohnung Seeherz', 'Insel Reichenau'],
        'markerText' => ["Seestraße 1\n78479 Reichenau", ''],
        'zoom' => 0,
        'height' => 400,
        'requireConsent' => 1,
    ]],
]);
