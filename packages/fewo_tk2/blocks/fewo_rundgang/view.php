<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $title
 * @var string $viewerCode
 * @var array<string, string> $imageMap
 * @var array<string, string> $mobileImageMap
 * @var string|null $ratio Seitenverhältnis als CSS-Wert ("3 / 2"), null = Höhe aus dem Theme
 * @var string $cssClass
 * @var bool $canEdit
 */

$page = Concrete\Core\Page\Page::getCurrentPage();
$isEditMode = $page && $page->isEditMode();
$id = 'fewo-tour-' . (int) $bID;
$classes = trim('fewo-tour ' . ($ratio === null ? 'fewo-tour--manual ' : '') . $cssClass);
$label = $title !== '' ? $title : t('360° view');

if (trim((string) $viewerCode) === '') {
    if ($isEditMode) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('360° tour: no settings yet.') ?></div>
    <?php }

    return;
}
?>
<div class="<?= h($classes) ?>" id="<?= $id ?>"<?= $ratio !== null ? ' style="--fewo-tour-ratio: ' . h($ratio) . '"' : '' ?>>
    <?php if ($title !== '') { ?>
        <h2 class="fewo-tour__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($isEditMode) { ?>
        <div class="fewo-tour__viewer fewo-tour__placeholder">
            <p><?= t('360° tour – shown in view mode.') ?></p>
        </div>
    <?php } else { ?>
        <div class="fewo-tour__viewer"
             role="region"
             aria-label="<?= h($label) ?>"
             data-fewo-tour="<?= h((string) $viewerCode) ?>"
             data-images="<?= h(json_encode((object) $imageMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
             data-images-mobile="<?= h(json_encode((object) $mobileImageMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
             <?= $canEdit ? 'data-show-details' : '' ?>></div>
        <p class="fewo-tour__error" role="alert" hidden>
            <?= t('The 360° view cannot be displayed.') ?>
            <small data-fewo-tour-details></small>
        </p>
        <noscript><p class="fewo-tour__error"><?= t('JavaScript is required for the 360° view.') ?></p></noscript>
    <?php } ?>
</div>
