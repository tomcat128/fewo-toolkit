<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var array $images
 * @var array $ratio
 * @var int $columnCount
 * @var bool $showCaptions
 */

if ($images === []) {
    $page = Concrete\Core\Page\Page::getCurrentPage();
    if ($page && $page->isEditMode()) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('Image gallery: no images found.') ?></div>
    <?php }

    return;
}

$id = 'fewo-gallery-' . (int) $bID;
?>
<div class="fewo-gallery" id="<?= $id ?>"
     style="--fewo-columns: <?= (int) $columnCount ?>; --fewo-ratio: <?= (int) $ratio[0] ?> / <?= (int) $ratio[1] ?>"
     data-fewo-gallery
     data-label-close="<?= h(t('Close')) ?>"
     data-label-prev="<?= h(t('Previous image')) ?>"
     data-label-next="<?= h(t('Next image')) ?>"
     data-label-counter="<?= h(t('Image %1$s of %2$s', '{index}', '{total}')) ?>"
     data-show-captions="<?= $showCaptions ? '1' : '0' ?>">
    <ul class="fewo-gallery__grid" role="list">
        <?php foreach ($images as $index => $image) { ?>
            <li class="fewo-gallery__item">
                <a href="<?= h($image['large']) ?>"
                   class="fewo-gallery__link"
                   data-width="<?= $image['largeWidth'] ?>"
                   data-height="<?= $image['largeHeight'] ?>"
                   data-caption="<?= h($image['caption']) ?>">
                    <img src="<?= h($image['thumb']) ?>"
                         width="<?= $image['thumbWidth'] ?>"
                         height="<?= $image['thumbHeight'] ?>"
                         alt="<?= h($image['alt']) ?>"
                         loading="<?= $index < $columnCount ? 'eager' : 'lazy' ?>"
                         decoding="async">
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
