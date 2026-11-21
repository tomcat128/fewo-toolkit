<?php

/**
 * Legt in der lokalen Seeherz-Instanz die Seite /fewo-toolkit-2 an: alle neun Blöcke des neuen Pakets
 * (fewo_tk2) mit den echten Inhalten der Website – neben den unveränderten Seiten mit den alten Blöcken.
 *
 * - Belegung und Preise: Wohnung "Seeherz" mit den Adressen in Fewo-Office
 * - Ausstattung, Bilder, Rundgang: aus den alten Blöcken der Seiten /ausstattung, /bilder, /rundgang übernommen
 * - Anfragen gehen an eine Testadresse (Mailpit)
 * - Chat: Der KI-Assistent wird eingeschaltet und mit dem Ollama der Instanz verbunden (http://ollama:11434)
 *
 *   docker compose exec -u www-data web ./concrete/bin/concrete c5:exec /seed/page.php [Modell]
 *
 * Kann beliebig oft aufgerufen werden: Die Blöcke der Seite werden jedes Mal neu angelegt.
 */

use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Template;
use Concrete\Core\Page\Type\Type as PageType;
use Concrete\Core\Support\Facade\Application;
use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Assistant\AssistantSettings;
use FewoTk2\Assistant\KnowledgeBase;
use FewoTk2\Assistant\SettingsRepository;
use FewoTk2\Entity\Apartment;

const PAGE_HANDLE = 'fewo-toolkit-2';
const FEWO_OFFICE = 'https://fewo-office.com/api/wohnungen/seeherz/';

$app = Application::getFacadeApplication();
$db = $app->make('database')->connection();
$em = $app->make(EntityManagerInterface::class);

// IDs der Blöcke eines Typs in der freigegebenen Version einer Seite, in der Reihenfolge der Seite
$legacyBlocks = static fn (string $path, string $handle): array => array_map('intval', $db->fetchFirstColumn(
    'SELECT b.bID FROM PagePaths pp
       JOIN CollectionVersions cv ON cv.cID = pp.cID AND cv.cvIsApproved = 1
       JOIN CollectionVersionBlocks cvb ON cvb.cID = cv.cID AND cvb.cvID = cv.cvID
       JOIN Blocks b ON b.bID = cvb.bID
       JOIN BlockTypes bt ON bt.btID = b.btID
      WHERE pp.ppIsCanonical = 1 AND pp.cPath = ? AND bt.btHandle = ?
      ORDER BY cvb.cbDisplayOrder',
    [$path, $handle]
));

// 1. Wohnung mit den Adressen in Fewo-Office (Dashboard › Fewo Toolkit › Wohnungen)
$apartment = $em->getRepository(Apartment::class)->findOneBy(['name' => 'Seeherz']) ?? new Apartment();
$apartment->setName('Seeherz')
    ->setOccupancyUrl(FEWO_OFFICE . 'belegung.json')
    ->setPricelistUrl(FEWO_OFFICE . 'preise.json')
    ->setRefreshMinutes(60);
$em->persist($apartment);
$em->flush();
$apartmentID = (int) $apartment->getId();
echo "Wohnung #{$apartmentID} Seeherz\n";

// 2. Ausstattung: je alter Liste ein neuer Block, Anmerkungen aus den Kommentaren
$amenities = [];
foreach ($legacyBlocks('/ausstattung', 'ausstattungsliste') as $bID) {
    $items = $db->fetchAllAssociative(
        'SELECT a.ausstattung, COALESCE(k.kommentar, \'\') AS kommentar
           FROM btFwbAusstattungsliste a
           LEFT JOIN btFwbAusstattungslisteKommentare k ON k.aID = a.aID AND k.bID = a.bID
          WHERE a.bID = ? ORDER BY a.sortierung',
        [$bID]
    );
    if ($items === []) {
        continue;
    }
    $amenities[] = ['fewo_ausstattungsliste', [
        'title' => (string) $db->fetchOne('SELECT liste FROM btFwbAusstattung WHERE bID = ?', [$bID]),
        'itemName' => array_column($items, 'ausstattung'),
        'itemNote' => array_column($items, 'kommentar'),
        'columnCount' => 2,
        'collapsible' => 0,
    ]];
}
echo count($amenities) . " Ausstattungslisten von /ausstattung\n";

