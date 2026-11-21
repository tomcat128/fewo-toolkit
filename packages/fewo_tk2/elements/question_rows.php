<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Beispielfragen eines Chats bearbeiten: eine Zeile je Frage, mit Verschieben und Entfernen.
 *
 * @var string $name Name der Formularfelder (wird als name[] gesendet)
 * @var string[] $questions
 * @var int $max höchste Anzahl
 * @var int $maxLength höchste Länge einer Frage
 */

$rowTemplate = static function (string $question = '') use ($name, $maxLength): string {
    return '<tr class="fewo-question-row">'
        . '<td><input type="text" class="form-control" name="' . h($name) . '[]" value="' . h($question) . '" maxlength="' . (int) $maxLength . '"'
        . ' aria-label="' . h(t('Example question')) . '" placeholder="' . h(t('e.g. Is there a parking space?')) . '"></td>'
        . '<td class="text-nowrap" style="width: 1%">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-fewo-move="-1" title="' . h(t('Move up')) . '" aria-label="' . h(t('Move up')) . '">↑</button> '
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-fewo-move="1" title="' . h(t('Move down')) . '" aria-label="' . h(t('Move down')) . '">↓</button> '
        . '<button type="button" class="btn btn-sm btn-outline-danger" data-fewo-remove title="' . h(t('Remove')) . '" aria-label="' . h(t('Remove')) . '">×</button>'
        . '</td></tr>';
};
$uid = 'fewo-questions-' . bin2hex(random_bytes(4));
?>
<div data-fewo-questions id="<?= $uid ?>" data-max="<?= (int) $max ?>">
    <table class="table table-sm align-middle mb-2">
        <tbody>
            <?php foreach ($questions as $question) {
                echo $rowTemplate((string) $question);
            } ?>
        </tbody>
    </table>
    <p class="text-muted small mb-2" data-fewo-empty<?= $questions === [] ? '' : ' hidden' ?>><?= t('No example questions – the chat shows only the input field.') ?></p>
    <button type="button" class="btn btn-secondary btn-sm" data-fewo-add><?= t('Add question') ?></button>
    <div class="form-text"><?= t('Visitors can ask these questions with one click. Up to %s questions; remove all of them to show none.', (int) $max) ?></div>
    <template data-fewo-template><?= $rowTemplate() ?></template>
</div>
<script>
(() => {
    const root = document.getElementById('<?= $uid ?>');
    const tbody = root.querySelector('tbody');
    const template = root.querySelector('template[data-fewo-template]');
    const add = root.querySelector('[data-fewo-add]');
    const empty = root.querySelector('[data-fewo-empty]');
    const max = parseInt(root.dataset.max, 10);
    const update = () => {
        add.disabled = tbody.children.length >= max;
        empty.hidden = tbody.children.length > 0;
    };
    const addRow = () => {
        if (tbody.children.length >= max) {
            return;
        }
        tbody.insertAdjacentHTML('beforeend', template.innerHTML);
        tbody.lastElementChild.querySelector('input').focus();
        update();
    };
    add.addEventListener('click', addRow);
    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }
        const row = button.closest('tr');
        if (button.hasAttribute('data-fewo-remove')) {
            row.remove();
            update();
        } else if (button.dataset.fewoMove === '-1' && row.previousElementSibling) {
            row.previousElementSibling.before(row);
        } else if (button.dataset.fewoMove === '1' && row.nextElementSibling) {
            row.nextElementSibling.after(row);
        }
    });
    // Eingabetaste: nächste Frage statt Absenden des Formulars
    tbody.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input')) {
            event.preventDefault();
            if (event.target.closest('tr') === tbody.lastElementChild) {
                addRow();
            }
        }
    });
    update();
})();
</script>
