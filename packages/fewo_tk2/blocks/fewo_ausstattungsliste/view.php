<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $title
 * @var int $columnCount
 * @var bool $collapsible
 * @var string $listStyle
 * @var array $itemList
 */

if ($itemList === []) {
    $page = Concrete\Core\Page\Page::getCurrentPage();
    if ($page && $page->isEditMode()) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('Amenities list: no entries yet.') ?></div>
    <?php }

    return;
}

$notes = [];
$id = 'fewo-amenities-' . (int) $bID;
ob_start();
?>
<ul class="fewo-amenities__list" style="--fewo-columns: <?= (int) $columnCount ?>">
    <?php foreach ($itemList as $item) { ?>
        <li>
            <?= h($item['name']) ?>
            <?php if (($item['note'] ?? '') !== '') {
                $notes[] = $item['note'];
                $number = count($notes); ?>
                <sup><a href="#<?= $id ?>-note-<?= $number ?>" id="<?= $id ?>-ref-<?= $number ?>" aria-describedby="<?= $id ?>-notes-title"><?= $number ?></a></sup>
            <?php } ?>
        </li>
    <?php } ?>
</ul>
<?php if ($notes !== []) { ?>
    <h4 class="fewo-visually-hidden" id="<?= $id ?>-notes-title"><?= t('Notes') ?></h4>
    <ol class="fewo-amenities__notes">
        <?php foreach ($notes as $index => $note) { ?>
            <li id="<?= $id ?>-note-<?= $index + 1 ?>"><?= h($note) ?> <a href="#<?= $id ?>-ref-<?= $index + 1 ?>" aria-label="<?= h(t('Back to list')) ?>">↩</a></li>
        <?php } ?>
    </ol>
<?php }
$content = ob_get_clean();
?>
<div class="fewo-amenities fewo-amenities--<?= h($listStyle) ?>" id="<?= $id ?>">
    <?php if ($collapsible) { ?>
        <details class="fewo-amenities__details">
            <summary class="fewo-amenities__title"><?= h($title !== '' ? $title : t('Amenities')) ?></summary>
            <?= $content ?>
        </details>
    <?php } else { ?>
        <?php if ($title !== '') { ?>
            <h3 class="fewo-amenities__title"><?= h($title) ?></h3>
        <?php } ?>
        <?= $content ?>
    <?php } ?>
</div>
