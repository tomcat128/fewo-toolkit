<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $pricelistID
 * @var string $calculationMode
 * @var string $title
 * @var int $inquiryPageID
 * @var array $pricelistOptions
 * @var array $modeOptions
 * @var string $dashboardUrl
 */

$pageSelector = app('helper/form/page_selector');
?>
<div class="mb-3">
    <?= $form->label('pricelistID', t('Price list')) ?>
    <?php if (count($pricelistOptions) > 1) { ?>
        <?= $form->select('pricelistID', $pricelistOptions, (int) $pricelistID ?: '', ['required' => 'required']) ?>
    <?php } else { ?>
        <div class="alert alert-info"><?= t('There are no price lists yet.') ?></div>
    <?php } ?>
    <div class="form-text"><a href="<?= h($dashboardUrl) ?>" target="_blank"><?= t('Manage price lists') ?></a></div>
</div>

<div class="mb-3">
    <?= $form->label('calculationMode', t('Which day determines the season price of a night?')) ?>
    <?= $form->select('calculationMode', $modeOptions, $calculationMode) ?>
</div>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
</div>

<div class="mb-3">
    <?= $form->label('inquiryPageID', t('Page with the booking inquiry form (optional)')) ?>
    <?= $pageSelector->selectPage('inquiryPageID', (int) $inquiryPageID ?: null) ?>
    <div class="form-text"><?= t('After the calculation a button leads to this page. Travel dates and number of guests are filled in automatically.') ?></div>
</div>
