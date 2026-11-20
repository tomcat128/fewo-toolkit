<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoToolkit\Support\Money;

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoToolkit\Entity\Pricelist $pricelist
 * @var DateTimeImmutable $today
 */

$token = app('token');
$form = app('helper/form');
$maxGuests = $pricelist->getMaxGuests();
$columns = max($maxGuests, 1);

$seasonRow = static function (string $key, ?FewoToolkit\Entity\Season $season, int $columns, DateTimeImmutable $today): string {
    $prices = $season ? $season->getPrices() : [];
    $isPast = $season && $season->getDateTo() < $today;
    $name = static fn (string $field): string => 'seasons[' . $key . '][' . $field . ']';
    $html = '<tr class="fewo-season' . ($isPast ? ' table-secondary' : '') . '">';
    $html .= '<td><input type="text" class="form-control form-control-sm" name="' . $name('name') . '" value="' . h($season ? $season->getName() : '') . '" placeholder="' . h(t('e.g. Main season')) . '" style="min-width: 9rem">';
    if ($isPast) {
        $html .= '<span class="badge bg-secondary mt-1">' . h(t('expired')) . '</span>';
    }
    $html .= '</td>';
    $html .= '<td><input type="date" class="form-control form-control-sm" name="' . $name('from') . '" value="' . ($season ? $season->getDateFrom()->format('Y-m-d') : '') . '"></td>';
    $html .= '<td><input type="date" class="form-control form-control-sm" name="' . $name('to') . '" value="' . ($season ? $season->getDateTo()->format('Y-m-d') : '') . '"></td>';
    $html .= '<td><input type="number" min="1" max="365" class="form-control form-control-sm" name="' . $name('minNights') . '" value="' . h((string) ($season?->getMinNights() ?? '')) . '" style="width: 5rem"></td>';
    for ($g = 1; $g <= $columns; $g++) {
        $html .= '<td data-guests="' . $g . '"><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="' . $name('prices') . '[' . $g . ']" value="' . h(Money::toInput($prices[$g] ?? null)) . '" style="width: 6rem"></td>';
    }
    $html .= '<td class="text-nowrap">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-fewo-copy-year title="' . h(t('Copy to next year')) . '">+1 ' . h(t('year')) . '</button> '
        . '<button type="button" class="btn btn-sm btn-outline-danger" data-fewo-remove title="' . h(t('Remove')) . '">×</button>'
        . '</td></tr>';

    return $html;
};
?>
<form method="post" action="<?= h($view->action('save', $pricelist->getId())) ?>" id="fewo-pricelist-form">
    <?php $token->output('fewo_pricelist_save') ?>

    <div class="ccm-dashboard-header-buttons">
        <a href="<?= h($view->action('view')) ?>" class="btn btn-secondary"><?= t('Back to list') ?></a>
    </div>

    <fieldset class="mb-4">
        <legend><?= t('General') ?></legend>
        <div class="row g-3">
            <div class="col-md-6">
                <?= $form->label('name', t('Name')) ?>
                <?= $form->text('name', $pricelist->getName(), ['required' => 'required', 'maxlength' => 255]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->label('maxGuests', t('Max. guests')) ?>
                <?= $form->number('maxGuests', $maxGuests, ['min' => 1, 'max' => 30, 'required' => 'required']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->label('minNights', t('Minimum stay (nights)')) ?>
                <?= $form->number('minNights', $pricelist->getMinNights(), ['min' => 1, 'max' => 365]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->label('cleaningFee', t('Final cleaning (€)')) ?>
                <?= $form->text('cleaningFee', Money::toInput($pricelist->getCleaningFee()), ['inputmode' => 'decimal']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->label('securityDeposit', t('Security deposit (€)')) ?>
                <?= $form->text('securityDeposit', Money::toInput($pricelist->getSecurityDeposit()), ['inputmode' => 'decimal']) ?>
                <div class="form-text"><?= t('Shown separately, not part of the total price.') ?></div>
            </div>
            <div class="col-md-4">
                <?= $form->label('downPaymentPercent', t('Down payment (%)')) ?>
                <?= $form->number('downPaymentPercent', $pricelist->getDownPaymentPercent(), ['min' => 0, 'max' => 100]) ?>
            </div>
            <div class="col-12">
                <?= $form->label('includedServices', t('Included services')) ?>
                <?= $form->text('includedServices', $pricelist->getIncludedServices(), ['placeholder' => t('e.g. incl. bed linen, towels, Wi-Fi and utilities'), 'maxlength' => 255]) ?>
            </div>
            <div class="col-12">
                <?= $form->label('note', t('Note below the price overview')) ?>
                <?= $form->textarea('note', $pricelist->getNote(), ['rows' => 2, 'placeholder' => t('e.g. Prices for other periods on request.')]) ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend><?= t('Seasons and prices per night') ?></legend>
        <p class="text-muted">
            <?= t('Enter the price per night for each number of guests. If a field is empty, the price of the next higher number of guests applies (e.g. only fill in 2 and 4). Overlapping seasons are allowed: the season starting later wins (e.g. Easter within the low season).') ?>
        </p>
        <div class="table-responsive">
            <table class="table table-sm align-middle" data-fewo-seasons data-columns="<?= $columns ?>">
                <thead>
                    <tr>
                        <th><?= t('Season') ?></th>
                        <th><?= t('From') ?></th>
                        <th><?= t('To (incl.)') ?></th>
                        <th title="<?= h(t('Different minimum stay in this season')) ?>"><?= t('Min. nights') ?></th>
                        <?php for ($g = 1; $g <= $columns; $g++) { ?>
                            <th data-guests="<?= $g ?>" class="text-end"><?= t2('%s guest', '%s guests', $g) ?></th>
                        <?php } ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $seasons = $pricelist->getSeasons();
                    foreach ($seasons as $index => $season) {
                        echo $seasonRow('s' . $index, $season, $columns, $today);
                    }
                    if ($seasons === []) {
                        echo $seasonRow('s0', null, $columns, $today);
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-fewo-add-season><?= t('Add season') ?></button>
        <template data-fewo-season-template><?= $seasonRow('__KEY__', null, 30, $today) ?></template>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($view->action('view')) ?>" class="btn btn-secondary float-start"><?= t('Cancel') ?></a>
            <button type="submit" class="btn btn-primary float-end"><?= t('Save') ?></button>
        </div>
    </div>
</form>

<script>
(() => {
    const form = document.getElementById('fewo-pricelist-form');
    const table = form.querySelector('[data-fewo-seasons]');
    const tbody = table.querySelector('tbody');
    const template = form.querySelector('template[data-fewo-season-template]');
    const maxGuests = form.querySelector('input[name="maxGuests"]');
    const guestLabel = <?= json_encode([t('%s guest'), t('%s guests')]) ?>;
    let counter = Date.now();

    const newRow = () => {
        const html = template.innerHTML.replace(/__KEY__/g, 'n' + (counter++));
        tbody.insertAdjacentHTML('beforeend', html);
        const row = tbody.lastElementChild;
        syncColumns();
        return row;
    };

    // Preisspalten an die maximale Gästeanzahl anpassen
    const syncColumns = () => {
        const count = Math.max(1, Math.min(30, parseInt(maxGuests.value, 10) || 1));
        const headRow = table.querySelector('thead tr');
        let existing = headRow.querySelectorAll('th[data-guests]').length;
        while (existing < count) {
            existing++;
            const th = document.createElement('th');
            th.dataset.guests = existing;
            th.className = 'text-end';
            th.textContent = (existing === 1 ? guestLabel[0] : guestLabel[1]).replace('%s', existing);
            headRow.lastElementChild.before(th);
            tbody.querySelectorAll('tr').forEach((row) => {
                if (!row.querySelector('td[data-guests="' + existing + '"]')) {
                    const key = row.querySelector('input[name$="[name]"]').name.match(/^seasons\[([^\]]+)\]/)[1];
                    const td = document.createElement('td');
                    td.dataset.guests = existing;
                    td.innerHTML = '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end" style="width: 6rem">';
                    td.querySelector('input').name = 'seasons[' + key + '][prices][' + existing + ']';
                    row.lastElementChild.before(td);
                }
            });
        }
        table.querySelectorAll('[data-guests]').forEach((cell) => {
            const visible = parseInt(cell.dataset.guests, 10) <= count;
            cell.hidden = !visible;
            cell.querySelectorAll('input').forEach((input) => { input.disabled = !visible; });
        });
    };

    const addYear = (value) => {
        if (!value) {
            return '';
        }
        const [y, m, d] = value.split('-').map(Number);
        const date = new Date(Date.UTC(y + 1, m - 1, d));
        // 29. Februar => 28. Februar
        if (date.getUTCMonth() !== m - 1) {
            date.setUTCDate(0);
        }
        return date.toISOString().slice(0, 10);
    };

    form.querySelector('[data-fewo-add-season]').addEventListener('click', () => {
        newRow().querySelector('input').focus();
    });

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }
        const row = button.closest('tr');
        if (button.hasAttribute('data-fewo-remove')) {
            row.remove();
        } else if (button.hasAttribute('data-fewo-copy-year')) {
            const copy = newRow();
            row.querySelectorAll('input').forEach((input) => {
                const field = input.name.replace(/^seasons\[[^\]]+\]/, '');
                const target = copy.querySelector('input[name$="' + CSS.escape(field) + '"]');
                if (!target) {
                    return;
                }
                target.value = (field === '[from]' || field === '[to]') ? addYear(input.value) : input.value;
            });
            copy.classList.add('table-success');
            copy.querySelector('input').focus();
        }
    });

    maxGuests.addEventListener('input', syncColumns);
    syncColumns();
})();
</script>
