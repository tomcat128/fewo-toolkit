<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\News\ImportanceLevels;

/**
 * Block "Neuigkeiten": Stufen der Wichtigkeit bearbeiten – je Zeile der Code aus dem Feed von Fewo-Office, die
 * Bezeichnung über dem Titel und die Farbe. Die Reihenfolge der Zeilen ist die Reihenfolge der Anzeige.
 *
 * @var array<int, array{code: string, label: string, color: string}> $levels
 */

$rowTemplate = static function (string $code = '', string $label = '', string $color = ImportanceLevels::NEUTRAL_COLOR): string {
    $button = static fn (string $action, string $symbol, string $title, string $class = 'btn-outline-secondary'): string => '<button type="button" class="btn btn-sm ' . $class . '" data-fewo-' . $action
        . ' title="' . h($title) . '" aria-label="' . h($title) . '">' . $symbol . '</button>';

    return '<tr class="fewo-level-row">'
        . '<td style="width: 26%"><input type="text" class="form-control" name="levelCode[]" value="' . h($code) . '" list="fewo-level-codes" required'
        . ' maxlength="' . ImportanceLevels::MAX_CODE_CHARS . '" pattern="[A-Za-z0-9_\-]+" aria-label="' . h(t('Code in the feed')) . '" placeholder="info"></td>'
        . '<td><input type="text" class="form-control" name="levelLabel[]" value="' . h($label) . '" maxlength="' . ImportanceLevels::MAX_LABEL_CHARS . '"'
        . ' aria-label="' . h(t('Label')) . '" placeholder="' . h(t('e.g. Information')) . '"></td>'
        . '<td style="width: 1%"><input type="color" class="form-control form-control-color" name="levelColor[]" value="' . h($color) . '"'
        . ' aria-label="' . h(t('Colour')) . '" title="' . h(t('Choose a colour')) . '"></td>'
        . '<td class="text-nowrap" style="width: 1%">'
        . $button('up', '↑', t('Move up')) . ' ' . $button('down', '↓', t('Move down')) . ' ' . $button('remove', '×', t('Remove'), 'btn-outline-danger')
        . '</td></tr>';
};
?>
<div id="fewo-news-levels" data-max="<?= ImportanceLevels::MAX_LEVELS ?>">
    <p class="form-text mt-0">
        <?= t('Fewo-Office marks each news item with a code: info, notice or important. Here you decide which label and colour each code gets. The order of the rows is the order on the page – the first level is shown at the top. News with a code that is not listed are shown in grey without a label.') ?>
    </p>
    <table class="table table-sm align-middle mb-2">
        <thead>
            <tr>
                <th scope="col"><?= t('Code in the feed') ?></th>
                <th scope="col"><?= t('Label') ?></th>
                <th scope="col"><?= t('Colour') ?></th>
                <th scope="col"><span class="visually-hidden"><?= t('Order') ?></span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($levels as $level) {
                echo $rowTemplate($level['code'], $level['label'], $level['color']);
            } ?>
        </tbody>
    </table>
    <datalist id="fewo-level-codes">
        <option value="info"></option>
        <option value="notice"></option>
        <option value="important"></option>
    </datalist>
    <button type="button" class="btn btn-secondary btn-sm" data-fewo-add><?= t('Add level') ?></button>
    <button type="button" class="btn btn-link btn-sm" data-fewo-defaults><?= t('Restore defaults') ?></button>
    <template data-fewo-template><?= $rowTemplate() ?></template>
    <template data-fewo-defaults-template><?php foreach (ImportanceLevels::defaults() as $level) {
        echo $rowTemplate($level['code'], $level['label'], $level['color']);
    } ?></template>
</div>
<script>
(() => {
    const root = document.getElementById('fewo-news-levels');
    const tbody = root.querySelector('tbody');
    const add = root.querySelector('[data-fewo-add]');
    const max = parseInt(root.dataset.max, 10);
    const update = () => {
        const rows = Array.from(tbody.children);
        add.disabled = rows.length >= max;
        rows.forEach((row, index) => {
            row.querySelector('[data-fewo-up]').disabled = index === 0;
            row.querySelector('[data-fewo-down]').disabled = index === rows.length - 1;
            // Die letzte Zeile bleibt: Ohne Stufen gälten ohnehin wieder die Vorgaben
            row.querySelector('[data-fewo-remove]').disabled = rows.length === 1;
        });
    };
    add.addEventListener('click', () => {
        if (tbody.children.length < max) {
            tbody.insertAdjacentHTML('beforeend', root.querySelector('template[data-fewo-template]').innerHTML);
            tbody.lastElementChild.querySelector('input').focus();
            update();
        }
    });
    root.querySelector('[data-fewo-defaults]').addEventListener('click', () => {
        tbody.innerHTML = root.querySelector('template[data-fewo-defaults-template]').innerHTML;
        update();
    });
    tbody.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (event.target.closest('[data-fewo-remove]')) {
            row.remove();
        } else if (event.target.closest('[data-fewo-up]') && row.previousElementSibling) {
            row.previousElementSibling.before(row);
        } else if (event.target.closest('[data-fewo-down]') && row.nextElementSibling) {
            row.nextElementSibling.after(row);
        } else {
            return;
        }
        update();
    });
    // Eingabetaste in einem Textfeld: nicht den Dialog absenden
    tbody.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input[type="text"]')) {
            event.preventDefault();
        }
    });
    update();
})();
</script>
