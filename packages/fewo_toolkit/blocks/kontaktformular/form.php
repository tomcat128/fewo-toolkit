<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $formName
 * @var string $recipientEmail
 * @var int $pricelistID
 * @var int $maxGuests
 * @var string $accommodations
 * @var string $referralOptions
 * @var bool $askForAddress
 * @var int $privacyPageID
 * @var bool $useCaptcha
 * @var bool $storeInquiries
 * @var bool $sendConfirmation
 * @var string $successMessage
 * @var array $pricelistOptions
 */

$pageSelector = app('helper/form/page_selector');
?>
<fieldset>
    <legend><?= t('Delivery') ?></legend>
    <div class="mb-3">
        <?= $form->label('recipientEmail', t('Send inquiries to')) ?>
        <?= $form->email('recipientEmail', $recipientEmail, ['required' => 'required']) ?>
        <div class="form-text"><?= t('Emails are sent with the settings under Dashboard › System & Settings › Email. Please configure SMTP there.') ?></div>
    </div>
    <div class="mb-3">
        <?= $form->label('formName', t('Name of the form (optional)')) ?>
        <?= $form->text('formName', $formName, ['placeholder' => t('e.g. name of the apartment')]) ?>
        <div class="form-text"><?= t('Appears in the email subject and in the dashboard.') ?></div>
    </div>
    <div class="form-check">
        <?= $form->checkbox('storeInquiries', 1, (bool) $storeInquiries) ?>
        <?= $form->label('storeInquiries', t('Save inquiries in the dashboard'), ['class' => 'form-check-label']) ?>
    </div>
    <div class="form-check mb-3">
        <?= $form->checkbox('sendConfirmation', 1, (bool) $sendConfirmation) ?>
        <?= $form->label('sendConfirmation', t('Send a confirmation email to the guest'), ['class' => 'form-check-label']) ?>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Fields') ?></legend>
    <div class="row">
        <div class="col-sm-8 mb-3">
            <?= $form->label('pricelistID', t('Price list (optional)')) ?>
            <?= $form->select('pricelistID', $pricelistOptions, (int) $pricelistID ?: '') ?>
            <div class="form-text"><?= t('Determines the maximum number of guests. The non-binding price is included in the notification.') ?></div>
        </div>
        <div class="col-sm-4 mb-3">
            <?= $form->label('maxGuests', t('Max. guests')) ?>
            <?= $form->number('maxGuests', (int) $maxGuests, ['min' => 1, 'max' => 50]) ?>
            <div class="form-text"><?= t('Only without price list.') ?></div>
        </div>
    </div>
    <div class="mb-3">
        <?= $form->label('accommodations', t('Accommodations to choose from (one per line, optional)')) ?>
        <?= $form->textarea('accommodations', $accommodations, ['rows' => 3, 'placeholder' => "Seeherz, 42 m², max. 4 Personen\nSeeherzchen, 24 m², max. 2 Personen"]) ?>
    </div>
    <div class="mb-3">
        <?= $form->label('referralOptions', t('Answers for "How did you find us?" (one per line, empty = hide field)')) ?>
        <?= $form->textarea('referralOptions', $referralOptions, ['rows' => 4]) ?>
    </div>
    <div class="form-check mb-3">
        <?= $form->checkbox('askForAddress', 1, (bool) $askForAddress) ?>
        <?= $form->label('askForAddress', t('Ask for the postal address'), ['class' => 'form-check-label']) ?>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Privacy & spam protection') ?></legend>
    <div class="mb-3">
        <?= $form->label('privacyPageID', t('Privacy policy page')) ?>
        <?= $pageSelector->selectPage('privacyPageID', (int) $privacyPageID ?: null) ?>
        <div class="form-text"><?= t('Linked in the mandatory consent checkbox.') ?></div>
    </div>
    <div class="form-check mb-3">
        <?= $form->checkbox('useCaptcha', 1, (bool) $useCaptcha) ?>
        <?= $form->label('useCaptcha', t('Use captcha (configured under Dashboard › System & Settings › Permissions › Captcha)'), ['class' => 'form-check-label']) ?>
        <div class="form-text"><?= t('A hidden honeypot field and a minimum fill-in time are always active.') ?></div>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('After sending') ?></legend>
    <div class="mb-3">
        <?= $form->label('successMessage', t('Thank-you message')) ?>
        <?= $form->textarea('successMessage', $successMessage, ['rows' => 3]) ?>
    </div>
</fieldset>
