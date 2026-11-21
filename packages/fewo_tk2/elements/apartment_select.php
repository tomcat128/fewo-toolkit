<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Block-Formulare: Auswahl der Wohnung, deren Daten aus Fewo-Office angezeigt werden.
 *
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var array<int|string, string> $apartmentOptions
 * @var string $apartmentsUrl
 * @var bool $required
 * @var string $help
 */
?>
<div class="mb-3">
    <?= $form->label('apartmentID', $required ? t('Apartment') : t('Apartment (optional)')) ?>
    <?php if (count($apartmentOptions) > 1) { ?>
        <?= $form->select('apartmentID', $apartmentOptions, (int) $apartmentID ?: '', $required ? ['required' => 'required'] : []) ?>
    <?php } else { ?>
        <div class="alert alert-info mb-1"><?= t('There are no apartments yet.') ?></div>
    <?php } ?>
    <div class="form-text">
        <?= h($help) ?>
        <a href="<?= h($apartmentsUrl) ?>" target="_blank"><?= t('Manage apartments') ?></a>
    </div>
</div>
