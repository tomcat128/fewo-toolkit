<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var int $inquiryPageID
 * @var array $apartmentOptions
 * @var string $apartmentsUrl
 */

$pageSelector = app('helper/form/page_selector');
?>
<?php Concrete\Core\View\View::element('apartment_select', [
    'form' => $form,
    'apartmentID' => $apartmentID,
    'apartmentOptions' => $apartmentOptions,
    'apartmentsUrl' => $apartmentsUrl,
    'required' => true,
    'help' => t('The calculation follows Fewo-Office: each night costs the price of the day on which it ends.'),
], 'fewo_tk2') ?>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
</div>

<div class="mb-3">
    <?= $form->label('inquiryPageID', t('Page with the booking inquiry form (optional)')) ?>
    <?= $pageSelector->selectPage('inquiryPageID', (int) $inquiryPageID ?: null) ?>
    <div class="form-text"><?= t('After the calculation a button leads to this page. Travel dates and number of guests are filled in automatically.') ?></div>
</div>
