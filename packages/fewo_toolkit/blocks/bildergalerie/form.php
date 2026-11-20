<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $source
 * @var int $folderID
 * @var int $fileSetID
 * @var string $sortOrder
 * @var int $columnCount
 * @var string $aspectRatio
 * @var bool $showCaptions
 * @var array $folderOptions
 * @var array $setOptions
 * @var array $sortOptions
 * @var array $ratioOptions
 */

$uid = 'fewo-gallery-' . bin2hex(random_bytes(4));
?>
<div id="<?= $uid ?>">
    <div class="mb-3">
        <?= $form->label('source', t('Images from')) ?>
        <?= $form->select('source', ['folder' => t('Folder in the file manager'), 'set' => t('File set')], $source) ?>
    </div>
    <div class="mb-3" data-source="folder">
        <?= $form->label('folderID', t('Folder')) ?>
        <?= $form->select('folderID', $folderOptions, (int) $folderID ?: '') ?>
        <div class="form-text"><?= t('All images directly in this folder are shown. Use the image description or title as caption.') ?></div>
    </div>
    <div class="mb-3" data-source="set">
        <?= $form->label('fileSetID', t('File set')) ?>
        <?= $form->select('fileSetID', $setOptions, (int) $fileSetID ?: '') ?>
    </div>
    <div class="row">
        <div class="col-sm-6 mb-3">
            <?= $form->label('sortOrder', t('Order')) ?>
            <?= $form->select('sortOrder', $sortOptions, $sortOrder) ?>
        </div>
        <div class="col-sm-3 mb-3">
            <?= $form->label('columnCount', t('Columns')) ?>
            <?= $form->select('columnCount', array_combine(range(1, 6), range(1, 6)), (int) $columnCount) ?>
        </div>
        <div class="col-sm-3 mb-3">
            <?= $form->label('aspectRatio', t('Format')) ?>
            <?= $form->select('aspectRatio', $ratioOptions, $aspectRatio) ?>
        </div>
    </div>
    <div class="form-check">
        <?= $form->checkbox('showCaptions', 1, (bool) $showCaptions) ?>
        <?= $form->label('showCaptions', t('Show captions in the lightbox'), ['class' => 'form-check-label']) ?>
    </div>
</div>
<script>
(() => {
    const root = document.getElementById('<?= $uid ?>');
    const select = root.querySelector('select[name="source"]');
    const update = () => root.querySelectorAll('[data-source]').forEach((el) => {
        el.hidden = el.dataset.source !== select.value;
    });
    select.addEventListener('change', update);
    update();
})();
</script>
