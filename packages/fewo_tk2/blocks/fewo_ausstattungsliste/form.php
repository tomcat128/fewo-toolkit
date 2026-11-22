<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var int $columnCount
 * @var bool $collapsible
 * @var string $listStyle
 * @var array<string, string> $styleOptions
 * @var array $itemList
 */

$rowTemplate = static function (string $name = '', string $note = ''): string {
    return '<tr class="fewo-item-row">'
        . '<td><input type="text" class="form-control" name="itemName[]" value="' . h($name) . '" placeholder="' . h(t('e.g. Dishwasher')) . '"></td>'
        . '<td><input type="text" class="form-control" name="itemNote[]" value="' . h($note) . '" placeholder="' . h(t('optional')) . '"></td>'
        . '<td class="text-nowrap">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-fewo-move="-1" title="' . h(t('Move up')) . '">↑</button> '
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-fewo-move="1" title="' . h(t('Move down')) . '">↓</button> '
        . '<button type="button" class="btn btn-sm btn-outline-danger" data-fewo-remove title="' . h(t('Remove')) . '">×</button>'
        . '</td></tr>';
};
$uid = 'fewo-items-' . bin2hex(random_bytes(4));
?>
<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title, ['placeholder' => t('e.g. Kitchen')]) ?>
</div>

<div class="mb-3">
    <?= $form->label('listStyle', t('Style')) ?>
    <?= $form->select('listStyle', $styleOptions, $listStyle) ?>
    <div class="form-text"><?= t('"Labels side by side" fills the width line by line and ignores the number of columns.') ?></div>
</div>

<div class="row">
    <div class="col-sm-6 mb-3">
        <?= $form->label('columnCount', t('Columns')) ?>
        <?= $form->select('columnCount', [1 => 1, 2 => 2, 3 => 3, 4 => 4], (int) $columnCount) ?>
    </div>
    <div class="col-sm-6 mb-3 d-flex align-items-end">
        <div class="form-check">
            <?= $form->checkbox('collapsible', 1, (bool) $collapsible) ?>
            <?= $form->label('collapsible', t('Collapsed by default (expandable)'), ['class' => 'form-check-label']) ?>
        </div>
    </div>
</div>

<div data-fewo-items id="<?= $uid ?>">
    <table class="table table-sm align-middle">
        <thead>
            <tr>
                <th><?= t('Amenity') ?></th>
                <th><?= t('Note (shown as footnote)') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itemList as $item) {
                echo $rowTemplate($item['name'], $item['note'] ?? '');
            } ?>
            <?php if ($itemList === []) {
                echo $rowTemplate();
            } ?>
        </tbody>
    </table>
    <button type="button" class="btn btn-secondary btn-sm" data-fewo-add><?= t('Add amenity') ?></button>
    <div class="form-text"><?= t('Tip: press Enter in the last row to add a new one.') ?></div>
    <template data-fewo-template><?= $rowTemplate() ?></template>
</div>

<script>
(() => {
    const root = document.getElementById('<?= $uid ?>');
    const tbody = root.querySelector('tbody');
    const template = root.querySelector('template[data-fewo-template]');
    const addRow = () => {
        tbody.insertAdjacentHTML('beforeend', template.innerHTML);
        tbody.lastElementChild.querySelector('input').focus();
    };
    root.querySelector('[data-fewo-add]').addEventListener('click', addRow);
    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }
        const row = button.closest('tr');
        if (button.hasAttribute('data-fewo-remove')) {
            row.remove();
            if (!tbody.children.length) {
                addRow();
            }
        } else if (button.dataset.fewoMove === '-1' && row.previousElementSibling) {
            row.previousElementSibling.before(row);
        } else if (button.dataset.fewoMove === '1' && row.nextElementSibling) {
            row.nextElementSibling.after(row);
        }
    });
    tbody.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input')) {
            event.preventDefault();
            if (event.target.closest('tr') === tbody.lastElementChild) {
                addRow();
            }
        }
    });
})();
</script>
