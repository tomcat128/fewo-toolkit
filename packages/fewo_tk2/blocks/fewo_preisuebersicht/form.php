<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var string $layout
 * @var bool $showConditions
 * @var array $apartmentOptions
 * @var string $apartmentsUrl
 * @var array $layoutOptions
 */
?>
<?php Concrete\Core\View\View::element('apartment_select', [
    'form' => $form,
    'apartmentID' => $apartmentID,
    'apartmentOptions' => $apartmentOptions,
    'apartmentsUrl' => $apartmentsUrl,
    'required' => true,
    'help' => t('The prices of this apartment are loaded from Fewo-Office.'),
], 'fewo_tk2') ?>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
</div>

<div class="mb-3">
    <?= $form->label('layout', t('Arrangement of the seasons')) ?>
    <?= $form->select('layout', $layoutOptions, $layout) ?>
</div>

<div class="form-check">
    <?= $form->checkbox('showConditions', 1, (bool) $showConditions) ?>
    <?= $form->label('showConditions', t('Show conditions (minimum stay, final cleaning, down payment)'), ['class' => 'form-check-label']) ?>
</div>
