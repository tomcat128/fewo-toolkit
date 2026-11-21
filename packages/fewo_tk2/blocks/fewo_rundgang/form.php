<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var bool $autoLoad
 * @var bool $compass
 * @var string $author
 * @var bool $useCustomCode
 * @var string $viewerCode
 * @var string $aspectRatio
 * @var string $cssClass
 * @var array $aspectOptions
 * @var array{tour: array, files: array} $editorData
 * @var Concrete\Core\Entity\File\File|null $previewFile
 * @var string $pannellumPath
 */

$fileManager = app('helper/concrete/file_manager');
$uid = 'fewo-tour-editor-' . bin2hex(random_bytes(4));
$code = ['class' => 'form-control font-monospace', 'style' => 'font-size: .8125rem; white-space: pre; overflow-x: auto', 'spellcheck' => 'false', 'wrap' => 'off'];

// Texte des Szenen-Editors (auto.js)
$labels = [
    'name' => t('Name'),
    'title' => t('Title'),
    'titlePlaceholder' => t('e.g. Living room'),
    'startRoom' => t('Start room'),
    'edit' => t('Edit'),
    'chooseImage' => t('Choose image'),
    'changeImage' => t('Change image'),
    'noImage' => t('No image yet'),
    'moveUp' => t('Move up'),
    'moveDown' => t('Move down'),
    'remove' => t('Remove'),
    'removeRoom' => t('Remove the room "%s" with its signposts and notes?'),
    'noRooms' => t('No rooms yet. Add the 360° images of the rooms from the file manager.'),
    'editing' => t('Edit room: %s'),
    'selectRoom' => t('Select a room with an image to edit it.'),
    'setStart' => t('Use current view as start position'),
    'startSaved' => t('Start position saved: this is what guests see first in this room.'),
    'startValue' => t('Start position: %1$s° horizontal, %2$s° vertical'),
    'addSignpost' => t('Add signpost'),
    'addNote' => t('Add note'),
    'placeSignpost' => t('Click the spot in the panorama where the signpost to another room should be.'),
    'placeNote' => t('Click the spot in the panorama that the note refers to.'),
    'placeMove' => t('Click the new spot in the panorama.'),
    'cancel' => t('Cancel'),
    'needSecondRoom' => t('A signpost needs a second room. Add another room first.'),
    'hotSpots' => t('Signposts and notes in this room'),
    'noHotSpots' => t('None yet. Use the buttons above the panorama and click the spot in the image.'),
    'signpost' => t('Signpost'),
    'note' => t('Note'),
    'target' => t('Leads to'),
    'text' => t('Text'),
    'signpostText' => t('Text (empty = title of the room)'),
    'noteText' => t('e.g. Coffee machine'),
    'show' => t('Show'),
    'move' => t('Move'),
    'arrival' => t('View on arrival'),
    'arrivalDefault' => t('Start position of the room'),
    'arrivalCustom' => t('own direction'),
    'arrivalSet' => t('Set'),
    'arrivalReset' => t('Reset'),
    'arrivalPrompt' => t('Turn the view to what guests should see when they arrive in "%s" through this signpost.'),
    'arrivalApply' => t('Use this view'),
    'horizon' => t('Straighten the horizon'),
    'horizonRoll' => t('Tilt (°)'),
    'horizonPitch' => t('Pitch (°)'),
    'importDone' => t('%s rooms taken over. Rooms without a matching image are marked – choose their images.'),
    'importFailed' => t('The settings could not be read: %s'),
    'importEmpty' => t('The settings do not contain any rooms ("scenes").'),
    'loadFailed' => t('The file could not be loaded.'),
];
?>
<style>
    .fewo-tour-editor__rooms td { vertical-align: middle; }
    .fewo-tour-editor__thumb { width: 96px; height: 48px; object-fit: cover; border-radius: 4px; background: #e9ecef; display: block; }
    .fewo-tour-editor__thumb--missing { display: flex; align-items: center; justify-content: center; font-size: .6875rem; color: #b42318; text-align: center; line-height: 1.1; }
    .fewo-tour-editor__rooms tr.is-current { outline: 2px solid #0d6efd; outline-offset: -2px; }
    /* Bootstrap lässt Überschriften von Feldgruppen (legend) links umfließen */
    .fewo-tour-editor fieldset > * { clear: both; }
    /* Höhe gegen die Vorgabe von Pannellum (.pnlm-container: 100 %) durchsetzen */
    .fewo-tour-editor .fewo-tour-editor__viewer { height: 380px; border-radius: 6px; overflow: hidden; background: #f4f4f4; }
    .fewo-tour-editor__viewer.is-placing, .fewo-tour-editor__viewer.is-placing .pnlm-dragfix { cursor: crosshair !important; }
    .fewo-tour-editor__status { min-height: 1.5rem; margin: .5rem 0; font-size: .875rem; }
    .fewo-tour-editor__status.is-prompt { padding: .5rem .75rem; border-radius: 6px; background: #fff6e0; color: #6b4b00; }
    .fewo-tour-editor__toolbar { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
    .fewo-tour-editor__horizon input { width: 6rem; }
    .fewo-tour-editor .pnlm-container img { max-width: none; }
</style>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
</div>

<div class="fewo-tour-editor" id="<?= $uid ?>">
    <input type="hidden" name="tour" value="<?= h(json_encode($editorData['tour'], JSON_UNESCAPED_UNICODE)) ?>">

    <fieldset>
        <legend><?= t('Rooms') ?></legend>
        <p class="form-text mt-0"><?= t('Each room is a 360° image from the file manager. The name identifies the room – signposts refer to it, and in your own Pannellum settings the image is available as {name}.') ?></p>
        <table class="table table-sm fewo-tour-editor__rooms">
            <thead>
                <tr>
                    <th><?= t('Image') ?></th>
                    <th><?= t('Name') ?></th>
                    <th><?= t('Title (shown in the tour)') ?></th>
                    <th><?= t('Start room') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-rooms></tbody>
        </table>
        <button type="button" class="btn btn-secondary btn-sm" data-add-images><?= t('Add images from the file manager') ?></button>
    </fieldset>

    <fieldset class="mt-4" data-scene-editor>
        <legend data-scene-heading><?= t('Scene editor') ?></legend>
        <div data-scene-empty class="alert alert-info"><?= h($labels['selectRoom']) ?></div>
        <div data-scene-body hidden>
            <div class="fewo-tour-editor__toolbar">
                <button type="button" class="btn btn-sm btn-primary" data-set-start><?= h($labels['setStart']) ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-place="scene"><?= h($labels['addSignpost']) ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-place="info"><?= h($labels['addNote']) ?></button>
            </div>
            <div class="fewo-tour-editor__viewer" data-viewer></div>
            <div class="fewo-tour-editor__status" data-status role="status" aria-live="polite"></div>

            <h5 class="mt-3"><?= h($labels['hotSpots']) ?></h5>
            <div data-hotspots></div>

            <details class="mt-3 fewo-tour-editor__horizon">
                <summary><?= h($labels['horizon']) ?></summary>
                <div class="d-flex gap-3 mt-2">
                    <label><?= h($labels['horizonRoll']) ?> <input type="number" class="form-control form-control-sm" step="0.25" min="-45" max="45" data-horizon="horizonRoll"></label>
                    <label><?= h($labels['horizonPitch']) ?> <input type="number" class="form-control form-control-sm" step="0.25" min="-45" max="45" data-horizon="horizonPitch"></label>
                </div>
            </details>
        </div>
    </fieldset>

    <fieldset class="mt-4">
        <legend><?= t('Display') ?></legend>
        <div class="row">
            <div class="col-sm-4 mb-3">
                <?= $form->label('aspectRatio', t('Aspect ratio')) ?>
                <?= $form->select('aspectRatio', $aspectOptions, $aspectRatio) ?>
            </div>
            <div class="col-sm-4 mb-3">
                <?= $form->label('cssClass', t('CSS class (optional)')) ?>
                <?= $form->text('cssClass', $cssClass, ['maxlength' => 64]) ?>
            </div>
            <div class="col-sm-4 mb-3">
                <?= $form->label('author', t('Photographer / copyright (optional)')) ?>
                <?= $form->text('author', $author, ['maxlength' => 255]) ?>
            </div>
        </div>
        <div class="form-check">
            <?= $form->checkbox('autoLoad', 1, (bool) $autoLoad) ?>
            <?= $form->label('autoLoad', t('Load the tour immediately (otherwise after a click – saves data volume on mobile devices)'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="form-check mb-3">
            <?= $form->checkbox('compass', 1, (bool) $compass) ?>
            <?= $form->label('compass', t('Show compass'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="mb-3">
            <?= $form->label('previewFID', t('Preview image (optional)')) ?>
            <?= $fileManager->image($uid . '-preview', 'previewFID', t('Choose image'), $previewFile) ?>
            <div class="form-text"><?= t('Shown until the tour has loaded, e.g. a photo or the logo.') ?></div>
        </div>
    </fieldset>

    <details class="mt-4"<?= $useCustomCode ? ' open' : '' ?>>
        <summary class="h5"><?= t('For experts: Pannellum settings') ?></summary>

        <div class="mb-3 mt-3">
            <label class="form-label" for="<?= $uid ?>-import"><?= t('Take over an existing tour') ?></label>
            <textarea id="<?= $uid ?>-import" data-import-code rows="4" class="<?= $code['class'] ?>" style="<?= $code['style'] ?>" spellcheck="false" wrap="off"></textarea>
            <div class="form-text"><?= t('Paste the "Pannellum settings" of the previous Pannellum block. Rooms, start positions, signposts and notes are taken over into the scene editor. Images are matched by file name with the rooms above; choose the remaining ones afterwards.') ?></div>
            <button type="button" class="btn btn-secondary btn-sm mt-2" data-import><?= t('Take over into the scene editor') ?></button>
        </div>

        <div class="form-check mb-2">
            <?= $form->checkbox('useCustomCode', 1, (bool) $useCustomCode) ?>
            <?= $form->label('useCustomCode', t('Use my own Pannellum settings instead of the scene editor'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="mb-3">
            <?= $form->label('viewerCode', t('Pannellum settings')) ?>
            <?= $form->textarea('viewerCode', (string) $viewerCode, $code + ['rows' => 10]) ?>
            <div class="form-text">
                <?= t('Configuration of the Pannellum viewer without the surrounding braces, as in the previous Pannellum block. The images of the rooms above are available as {name}.') ?>
                <a href="https://pannellum.org/documentation/reference/" target="_blank" rel="noopener"><?= t('Pannellum documentation') ?></a>
            </div>
        </div>
    </details>
</div>

<link rel="stylesheet" href="<?= h($pannellumPath) ?>/pannellum.css">
<script>
(() => {
    const options = {
        root: document.getElementById(<?= json_encode($uid) ?>),
        files: <?= json_encode((object) $editorData['files'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
    };
    // Pannellum und der Editor (auto.js) werden von Concrete nachgeladen – warten, bis beide da sind
    const start = () => {
        if (window.pannellum && window.FewoTourEditor) {
            window.FewoTourEditor(options);
        } else {
            setTimeout(start, 50);
        }
    };
    if (!window.pannellum && !document.querySelector('script[data-fewo-pannellum]')) {
        const script = document.createElement('script');
        script.src = <?= json_encode($pannellumPath . '/pannellum.js') ?>;
        script.dataset.fewoPannellum = '1';
        document.head.appendChild(script);
    }
    start();
})();
</script>
