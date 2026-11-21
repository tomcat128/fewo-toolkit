<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Erklärungen zu Stichwörtern bearbeiten (Dashboard › KI-Assistent): je Zeile die Stichwörter und der Text,
 * den das Sprachmodell bekommt, wenn eines davon in einer Frage vorkommt.
 *
 * @var array<int, array{keywords: string, text: string}> $notes
 * @var int $max höchste Anzahl
 * @var int $maxKeywords höchste Länge der Stichwörter
 * @var int $maxText höchste Länge einer Erklärung
 */

$rowTemplate = static function (string $keywords = '', string $text = '') use ($maxKeywords, $maxText): string {
    return '<tr class="fewo-note-row">'
        . '<td style="width: 30%"><input type="text" class="form-control" name="noteKeywords[]" value="' . h($keywords) . '" maxlength="' . (int) $maxKeywords . '"'
        . ' aria-label="' . h(t('Keywords')) . '" placeholder="' . h(t('e.g. parking space, parking, car')) . '"></td>'
        . '<td><textarea class="form-control" name="noteTexts[]" rows="2" maxlength="' . (int) $maxText . '"'
        . ' aria-label="' . h(t('Explanation')) . '" placeholder="' . h(t('e.g. The apartment has no parking space of its own. Free parking is available along the street.')) . '">' . h($text) . '</textarea></td>'
        . '<td class="text-nowrap" style="width: 1%">'
        . '<button type="button" class="btn btn-sm btn-outline-danger" data-fewo-remove title="' . h(t('Remove')) . '" aria-label="' . h(t('Remove')) . '">×</button>'
        . '</td></tr>';
};
?>
<div data-fewo-notes id="fewo-assistant-notes" data-max="<?= (int) $max ?>">
    <table class="table table-sm align-top mb-2">
        <thead<?= $notes === [] ? ' hidden' : '' ?>>
            <tr>
                <th scope="col"><?= t('Keywords') ?></th>
                <th scope="col"><?= t('Explanation') ?></th>
                <th scope="col"><span class="visually-hidden"><?= t('Remove') ?></span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notes as $note) {
                echo $rowTemplate((string) $note['keywords'], (string) $note['text']);
            } ?>
        </tbody>
    </table>
    <p class="text-muted small mb-2" data-fewo-empty<?= $notes === [] ? '' : ' hidden' ?>><?= t('No explanations yet.') ?></p>
    <button type="button" class="btn btn-secondary btn-sm" data-fewo-add><?= t('Add explanation') ?></button>
    <template data-fewo-template><?= $rowTemplate() ?></template>
</div>
<script>
(() => {
    const root = document.getElementById('fewo-assistant-notes');
    const tbody = root.querySelector('tbody');
    const head = root.querySelector('thead');
    const template = root.querySelector('template[data-fewo-template]');
    const add = root.querySelector('[data-fewo-add]');
    const empty = root.querySelector('[data-fewo-empty]');
    const max = parseInt(root.dataset.max, 10);
    const update = () => {
        add.disabled = tbody.children.length >= max;
        empty.hidden = tbody.children.length > 0;
        head.hidden = tbody.children.length === 0;
    };
    add.addEventListener('click', () => {
        if (tbody.children.length >= max) {
            return;
        }
        tbody.insertAdjacentHTML('beforeend', template.innerHTML);
        tbody.lastElementChild.querySelector('input').focus();
        update();
    });
    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-fewo-remove]');
        if (button) {
            button.closest('tr').remove();
            update();
        }
    });
    // Eingabetaste im Feld der Stichwörter: weiter zur Erklärung statt Absenden des Formulars
    tbody.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input')) {
            event.preventDefault();
            event.target.closest('tr').querySelector('textarea').focus();
        }
    });
    update();
})();
</script>
