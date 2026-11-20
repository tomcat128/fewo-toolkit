<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoToolkit\Support\Money;

/**
 * @var Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard\FewoToolkit\Pricelists $controller
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var FewoToolkit\Entity\Pricelist[] $pricelists
 * @var DateTimeImmutable $today
 */

$token = app('token');
$form = app('helper/form');
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($view->action('add')) ?>" class="btn btn-primary"><?= t('Add price list') ?></a>
</div>

<?php if ($pricelists === []) { ?>
    <div class="alert alert-info">
        <?= t('There are no price lists yet. Create a new one or import a price list from the old Fewo Toolkit below.') ?>
    </div>
<?php } else { ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th><?= t('Name') ?></th>
                    <th><?= t('Max. guests') ?></th>
                    <th><?= t('Current seasons') ?></th>
                    <th><?= t('Prices until') ?></th>
                    <th><?= t('Final cleaning') ?></th>
                    <th class="text-end"><?= t('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pricelists as $pricelist) {
                    $current = $pricelist->getCurrentSeasons($today);
                    $last = $current === [] ? null : max(array_map(static fn ($s) => $s->getDateTo(), $current));
                    $soon = $last !== null && $last < $today->modify('+3 months');
                    ?>
                    <tr>
                        <td><a href="<?= h($view->action('edit', $pricelist->getId())) ?>"><strong><?= h($pricelist->getName()) ?></strong></a></td>
                        <td><?= $pricelist->getMaxGuests() ?></td>
                        <td><?= count($current) ?></td>
                        <td>
                            <?php if ($last === null) { ?>
                                <span class="badge bg-danger"><?= t('No current prices') ?></span>
                            <?php } else { ?>
                                <?= $last->format('d.m.Y') ?>
                                <?php if ($soon) { ?>
                                    <span class="badge bg-warning text-dark"><?= t('Expires soon') ?></span>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td><?= h(Money::format($pricelist->getCleaningFee(), $pricelist->getCurrency())) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= h($view->action('edit', $pricelist->getId())) ?>" class="btn btn-sm btn-secondary"><?= t('Edit') ?></a>
                            <form method="post" action="<?= h($view->action('duplicate', $pricelist->getId())) ?>" class="d-inline">
                                <?php $token->output('fewo_pricelist_duplicate') ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= t('Copy') ?></button>
                            </form>
                            <form method="post" action="<?= h($view->action('delete', $pricelist->getId())) ?>" class="d-inline"
                                  onsubmit="return confirm(<?= h(json_encode(t('Delete the price list "%s"? Blocks using it will no longer show prices.', $pricelist->getName()))) ?>)">
                                <?php $token->output('fewo_pricelist_delete') ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= t('Delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<hr class="my-5">

<h3><?= t('Import from the old Fewo Toolkit') ?></h3>
<p class="text-muted">
    <?= t('In the old toolkit, enable the export of a price list (price list settings › export) and open %s. Save the XML file and upload it here, or paste its content.', '<code>https://ihre-domain.de/index.php/fewobacher/export/pricelist/ID</code>') ?>
</p>
<form method="post" action="<?= h($view->action('import')) ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 48rem">
    <?php $token->output('fewo_pricelist_import') ?>
    <div class="col-12">
        <?= $form->label('importFile', t('XML file')) ?>
        <input type="file" name="importFile" id="importFile" class="form-control" accept=".xml,text/xml,application/xml">
    </div>
    <div class="col-12">
        <?= $form->label('importXml', t('… or paste the XML')) ?>
        <?= $form->textarea('importXml', '', ['rows' => 4, 'class' => 'font-monospace']) ?>
    </div>
    <div class="col-sm-8">
        <?= $form->label('importTarget', t('Import into')) ?>
        <?php
        $targets = ['' => t('New price list')];
        foreach ($pricelists as $pricelist) {
            $targets[$pricelist->getId()] = t('Replace seasons of "%s"', $pricelist->getName());
        }
        echo $form->select('importTarget', $targets);
        ?>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-secondary"><?= t('Import') ?></button>
    </div>
</form>
