<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var int $monthsVisible
 * @var int $monthsTotal
 * @var string $onRequestKeywords
 * @var bool $showLegend
 * @var array $apartmentOptions
 * @var string $apartmentsUrl
 */

?>
<fieldset>
    <legend><?= t('Occupancy') ?></legend>

    <?php Concrete\Core\View\View::element('apartment_select', [
        'form' => $form,
        'apartmentID' => $apartmentID,
        'apartmentOptions' => $apartmentOptions,
        'apartmentsUrl' => $apartmentsUrl,
        'required' => true,
        'help' => t('The occupancy of this apartment is loaded from Fewo-Office.'),
    ], 'fewo_tk2') ?>

    <div class="mb-3">
        <?= $form->label('onRequestKeywords', t('Keywords for "on request" (optional)')) ?>
        <?= $form->text('onRequestKeywords', $onRequestKeywords) ?>
        <div class="form-text"><?= t('Entries whose info text contains one of these words (comma-separated) are shown as "on request" instead of "booked". Leave empty to show all entries as booked.') ?></div>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Display') ?></legend>

    <div class="mb-3">
        <?= $form->label('title', t('Heading (optional)')) ?>
        <?= $form->text('title', $title) ?>
    </div>

    <div class="row">
        <div class="col-sm-6 mb-3">
            <?= $form->label('monthsVisible', t('Months shown initially')) ?>
            <?= $form->number('monthsVisible', (int) $monthsVisible, ['min' => 1, 'max' => 36]) ?>
        </div>
        <div class="col-sm-6 mb-3">
            <?= $form->label('monthsTotal', t('Months in total')) ?>
            <?= $form->number('monthsTotal', (int) $monthsTotal, ['min' => 1, 'max' => 36]) ?>
        </div>
    </div>

    <div class="form-check mb-3">
        <?= $form->checkbox('showLegend', 1, (bool) $showLegend) ?>
        <?= $form->label('showLegend', t('Show legend'), ['class' => 'form-check-label']) ?>
    </div>
</fieldset>
