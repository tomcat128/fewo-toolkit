<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Support\Money;

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoTk2\Entity\Inquiry[] $inquiries
 * @var string $status
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $newCount
 * @var int $retentionDays
 */

$token = app('token');
$form = app('helper/form');
$dateService = app('date');
$filterUrl = static fn (string $value): string => (string) $view->action('view') . ($value !== '' ? '?status=' . $value : '');
?>
<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="<?= h($filterUrl('')) ?>"><?= t('All') ?></a></li>
    <li class="nav-item">
        <a class="nav-link<?= $status === 'new' ? ' active' : '' ?>" href="<?= h($filterUrl('new')) ?>">
            <?= t('New') ?> <?php if ($newCount > 0) { ?><span class="badge bg-primary"><?= $newCount ?></span><?php } ?>
        </a>
    </li>
    <li class="nav-item"><a class="nav-link<?= $status === 'done' ? ' active' : '' ?>" href="<?= h($filterUrl('done')) ?>"><?= t('Done') ?></a></li>
</ul>

<?php if ($inquiries === []) { ?>
    <div class="alert alert-info"><?= t('No inquiries found.') ?></div>
<?php } else { ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th><?= t('Received') ?></th>
                    <th><?= t('Name') ?></th>
                    <th><?= t('Travel dates') ?></th>
                    <th><?= t('Guests') ?></th>
                    <th><?= t('Accommodation / form') ?></th>
                    <th class="text-end"><?= t('Price') ?></th>
                    <th><?= t('Status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inquiries as $inquiry) {
                    $url = (string) $view->action('detail', $inquiry->getId()); ?>
                    <tr class="<?= $inquiry->isNew() ? 'fw-bold' : '' ?>" style="cursor: pointer" onclick="window.location.href=<?= h(json_encode($url)) ?>">
                        <td class="text-nowrap"><?= h($dateService->formatDateTime($inquiry->getCreatedAt())) ?></td>
                        <td><a href="<?= h($url) ?>"><?= h($inquiry->getFullName()) ?></a></td>
                        <td class="text-nowrap"><?= $inquiry->getArrival()->format('d.m.Y') ?> – <?= $inquiry->getDeparture()->format('d.m.Y') ?></td>
                        <td><?= $inquiry->getGuests() ?></td>
                        <td><?= h($inquiry->getAccommodation() ?: $inquiry->getFormName()) ?></td>
                        <td class="text-end text-nowrap"><?= $inquiry->getQuotedTotal() !== null ? h(Money::format($inquiry->getQuotedTotal(), $inquiry->getCurrency())) : '–' ?></td>
                        <td>
                            <?php if ($inquiry->isNew()) { ?>
                                <span class="badge bg-primary"><?= t('New') ?></span>
                            <?php } else { ?>
                                <span class="badge bg-secondary"><?= t('Done') ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1) { ?>
        <nav aria-label="<?= h(t('Pages')) ?>">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $pages; $p++) { ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= h((string) $view->action('view', $p) . ($status !== '' ? '?status=' . $status : '')) ?>"><?= $p ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    <?php } ?>
<?php } ?>

<hr class="my-5">

<h3><?= t('Data protection') ?></h3>
<form method="post" action="<?= h($view->action('save_settings')) ?>" class="row g-3 align-items-end" style="max-width: 40rem">
    <?php $token->output('fewo_inquiry_settings') ?>
    <div class="col-sm-8">
        <?= $form->label('retentionDays', t('Delete inquiries automatically after (days)')) ?>
        <?= $form->number('retentionDays', $retentionDays, ['min' => 0, 'max' => 3650]) ?>
        <div class="form-text"><?= t('0 = never delete. Inquiries are kept in the dashboard only; the emails in your mailbox are not affected.') ?></div>
    </div>
    <div class="col-sm-4">
        <button type="submit" class="btn btn-secondary"><?= t('Save') ?></button>
    </div>
</form>
