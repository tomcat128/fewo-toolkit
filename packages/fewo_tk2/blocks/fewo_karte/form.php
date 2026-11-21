<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var array $markerList
 * @var int $zoom
 * @var int $height
 * @var bool $requireConsent
 * @var string $tileUrl
 * @var string $attribution
 */

$uid = 'fewo-map-form-' . bin2hex(random_bytes(4));
$row = static function (array $marker = []): string {
    $value = static fn (string $key): string => h((string) ($marker[$key] ?? ''));

    return '<div class="card mb-2 fewo-marker"><div class="card-body p-2">'
        . '<div class="row g-2">'
        . '<div class="col-6"><input type="text" class="form-control form-control-sm" name="markerLat[]" value="' . $value('lat') . '" placeholder="' . h(t('Latitude, e.g. 47.7102')) . '" inputmode="decimal"></div>'
        . '<div class="col-6"><input type="text" class="form-control form-control-sm" name="markerLng[]" value="' . $value('lng') . '" placeholder="' . h(t('Longitude, e.g. 9.1024')) . '" inputmode="decimal"></div>'
        . '<div class="col-12"><input type="text" class="form-control form-control-sm" name="markerTitle[]" value="' . $value('title') . '" placeholder="' . h(t('Title, e.g. Apartment Seeherz')) . '"></div>'
        . '<div class="col-12"><textarea class="form-control form-control-sm" name="markerText[]" rows="2" placeholder="' . h(t('Description (optional)')) . '">' . $value('text') . '</textarea></div>'
        . '</div>'
        . '<button type="button" class="btn btn-sm btn-link text-danger px-0" data-fewo-remove>' . h(t('Remove marker')) . '</button>'
        . '</div></div>';
};
?>
<div id="<?= $uid ?>">
    <fieldset class="mb-3">
        <legend><?= t('Markers') ?></legend>
        <div class="form-text mb-2">
            <?= t('Find the place on %s, right-click it and choose "Show address" – the coordinates are shown on the left. You can paste "47.7102, 9.1024" into the latitude field; it is split automatically.', '<a href="https://www.openstreetmap.org" target="_blank" rel="noopener">openstreetmap.org</a>') ?>
        </div>
        <div data-fewo-markers>
            <?php foreach ($markerList ?: [[]] as $marker) {
                echo $row($marker);
            } ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-fewo-add><?= t('Add marker') ?></button>
        <template data-fewo-template><?= $row() ?></template>
    </fieldset>

    <fieldset>
        <legend><?= t('Display') ?></legend>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <?= $form->label('zoom', t('Zoom level')) ?>
                <?= $form->select('zoom', [0 => t('Automatic (show all markers)')] + array_combine(range(5, 18), range(5, 18)), (int) $zoom) ?>
            </div>
            <div class="col-sm-6 mb-3">
                <?= $form->label('height', t('Height in pixels')) ?>
                <?= $form->number('height', (int) $height, ['min' => 150, 'max' => 1200, 'step' => 10]) ?>
            </div>
        </div>
        <div class="form-check mb-3">
            <?= $form->checkbox('requireConsent', 1, (bool) $requireConsent) ?>
            <?= $form->label('requireConsent', t('Load the map only after the visitor clicks (recommended for GDPR compliance)'), ['class' => 'form-check-label']) ?>
        </div>
        <details class="mb-3">
            <summary><?= t('Advanced: tile server') ?></summary>
            <div class="mt-2 mb-2">
                <?= $form->label('tileUrl', t('Tile server URL')) ?>
                <?= $form->text('tileUrl', $tileUrl) ?>
            </div>
            <div class="mb-2">
                <?= $form->label('attribution', t('Attribution (HTML)')) ?>
                <?= $form->text('attribution', $attribution) ?>
                <div class="form-text"><?= t('The public OpenStreetMap servers are intended for low traffic. For high traffic use a commercial provider or your own tile server.') ?></div>
            </div>
        </details>
    </fieldset>
</div>
<script>
(() => {
    const root = document.getElementById('<?= $uid ?>');
    const list = root.querySelector('[data-fewo-markers]');
    const template = root.querySelector('template[data-fewo-template]');
    root.querySelector('[data-fewo-add]').addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML);
        list.lastElementChild.querySelector('input').focus();
    });
    list.addEventListener('click', (event) => {
        if (event.target.closest('[data-fewo-remove]')) {
            event.target.closest('.fewo-marker').remove();
        }
    });
    // "47.71, 9.10" im Breitengrad-Feld automatisch aufteilen: beim Einfügen sofort, beim Tippen erst
    // nach Verlassen des Feldes – sonst würde schon "47.71, 9" getrennt, bevor die Eingabe fertig ist.
    const splitCoordinates = (input) => {
        const match = input.value.match(/^\s*(-?\d+(?:[.,]\d+)?)\s*[,;\s]\s*(-?\d+(?:[.,]\d+)?)\s*$/);
        // "47,7102" allein ist eine Zahl mit Dezimalkomma und kein Koordinatenpaar
        if (match && !/^\s*-?\d+,\d+\s*$/.test(input.value)) {
            input.value = match[1].replace(',', '.');
            input.closest('.fewo-marker').querySelector('input[name="markerLng[]"]').value = match[2].replace(',', '.');
        }
    };
    list.addEventListener('input', (event) => {
        if (event.target.matches('input[name="markerLat[]"]') && ['insertFromPaste', 'insertFromDrop'].includes(event.inputType)) {
            splitCoordinates(event.target);
        }
    });
    list.addEventListener('change', (event) => {
        if (event.target.matches('input[name="markerLat[]"]')) {
            splitCoordinates(event.target);
        }
    });
})();
</script>
