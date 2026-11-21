<?php

/**
 * Legt die leere Seite /fewo-e2e für die Browser-Tests der Block-Dialoge an
 * bzw. entfernt alle Blöcke von ihr und gibt sie zur Bearbeitung frei.
 *
 *   concrete c5:exec /seed/e2e/reset-page.php
 */

use Concrete\Core\Page\Page;
use Concrete\Core\Page\Template;
use Concrete\Core\Page\Type\Type as PageType;

$page = Page::getByPath('/fewo-e2e');
if (!$page || $page->isError()) {
    $home = Page::getByID(Page::getHomePageID());
    $page = $home->add(PageType::getByHandle('page'), ['cName' => 'Fewo E2E', 'cHandle' => 'fewo-e2e'], Template::getByHandle('full'));
}
if ($page->isCheckedOut()) {
    $page->forceCheckIn();
}
$page = Page::getByPath('/fewo-e2e', 'RECENT');
foreach ($page->getBlocks('Main') as $block) {
    $block->deleteBlock();
}
// Offene Entwürfe verwerfen, damit Besucher und Tests dieselbe Version sehen
$version = Page::getByPath('/fewo-e2e', 'RECENT')->getVersionObject();
if (!$version->isApproved()) {
    $version->approve(false);
}
echo 'Seite ' . $page->getCollectionPath() . ' #' . $page->getCollectionID() . "\n";

// c5:exec verwendet den Rückgabewert des Skripts als Exit-Code
return 0;