// 3. Bilder: dasselbe Datei-Set wie die alte Galerie auf /bilder
$gallery = [];
foreach ($legacyBlocks('/bilder', 'bildergalerie') as $bID) {
    $fileSetID = (int) $db->fetchOne('SELECT dateialbum FROM btFwbBildergalerie WHERE bID = ?', [$bID]);
    if ($fileSetID > 0) {
        $gallery[] = ['fewo_bildergalerie', ['source' => 'set', 'fileSetID' => $fileSetID, 'sortOrder' => 'display', 'columnCount' => 3, 'aspectRatio' => '3:2', 'showCaptions' => 1]];
        echo "Bildergalerie: Datei-Set #{$fileSetID}\n";
        break;
    }
}

// 4. Rundgang: Räume, Wegweiser und Hinweise aus dem alten Pannellum-Block von /rundgang in die
//    Form des neuen Szenen-Editors übertragen. Bilder stehen im alten Block als Pfad unter
//    application/files, im neuen als Datei der Dateiverwaltung.
$fileByPath = static function (string $path) use ($db): int {
    $parts = explode('/', trim($path, '/ '));
    $name = (string) array_pop($parts);

    return (int) $db->fetchOne(
        'SELECT fID FROM FileVersions WHERE fvFilename = ? AND fvPrefix = ? AND fvIsApproved = 1 ORDER BY fvID DESC LIMIT 1',
        [$name, implode('', $parts)]
    );
};
// Winkel wie 349.38 (alter Block) als -10.62
$angle = static fn (mixed $value): float => round(fmod((float) $value + 540, 360) - 180, 2);
$tour = [];
foreach ($legacyBlocks('/rundgang', 'pannellum') as $bID) {
    $legacy = $db->fetchAssociative('SELECT images, viewer_code FROM btPannellum WHERE bID = ?', [$bID]);
    $images = [];
    foreach (preg_split('/\R/', (string) $legacy['images']) as $line) {
        if (str_contains($line, '->')) {
            [$key, $value] = array_map('trim', explode('->', $line, 2));
            $images[$key] = $value;
        }
    }
    // Die alten Einstellungen sind JSON ohne äußere Klammer, mit Kommentaren
    $code = preg_replace('~/\*.*?\*/~s', '', (string) $legacy['viewer_code']);
    $code = preg_replace('/,(\s*[}\]])/', '$1', '{' . $code . '}');
    $settings = json_decode((string) $code, true);
    if (!is_array($settings) || !is_array($settings['scenes'] ?? null)) {
        echo "Rundgang: Einstellungen des alten Blocks #{$bID} nicht lesbar – übersprungen\n";
        continue;
    }
    $scenes = [];
    foreach ($settings['scenes'] as $name => $scene) {
        $hotSpots = [];
        foreach ($scene['hotSpots'] ?? [] as $hotSpot) {
            $entry = ['type' => $hotSpot['type'] ?? 'info', 'pitch' => $angle($hotSpot['pitch'] ?? 0), 'yaw' => $angle($hotSpot['yaw'] ?? 0), 'text' => (string) ($hotSpot['text'] ?? '')];
            if ($entry['type'] === 'scene') {
                $entry += ['target' => (string) ($hotSpot['sceneId'] ?? '')];
                if (isset($hotSpot['targetYaw'])) {
                    $entry += ['targetYaw' => $angle($hotSpot['targetYaw']), 'targetPitch' => $angle($hotSpot['targetPitch'] ?? 0)];
                }
            }
            $hotSpots[] = $entry;
        }
        $scenes[] = [
            'name' => (string) $name,
            'fID' => $fileByPath($images[trim((string) ($scene['panorama'] ?? ''), '{}')] ?? ''),
            'title' => (string) ($scene['title'] ?? ''),
            'yaw' => $angle($scene['yaw'] ?? 0),
            'pitch' => $angle($scene['pitch'] ?? 0),
            'horizonPitch' => (float) ($scene['horizonPitch'] ?? 0),
            'horizonRoll' => (float) ($scene['horizonRoll'] ?? 0),
            'hotSpots' => $hotSpots,
        ];
    }
    $default = is_array($settings['default'] ?? null) ? $settings['default'] : [];
    $tour[] = ['fewo_rundgang', [
        'title' => '360°-Rundgang',
        'tour' => json_encode(['first' => (string) ($default['firstScene'] ?? ''), 'scenes' => $scenes], JSON_UNESCAPED_UNICODE),
        'previewFID' => $fileByPath($images['preview'] ?? ''),
        'autoLoad' => empty($default['autoLoad']) ? 0 : 1,
        'compass' => empty($default['compass']) ? 0 : 1,
        'author' => trim((string) ($default['author'] ?? ''), '[] '),
        'useCustomCode' => 0,
        'viewerCode' => '',
        'aspectRatio' => '3:2',
        'cssClass' => '',
    ]];
    echo 'Rundgang: ' . count($scenes) . ' Räume, ' . count(array_filter(array_column($scenes, 'fID'))) . " Bilder gefunden\n";
    break;
}

