<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $pricelistID
 * @var string $title
 * @var bool $showConditions
 * @var array $pricelistOptions
 * @var string $dashboardUrl
 */
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
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
</div>

<div class="form-check">
    <?= $form->checkbox('showConditions', 1, (bool) $showConditions) ?>
    <?= $form->label('showConditions', t('Show conditions (minimum stay, cleaning fee, deposit, note)'), ['class' => 'form-check-label']) ?>
</div>
