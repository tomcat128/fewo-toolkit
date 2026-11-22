<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var bool $showPeriod
 * @var array<int, array{code: string, label: string, color: string}> $levels
 * @var array $apartmentOptions
 * @var string $apartmentsUrl
 */
?>
<?php Concrete\Core\View\View::element('apartment_select', [
    'form' => $form,
    'apartmentID' => $apartmentID,
    'apartmentOptions' => $apartmentOptions,
    'apartmentsUrl' => $apartmentsUrl,
    'required' => true,
    'help' => t('The news of this apartment are loaded from Fewo-Office (news URL of the apartment).'),
], 'fewo_tk2') ?>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
    <div class="form-text"><?= t('Only shown when there is current news.') ?></div>
</div>

<div class="form-check form-switch">
    <?= $form->checkbox('showPeriod', 1, (bool) $showPeriod, ['role' => 'switch']) ?>
    <?= $form->label('showPeriod', t('Show the period of validity'), ['class' => 'form-check-label']) ?>
    <div class="form-text"><?= t('Below the title, e.g. „Valid from 01.12. to 24.12.“. When switched off, only the title and text are shown.') ?></div>
</div>

<fieldset class="mt-4">
    <legend class="fs-6 fw-bold"><?= t('Importance levels') ?></legend>
    <?php Concrete\Core\View\View::element('importance_rows', ['levels' => $levels], 'fewo_tk2') ?>
</fieldset>

<p class="form-text mt-3 mb-0">
    <?= t('News are written in Fewo-Office. Each one appears here from the first to the last day of its period; when there is no current news, the block shows nothing.') ?>
</p>
