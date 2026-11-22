<?php

/**
 * Legt Demo-Inhalte für die lokale Testumgebung an: Belegung und Preislisten im Format der
 * Fewo-Office-Schnittstelle (als Dateien, die der Webserver selbst ausliefert), die Wohnungen
 * dazu im Dashboard, Beispielbilder und Testseiten mit allen Blöcken.
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
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\SettingsRepository;
use FewoTk2\Entity\Apartment;

$app = Application::getFacadeApplication();
$em = $app->make(EntityManagerInterface::class);

// 1. Belegung und Preislisten wie aus Fewo-Office (/api/wohnungen/{kürzel}/belegung.json bzw. preise.json).
// Alle Daten sind relativ zum heutigen Tag, damit die Demo zu jeder Jahreszeit gleich aussieht.
$day = static fn (int $offset): string => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));
$status = ['type' => 'OK', 'message' => 'Okay!'];
$feedUrl = static fn (string $file): string => 'http://localhost/application/files/' . $file;
$writeFeed = static function (string $file, string $content): void {
    file_put_contents(DIR_FILES_UPLOADED_STANDARD . '/' . $file, $content);
    echo "Datei {$file}\n";
};
$json = static fn (array $data): string => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$prices = static fn (array $table): array => array_map(
    static fn (int $persons, int $euros): array => ['persons' => $persons, 'value' => number_format($euros, 2, '.', '')],
    array_keys($table),
    $table
);

// Seeherz: Belegung als JSON und – mit denselben Terminen – als ICS
$bookings = [];
foreach ([[3, 8, 'direkt', null], [8, 12, 'airbnb', null], [20, 40, 'direkt', 'Betriebsferien'], [40, 47, 'homeaway', null], [70, 77, 'direkt', null]] as [$from, $to, $vendor, $info]) {
    $bookings[] = ['date_start' => $day($from), 'date_end' => $day($to), 'vendor' => $vendor, 'info' => $info];
}
$writeFeed('fewo-demo-belegung.json', $json(['status' => $status, 'occupancy' => ['name' => 'seeherz', 'title' => 'Ferienwohnung Seeherz'], 'bookings' => $bookings]));
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Fewo-Office//Belegung//DE\r\n";
foreach ($bookings as $i => $booking) {
    $ics .= "BEGIN:VEVENT\r\nUID:demo-{$i}@fewo-office\r\nDTSTAMP:" . gmdate('Ymd\\THis\\Z')
        . "\r\nDTSTART:" . str_replace('-', '', $booking['date_start']) . "T150000\r\nDTEND:" . str_replace('-', '', $booking['date_end']) . "T100000"
        . "\r\nSUMMARY:" . ($booking['info'] ?? 'Belegt') . "\r\nLOCATION:SEEHERZ\r\nEND:VEVENT\r\n";
}
$writeFeed('fewo-demo-belegung.ics', $ics . "END:VCALENDAR\r\n");

// Seeherzchen: die nächsten 14 Tage belegt, als XML
$writeFeed('fewo-demo-belegung-2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
    . '<fewobacher data="occupancy"><status><type>OK</type><message>Okay!</message></status>'
    . '<occupancy><name>seeherzchen</name><title>Ferienwohnung Seeherzchen</title></occupancy>'
    . '<bookings><booking><date_start>' . $day(1) . '</date_start><date_end>' . $day(15) . '</date_end><vendor>direkt</vendor><info></info></booking></bookings>'
    . "</fewobacher>\n");

// Seeherz: Preisliste als JSON. Die erste Saison ist bereits abgelaufen und darf nirgends erscheinen
// (Fewo-Office liefert solche Saisons gar nicht erst aus).
$seasons = [];
foreach ([
    ['Vergangene Saison', -60, -1, [2 => 60, 4 => 70]],
    ['Nebensaison', -10, 59, [2 => 75, 3 => 85, 4 => 90]],
    ['Hauptsaison', 60, 149, [1 => 85, 2 => 95, 4 => 115]],
    ['Zwischensaison', 150, 300, [2 => 78, 4 => 93]],
] as [$name, $from, $to, $table]) {
    $seasons[] = ['name' => $name, 'date_start' => $day($from), 'date_end' => $day($to), 'prices' => $prices($table)];
}
$writeFeed('fewo-demo-preise.json', $json([
    'status' => $status,
    'pricelist' => ['name' => 'seeherz', 'title' => 'Ferienwohnung Seeherz', 'cleaning' => '65.00', 'min_stay' => 3, 'max_guests' => 4, 'commission' => '30.0'],
    'seasons' => $seasons,
    // gilt für alle Tage, die keine Saison abdeckt
    'default_prices' => $prices([2 => 70, 4 => 88]),
]));

// Seeherzchen: kleine Preisliste als XML
$year = (int) date('Y');
$writeFeed('fewo-demo-preise-2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
    . '<fewobacher data="pricelist"><status><type>OK</type><message>Okay!</message></status>'
    . '<pricelist><name>seeherzchen</name><title>Ferienwohnung Seeherzchen</title><cleaning>40.00</cleaning><min_stay>2</min_stay><max_guests>2</max_guests><commission></commission></pricelist>'
    . '<seasons><season><name>Ganzjährig</name><date_start>' . $year . '-01-01</date_start><date_end>' . ($year + 2) . '-12-31</date_end>'
    . '<prices><persons>1</persons><value>55.00</value></prices><prices><persons>2</persons><value>55.00</value></prices></season></seasons>'
    . "<default_prices/></fewobacher>\n");

// Neuigkeiten wie aus Fewo-Office (/api/neuigkeiten/{kürzel}.json bzw. .xml). Fewo-Office liefert nur, was am
// Tag des Abrufs gilt; die künftige und die abgelaufene Neuigkeit prüfen, dass der Block trotzdem selbst filtert.
$writeFeed('fewo-demo-neuigkeiten.json', $json(['status' => $status, 'news' => [
    ['id' => 5, 'title' => 'Weihnachtsmarkt', 'text' => 'Der Weihnachtsmarkt in Konstanz hat geöffnet.', 'importance' => 'info', 'importance_label' => 'Info', 'date_start' => $day(-10), 'date_end' => $day(30), 'apartments' => ['seeherz']],
    ['id' => 3, 'title' => 'Neue Sauna im Haus', 'text' => 'Ab nächstem Monat steht unseren Gästen eine Sauna zur Verfügung.', 'importance' => 'info', 'importance_label' => 'Info', 'date_start' => $day(10), 'date_end' => $day(40), 'apartments' => ['seeherz']],
    ['id' => 2, 'title' => 'Schwimmbadpause', 'text' => "Das Schwimmbad macht Betriebspause! Wir bitten um Verständnis.\n\nDie Therme in Konstanz hat geöffnet.", 'importance' => 'important', 'importance_label' => 'Wichtige Mitteilung', 'date_start' => $day(-5), 'date_end' => $day(20), 'apartments' => ['seeherz', 'seeherzchen']],
    ['id' => 1, 'title' => 'Baustelle in der Seestraße', 'text' => 'Die Zufahrt ist nur von Norden möglich.', 'importance' => 'notice', 'importance_label' => 'Hinweis', 'date_start' => $day(-30), 'date_end' => $day(-1), 'apartments' => ['seeherz']],
]]));
$writeFeed('fewo-demo-neuigkeiten-2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
    . '<fewobacher data="news"><status><type>OK</type><message>Okay!</message></status><news>'
    . '<item><id>4</id><title>Fahrräder kostenlos</title><text>Zwei Fahrräder stehen im Keller bereit.' . "\n" . 'Den Schlüssel finden Sie in der Wohnung.</text>'
    . '<importance>notice</importance><importance_label>Hinweis</importance_label><date_start>' . $day(0) . '</date_start><date_end>' . $day(0) . '</date_end><apartments><apartment>seeherzchen</apartment></apartments></item>'
    . "</news></fewobacher>\n");

// Wohnungen im Dashboard (Fewo Toolkit › Wohnungen): Die Blöcke wählen nur noch die Wohnung aus.
$apartment = static function (string $name, string $occupancyFile, string $pricelistFile, string $newsFile = '') use ($em, $feedUrl): int {
    $apartment = $em->getRepository(Apartment::class)->findOneBy(['name' => $name]) ?? new Apartment();
    $apartment->setName($name)
        ->setOccupancyUrl($occupancyFile !== '' ? $feedUrl($occupancyFile) : '')
        ->setPricelistUrl($pricelistFile !== '' ? $feedUrl($pricelistFile) : '')
        ->setNewsUrl($newsFile !== '' ? $feedUrl($newsFile) : '')
        ->setRefreshMinutes(60);
    $em->persist($apartment);
    $em->flush();
    echo "Wohnung #{$apartment->getId()} {$name}\n";

    return (int) $apartment->getId();
};
$seeherz = $apartment('Seeherz (Demo)', 'fewo-demo-belegung.json', 'fewo-demo-preise.json', 'fewo-demo-neuigkeiten.json');
$seeherzchen = $apartment('Seeherzchen (Demo)', 'fewo-demo-belegung-2.xml', 'fewo-demo-preise-2.xml', 'fewo-demo-neuigkeiten-2.xml');
// Dieselbe Belegung im ICS-Format, ohne Preisliste
$seeherzIcs = $apartment('Seeherz – Belegung als ICS (Demo)', 'fewo-demo-belegung.ics', '');
// Die Demo-Daten sind eben neu geschrieben worden (Termine relativ zu heute): den Zwischenspeicher der Website
// verwerfen, sonst zeigt sie bis zum Ablauf des Intervalls noch den Stand des letzten Laufs – am nächsten Tag
// mit Terminen, die um einen Tag verschoben sind.
$em->getConnection()->executeStatement('DELETE FROM FewoFeeds');

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

// 3. Panoramen für den 360°-Rundgang (2:1, wie von einer 360°-Kamera) in der Dateiverwaltung, Ordner "Fewo Demo 360"
$tourFolder = null;
foreach ($root->getChildNodes() as $child) {
    if ($child instanceof Concrete\Core\Tree\Node\Type\FileFolder && $child->getTreeNodeName() === 'Fewo Demo 360') {
        $tourFolder = $child;
    }
}
$tourFolder ??= $filesystem->addFolder($root, 'Fewo Demo 360');
$tourFolder->populateDirectChildrenOnly();
$panoramaIDs = [];
foreach ($tourFolder->getChildNodes() as $node) {
    if ($node instanceof Concrete\Core\Tree\Node\Type\File && $node->getTreeNodeFileObject()) {
        $panoramaIDs[$node->getTreeNodeFileObject()->getFileName()] = (int) $node->getTreeNodeFileID();
    }
}
$panorama = static function (string $fileName, string $label, array $color, int $width) use ($app, $tourFolder, &$panoramaIDs): int {
    if (isset($panoramaIDs[$fileName])) {
        return $panoramaIDs[$fileName];
    }
    $height = intdiv($width, 2);
    $image = imagecreatetruecolor($width, $height);
    [$r, $g, $b] = $color;
    for ($y = 0; $y < $height; $y++) {
        // oben hell (Decke), unten dunkel (Boden)
        $f = 1.25 - 0.7 * $y / $height;
        imageline($image, 0, $y, $width, $y, imagecolorallocate($image, (int) min(255, $r * $f), (int) min(255, $g * $f), (int) min(255, $b * $f)));
    }
    $white = imagecolorallocate($image, 255, 255, 255);
    // Senkrechte Linien alle 30° und die Raumbezeichnung rundum
    for ($i = 0; $i < 12; $i++) {
        $x = intdiv($width * $i, 12);
        imageline($image, $x, intdiv($height, 3), $x, intdiv($height * 2, 3), $white);
        imagestring($image, 5, $x + 12, intdiv($height, 2) - 8, $label . ' ' . ($i * 30) . ' Grad', $white);
    }
    $tmp = sys_get_temp_dir() . '/' . $fileName;
    imagejpeg($image, $tmp, 85);
    imagedestroy($image);
    $options = $app->make(Concrete\Core\File\Import\ImportOptions::class)->setImportToFolder($tourFolder);
    $version = $app->make(FileImporter::class)->importLocalFile($tmp, $fileName, $options);
    $version->updateTitle($label);
    echo "Panorama {$fileName} #{$version->getFileID()}\n";

    return $panoramaIDs[$fileName] = (int) $version->getFileID();
};
// Das Wohnzimmer ist breiter als 4096 Pixel: Telefone und Tablets bekommen eine verkleinerte Fassung
$livingRoom = $panorama('Seeherz_01_Wohnzimmer.jpg', 'Wohnzimmer', [60, 120, 170], 5000);
$bedroom = $panorama('Seeherz_02_Schlafzimmer.jpg', 'Schlafzimmer', [170, 110, 70], 4096);
$panorama('Seeherz_03_Kueche.jpg', 'Kueche', [90, 150, 100], 4096);

// Rundgang durch zwei Räume, wie ihn der Szenen-Editor speichert: Startposition je Raum, Wegweiser
// (einer mit eigener Blickrichtung bei der Ankunft) und ein Hinweis
$tour = ['fewo_rundgang', [
    'title' => 'Rundgang',
    'tour' => json_encode([
        'first' => 'wohnzimmer',
        'scenes' => [
            ['name' => 'wohnzimmer', 'fID' => $livingRoom, 'title' => 'Wohnzimmer', 'yaw' => 20, 'pitch' => -2, 'hfov' => 100, 'hotSpots' => [
                ['type' => 'scene', 'pitch' => -5, 'yaw' => 45, 'text' => 'Zum Schlafzimmer', 'target' => 'schlafzimmer', 'targetYaw' => 90, 'targetPitch' => 0],
                ['type' => 'info', 'pitch' => 5, 'yaw' => 0, 'text' => 'Smart-TV mit Soundbar'],
            ]],
            ['name' => 'schlafzimmer', 'fID' => $bedroom, 'title' => 'Schlafzimmer', 'yaw' => -30, 'pitch' => 0, 'hfov' => 100, 'hotSpots' => [
                ['type' => 'scene', 'pitch' => -5, 'yaw' => 60, 'text' => '', 'target' => 'wohnzimmer'],
            ]],
        ],
    ], JSON_UNESCAPED_UNICODE),
    'previewFID' => 0,
    'autoLoad' => 1,
    'compass' => 0,
    'author' => 'Fewo Toolkit Demo',
    'useCustomCode' => 0,
    'viewerCode' => '',
    'aspectRatio' => '16:9',
    'cssClass' => '',
]];
// Einzelner Raum; auf Wunsch mit eigenen Pannellum-Einstellungen, in denen das Bild als {name} steht
$singlePanorama = static fn (string $title, string $name, int $fID, string $ratio, string $customCode = ''): array => ['fewo_rundgang', [
    'title' => $title,
    'tour' => json_encode(['first' => $name, 'scenes' => [['name' => $name, 'fID' => $fID, 'title' => $title, 'hotSpots' => []]]], JSON_UNESCAPED_UNICODE),
    'previewFID' => 0,
    'autoLoad' => 1,
    'compass' => 0,
    'author' => '',
    'useCustomCode' => $customCode !== '' ? 1 : 0,
    'viewerCode' => $customCode,
    'aspectRatio' => $ratio,
    'cssClass' => '',
]];

// KI-Assistent: Im Testprojekt antwortet die Ollama-Attrappe (dev/ollama-stub) – sofort und vorhersagbar
$app->make(SettingsRepository::class)->save(AssistantSettings::fromArray([
    'enabled' => 1,
    'ollamaUrl' => 'http://ollama-stub:11434',
    'model' => 'stub-model',
]));
$chat = ['fewo_chat', [
    'title' => 'Fragen? Unser Assistent hilft',
    'welcomeMessage' => 'Hallo! Ich bin der digitale Assistent dieser Website. Fragen Sie mich nach Ausstattung, Preisen oder freien Terminen.',
    'exampleQuestions' => "Was kostet eine Nacht?\nWann ist die Wohnung frei?",
    'notice' => 'Die Antworten erstellt eine KI; sie können Fehler enthalten. Verbindlich ist nur unser Angebot.',
]];

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
    ['fewo_kontaktformular', [
        'formName' => 'Seeherz',
        'recipientEmail' => 'vermieter@fewo-dev.test',
        'apartmentID' => $seeherz,
        'accommodations' => "Seeherz, 42 m², max. 4 Personen\nSeeherzchen, 24 m², max. 2 Personen",
        'referralOptions' => "Suchmaschine\nUrlaubsportal\nEmpfehlung",
        'askForAddress' => 1,
        'sendConfirmation' => 1,
        'successMessage' => 'Vielen Dank für Ihre Anfrage! Wir melden uns schnellstmöglich.',
    ]],
]);

$makePage('fewo-demo', 'Fewo Demo', [
    ['fewo_neuigkeiten', ['apartmentID' => $seeherz, 'title' => 'Neuigkeiten']],
    ['fewo_belegungsplan', [
        'title' => 'Belegungsplan',
        'apartmentID' => $seeherz,
        'monthsVisible' => 3,
        'monthsTotal' => 12,
        'onRequestKeywords' => '',
        'showLegend' => 1,
    ]],
    ['fewo_preisuebersicht', ['apartmentID' => $seeherz, 'title' => 'Preise', 'layout' => 'tiles', 'showConditions' => 1]],
    ['fewo_preisrechner', ['apartmentID' => $seeherz, 'title' => 'Preisrechner', 'inquiryPageID' => $inquiryPage->getCollectionID()]],
    ['fewo_ausstattungsliste', [
        'title' => 'Küche',
        'itemName' => ['Geschirrspüler', 'Ceranfeld', 'Kaffeemaschine', 'Kühlschrank mit Gefrierfach', 'Mikrowelle', 'Wasserkocher'],
        'itemNote' => ['', '', 'Filterkaffee und Nespresso', '', '', ''],
        'columnCount' => 2,
        'collapsible' => 0,
    ]],
    ['fewo_bildergalerie', ['source' => 'folder', 'folderID' => $folder->getTreeNodeID(), 'sortOrder' => 'title', 'columnCount' => 3, 'aspectRatio' => '3:2', 'showCaptions' => 1]],
    ['fewo_karte', [
        'markerLat' => ['47.7102', '47.6995'],
        'markerLng' => ['9.1024', '9.0631'],
        'markerTitle' => ['Ferienwohnung Seeherz', 'Insel Reichenau'],
        'markerText' => ["Seestraße 1\n78479 Reichenau", ''],
        'zoom' => 0,
        'height' => 400,
        'requireConsent' => 1,
    ]],
    $tour,
    $chat,
]);

// Ausstattungsliste in allen sechs Stilen mit denselben Einträgen
$amenityItems = ['WLAN', 'Geschirrspüler', 'Kaffeemaschine', 'Balkon mit Seeblick', 'Waschmaschine', 'Fahrradkeller', 'Bettwäsche und Handtücher', 'Kinderbett'];
$amenityNotes = ['kostenlos', '', 'Filterkaffee und Nespresso', '', 'im Keller, mit Trockner', '', '', 'auf Anfrage'];
$styleBlocks = [];
foreach (['plain' => 'Schlicht', 'checks' => 'Häkchen', 'tiles' => 'Kacheln', 'card' => 'Karte mit Kopfzeile', 'lines' => 'Trennlinien', 'chips' => 'Etiketten'] as $style => $label) {
    $styleBlocks[] = ['fewo_ausstattungsliste', [
        'title' => 'Ausstattung – ' . $label, 'itemName' => $amenityItems, 'itemNote' => $amenityNotes,
        'columnCount' => $style === 'tiles' ? 4 : 2, 'collapsible' => 0, 'listStyle' => $style,
    ]];
}
// aufklappbar im Stil "Karte"
$styleBlocks[] = ['fewo_ausstattungsliste', [
    'title' => 'Karte, aufklappbar', 'itemName' => ['Sauna', 'Fitnessraum'], 'itemNote' => ['', ''], 'columnCount' => 2, 'collapsible' => 1, 'listStyle' => 'card',
]];
// Reine Musterseite: nicht im Suchindex und damit nicht im Wissen des KI-Assistenten (dieselben Einträge sechsmal)
$makePage('fewo-ausstattung', 'Fewo Ausstattung', $styleBlocks)->setAttribute('exclude_search_index', true);

// Jeden Block zweimal auf einer Seite – die Blöcke dürfen sich nicht gegenseitig stören
$calendar = static fn (string $title, int $apartmentID, string $keywords): array => ['fewo_belegungsplan', [
    'title' => $title,
    'apartmentID' => $apartmentID,
    'monthsVisible' => 2,
    'monthsTotal' => 6,
    'onRequestKeywords' => $keywords,
    'showLegend' => 1,
]];
$form = static fn (string $name, int $apartmentID): array => ['fewo_kontaktformular', [
    'formName' => $name,
    'recipientEmail' => 'vermieter@fewo-dev.test',
    'apartmentID' => $apartmentID,
    'sendConfirmation' => 0,
    'successMessage' => 'Danke für Ihre Anfrage für ' . $name . '!',
]];
$gallery = static fn (int $columns, string $order): array => ['fewo_bildergalerie', [
    'source' => 'folder', 'folderID' => $folder->getTreeNodeID(), 'sortOrder' => $order, 'columnCount' => $columns, 'aspectRatio' => '1:1', 'showCaptions' => 1,
]];
$map = static fn (string $title, string $lat, string $lng): array => ['fewo_karte', [
    'markerLat' => [$lat], 'markerLng' => [$lng], 'markerTitle' => [$title], 'markerText' => [''], 'zoom' => 13, 'height' => 300, 'requireConsent' => 1,
]];
$makePage('fewo-doppelt', 'Fewo Doppelt', [
    // Neuigkeiten als XML, ohne Überschrift
    ['fewo_neuigkeiten', ['apartmentID' => $seeherzchen, 'title' => '']],
    ['fewo_neuigkeiten', ['apartmentID' => $seeherz, 'title' => 'Aktuelles Seeherz']],
    // Belegung im ICS-Format; "Betriebsferien" wird hier als "auf Anfrage" dargestellt
    $calendar('Belegung Seeherz', $seeherzIcs, 'Betriebsferien'),
    $calendar('Belegung Seeherzchen', $seeherzchen, ''),
    // Preisübersicht einmal als Liste untereinander, einmal als Kacheln
    ['fewo_preisuebersicht', ['apartmentID' => $seeherz, 'title' => 'Preise Seeherz', 'layout' => 'list', 'showConditions' => 1]],
    ['fewo_preisuebersicht', ['apartmentID' => $seeherzchen, 'title' => 'Preise Seeherzchen', 'layout' => 'tiles', 'showConditions' => 1]],
    ['fewo_preisrechner', ['apartmentID' => $seeherz, 'title' => 'Preisrechner Seeherz', 'inquiryPageID' => 0]],
    ['fewo_preisrechner', ['apartmentID' => $seeherzchen, 'title' => 'Preisrechner Seeherzchen', 'inquiryPageID' => 0]],
    ['fewo_ausstattungsliste', ['title' => 'Bad', 'itemName' => ['Dusche', 'Föhn'], 'itemNote' => ['bodengleich', ''], 'columnCount' => 1, 'collapsible' => 1]],
    ['fewo_ausstattungsliste', ['title' => 'Schlafzimmer', 'itemName' => ['Doppelbett', 'Kleiderschrank'], 'itemNote' => ['180 × 200 cm', ''], 'columnCount' => 1, 'collapsible' => 1]],
    $gallery(3, 'title'),
    $gallery(2, 'newest'),
    $map('Seeherz', '47.7102', '9.1024'),
    $map('Seeherzchen', '47.6995', '9.0631'),
    $singlePanorama('Wohnzimmer', 'wohnzimmer', $livingRoom, '2:1'),
    // Schreibweise des alten Pannellum-Blocks (kein JSON), Bild als Platzhalter
    $singlePanorama('Schlafzimmer', 'schlafzimmer', $bedroom, '4:3', "type: \"equirectangular\",\npanorama: \"{schlafzimmer}\",\nautoLoad: true, // Kommentar\ntitle: \"Schlafzimmer (eigene Einstellungen)\","),
    $form('Seeherz', $seeherz),
    $form('Seeherzchen', $seeherzchen),
]);

// c5:exec verwendet den Rückgabewert des Skripts als Exit-Code
return 0;
