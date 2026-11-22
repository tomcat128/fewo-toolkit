<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var bool $showPeriod
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

<div class="form-check">
    <?= $form->checkbox('showPeriod', 1, (bool) $showPeriod) ?>
    <?= $form->label('showPeriod', t('Show the period of validity'), ['class' => 'form-check-label']) ?>
</div>

<p class="form-text mt-3 mb-0">
    <?= t('News are written in Fewo-Office. Each one appears here from the first to the last day of its period; when there is no current news, the block shows nothing.') ?>
</p>
