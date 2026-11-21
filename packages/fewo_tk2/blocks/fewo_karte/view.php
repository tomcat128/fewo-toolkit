<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var array $config
 * @var array $markerList
 * @var int $height
 */

if ($markerList === []) {
    return;
}

$page = Concrete\Core\Page\Page::getCurrentPage();
$isEditMode = $page && $page->isEditMode();
$id = 'fewo-map-' . (int) $bID;
$first = $markerList[0];
?>
<div class="fewo-map" id="<?= $id ?>" style="--fewo-map-height: <?= (int) $height ?>px">
    <?php if ($isEditMode) { ?>
        <div class="fewo-map__placeholder">
            <p><?= t('Map with %s marker(s) – shown in view mode.', count($markerList)) ?></p>
        </div>
    <?php } else { ?>
        <div class="fewo-map__canvas" data-fewo-map="<?= h(json_encode($config, JSON_UNESCAPED_UNICODE)) ?>"></div>
        <?php if ($config['requireConsent']) { ?>
            <div class="fewo-map__consent" data-fewo-map-consent>
                <p><?= t('When the map is loaded, data is transferred to OpenStreetMap.') ?></p>
                <button type="button" class="btn btn-primary fewo-btn" data-fewo-map-load><?= t('Load map') ?></button>
                <label class="fewo-map__remember">
                    <input type="checkbox" data-fewo-map-remember> <?= t('Always load maps') ?>
                </label>
                <p class="fewo-map__fallback">
                    <a href="https://www.openstreetmap.org/?mlat=<?= (float) $first['lat'] ?>&amp;mlon=<?= (float) $first['lng'] ?>#map=15/<?= (float) $first['lat'] ?>/<?= (float) $first['lng'] ?>" target="_blank" rel="noopener"><?= t('Open map in a new window') ?></a>
                </p>
            </div>
        <?php } ?>
    <?php } ?>
    <noscript>
        <p><a href="https://www.openstreetmap.org/?mlat=<?= (float) $first['lat'] ?>&amp;mlon=<?= (float) $first['lng'] ?>#map=15/<?= (float) $first['lat'] ?>/<?= (float) $first['lng'] ?>"><?= t('Open map in a new window') ?></a></p>
    </noscript>
</div>