// 5. KI-Assistent: Ollama läuft als eigener Container der Instanz. Rechtliches und die technischen
//    Seiten des alten Pakets (/fewobacher/…) gehören nicht zu seinem Wissen.
$model = (string) (($args ?? [])[0] ?? 'gemma3:4b');
$app->make(SettingsRepository::class)->save(AssistantSettings::fromArray([
    'enabled' => 1,
    'ollamaUrl' => 'http://ollama:11434',
    'model' => $model,
    'excludedPaths' => "/impressum\n/datenschutz\n/fewobacher",
    'contextChars' => AssistantSettings::DEFAULT_CONTEXT,
] + $app->make(SettingsRepository::class)->get()->toArray()));
$app->make(KnowledgeBase::class)->clear();
echo "KI-Assistent: Modell {$model}\n";
$chat = ['fewo_chat', [
    'title' => 'Fragen? Unser Assistent hilft',
    'welcomeMessage' => 'Hallo! Ich bin der digitale Assistent des Ferienapartments Seeherz. Fragen Sie mich nach Ausstattung, Preisen, freien Terminen oder der Umgebung.',
    'exampleQuestions' => "Was kostet eine Nacht im Dezember?\nWann ist die Wohnung als Nächstes frei?\nGibt es WLAN und einen Parkplatz?\nWas kann man auf der Insel Reichenau unternehmen?",
    'notice' => 'Die Antworten erstellt eine KI automatisch; sie können Fehler enthalten. Verbindlich ist nur unser Angebot.',
]];

// 6. Die Seite
$page = Page::getByPath('/' . PAGE_HANDLE);
if (!$page || $page->isError()) {
    $home = Page::getByID(Page::getHomePageID());
    $page = $home->add(PageType::getByHandle('page'), ['cName' => 'Fewo Toolkit 2', 'cHandle' => PAGE_HANDLE], Template::getByHandle('full'));
}
foreach ($page->getBlocks('Main') as $block) {
    $block->deleteBlock();
}
$pageID = (int) $page->getCollectionID();

$blocks = array_merge(
    [
        ['content', ['content' => '<h1>Fewo Toolkit 2</h1><p>Testseite: alle Blöcke des neuen Pakets mit den Inhalten dieser Website. Belegung und Preise kommen aus Fewo-Office, Ausstattung, Bilder und Rundgang aus den bisherigen Blöcken.</p>']],
        $chat,
        ['fewo_belegungsplan', ['title' => 'Belegungsplan', 'apartmentID' => $apartmentID, 'monthsVisible' => 3, 'monthsTotal' => 15, 'onRequestKeywords' => '', 'showLegend' => 1]],
        ['fewo_preisuebersicht', ['apartmentID' => $apartmentID, 'title' => 'Preise', 'layout' => 'tiles', 'showConditions' => 1]],
        // "Jetzt anfragen" führt zum Anfrageformular weiter unten auf derselben Seite
        ['fewo_preisrechner', ['apartmentID' => $apartmentID, 'title' => 'Preisrechner', 'inquiryPageID' => $pageID]],
        ['fewo_kontaktformular', [
            'formName' => 'Seeherz',
            'recipientEmail' => 'vermieter@seeherz.test',
            'apartmentID' => $apartmentID,
            'accommodations' => 'Ferienapartment Seeherz, max. 3 Personen',
            'referralOptions' => "Suchmaschine\nUrlaubsportal\nEmpfehlung",
            'askForAddress' => 1,
            'sendConfirmation' => 1,
            'successMessage' => 'Vielen Dank für Ihre Anfrage! Wir melden uns schnellstmöglich.',
        ]],
    ],
    $amenities,
    $gallery,
    [
        // Markusstraße 4, wie in der Google-Karte auf /lage-anfahrt
        ['fewo_karte', [
            'markerLat' => ['47.69139'],
            'markerLng' => ['9.06042'],
            'markerTitle' => ['Ferienapartment Seeherz'],
            'markerText' => ["Markusstraße 4\n78479 Reichenau"],
            'zoom' => 14,
            'height' => 400,
            'requireConsent' => 1,
        ]],
    ],
    $tour
);
foreach ($blocks as [$handle, $data]) {
    $page->addBlock(BlockType::getByHandle($handle), 'Main', $data);
}
echo 'Seite ' . $page->getCollectionPath() . ' mit ' . count($blocks) . " Blöcken\n";

return 0;
