<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoTk2\Entity\Apartment $apartment
 * @var array{name: string, occupancyUrl: string, pricelistUrl: string, refreshMinutes: int} $values
 * @var array<int, string> $refreshOptions
 */

$token = app('token');
$form = app('helper/form');
?>
<?php // Außerhalb des Formulars: Concrete verschiebt sonst das ganze Formular in die Kopfzeile. ?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($view->action('view')) ?>" class="btn btn-secondary"><?= t('Back to list') ?></a>
</div>

<form method="post" action="<?= h($view->action('save', $apartment->getId())) ?>" id="fewo-apartment-form">
    <?php $token->output('fewo_apartment_save') ?>

    <fieldset class="mb-4">
        <legend><?= $apartment->getId() ? t('Edit apartment') : t('Add apartment') ?></legend>

        <div class="mb-3">
            <?= $form->label('name', t('Name')) ?>
            <?= $form->text('name', $values['name'], ['required' => 'required', 'maxlength' => 255, 'placeholder' => t('e.g. Holiday apartment Sonnenseele')]) ?>
            <div class="form-text"><?= t('Shown in the block dialogs when selecting the apartment.') ?></div>
        </div>

        <div class="mb-3">
            <?= $form->label('occupancyUrl', t('Occupancy URL from Fewo-Office')) ?>
            <?= $form->url('occupancyUrl', $values['occupancyUrl'], ['placeholder' => 'https://fewo-office.com/api/wohnungen/…/belegung.json']) ?>
            <div class="form-text"><?= t('For the occupancy calendar. All three formats of the interface are accepted (.json, .xml, .ics); JSON is recommended.') ?></div>
        </div>

        <div class="mb-3">
            <?= $form->label('pricelistUrl', t('Price list URL from Fewo-Office')) ?>
            <?= $form->url('pricelistUrl', $values['pricelistUrl'], ['placeholder' => 'https://fewo-office.com/api/wohnungen/…/preise.json']) ?>
            <div class="form-text"><?= t('For the price overview, the price calculator and the inquiry form. Both formats of the interface are accepted (.json, .xml); JSON is recommended. Only published seasons that have not ended yet are shown.') ?></div>
        </div>

        <div class="mb-3" style="max-width: 20rem">
            <?= $form->label('refreshMinutes', t('Update interval')) ?>
            <?= $form->select('refreshMinutes', $refreshOptions, (int) $values['refreshMinutes']) ?>
            <div class="form-text"><?= t('The data is loaded again on the next page view after this time. No cron job is required.') ?></div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($view->action('view')) ?>" class="btn btn-secondary float-start"><?= t('Cancel') ?></a>
            <button type="submit" class="btn btn-primary float-end"><?= t('Save') ?></button>
        </div>
    </div>
</form>
