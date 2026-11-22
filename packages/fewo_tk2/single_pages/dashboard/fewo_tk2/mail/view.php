<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Mail\MailSettings;

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoTk2\Mail\MailSettings $settings
 * @var bool $hasPassword
 * @var bool $passwordUnreadable
 * @var bool $sendingDisabled
 * @var string $concreteMethod
 * @var string $concreteServer
 * @var string $testRecipient
 * @var string $defaultHelo
 */

$token = app('token');
$form = app('helper/form');
$concreteMailUrl = (string) Concrete\Core\Support\Facade\Url::to('/dashboard/system/mail/method');
$encryptions = [
    MailSettings::STARTTLS => t('STARTTLS (port 587)'),
    MailSettings::SSL => t('SSL/TLS (port 465)'),
    MailSettings::NONE => t('None'),
];
?>
<p class="lead"><?= t('The inquiry form sends the inquiry to you and, if switched on, a confirmation to the guest. Choose how these emails are sent.') ?></p>

<?php if ($sendingDisabled) { ?>
    <div class="alert alert-warning"><?= t('Sending emails is switched off in Concrete – no email leaves the website, they are only logged. Switch it on under <a href="%s">System & Settings › Email</a>.', h($concreteMailUrl)) ?></div>
<?php } ?>
<?php if ($passwordUnreadable) { ?>
    <div class="alert alert-danger"><?= t('The stored SMTP password cannot be read on this website (e.g. settings copied from another installation). Please enter it again and save.') ?></div>
<?php } ?>

<form method="post" action="<?= h($view->action('save')) ?>" id="fewo-mail-form">
    <?php $token->output('fewo_mail_save') ?>

    <fieldset class="mb-4">
        <legend><?= t('Sending') ?></legend>
        <div class="form-check">
            <?= $form->radio('mode', MailSettings::MODE_CONCRETE, $settings->mode, ['id' => 'mailModeConcrete']) ?>
            <label class="form-check-label" for="mailModeConcrete"><?= t('Use the email settings of Concrete') ?></label>
            <div class="form-text">
                <?= $concreteMethod === 'smtp'
                    ? t('Currently via the SMTP server %s.', '<code>' . h($concreteServer) . '</code>')
                    : t('Currently via the mail function of the web server (PHP mail), which many providers treat as spam.') ?>
                <a href="<?= h($concreteMailUrl) ?>"><?= t('Change the settings of Concrete') ?></a>
            </div>
        </div>
        <div class="form-check mt-2">
            <?= $form->radio('mode', MailSettings::MODE_SMTP, $settings->mode, ['id' => 'mailModeSmtp']) ?>
            <label class="form-check-label" for="mailModeSmtp"><?= t('Use an SMTP mailbox of its own') ?></label>
            <div class="form-text"><?= t('Only for the emails of the Fewo Toolkit – the rest of the website keeps the settings of Concrete.') ?></div>
        </div>
    </fieldset>

    <fieldset class="mb-4" id="fewo-mail-smtp"<?= $settings->usesOwnSmtp() ? '' : ' hidden' ?>>
        <legend><?= t('SMTP mailbox') ?></legend>
        <div class="row">
            <div class="col-md-6 mb-3">
                <?= $form->label('host', t('SMTP server')) ?>
                <?= $form->text('host', $settings->host, ['placeholder' => 'smtp.example.com', 'maxlength' => 200, 'autocomplete' => 'off']) ?>
            </div>
            <div class="col-md-3 mb-3">
                <?= $form->label('encryption', t('Encryption')) ?>
                <?= $form->select('encryption', $encryptions, $settings->encryption) ?>
            </div>
            <div class="col-md-3 mb-3">
                <?= $form->label('port', t('Port')) ?>
                <?= $form->number('port', $settings->port, ['min' => 1, 'max' => 65535]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $form->label('username', t('User name')) ?>
                <?= $form->text('username', $settings->username, ['maxlength' => 200, 'autocomplete' => 'off']) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $form->label('password', t('Password')) ?>
                <?= $form->password('password', '', ['maxlength' => 200, 'autocomplete' => 'new-password', 'placeholder' => $hasPassword && !$passwordUnreadable ? t('Stored – overwrite to change') : '']) ?>
                <?php if ($hasPassword) { ?>
                    <div class="form-check mt-1">
                        <?= $form->checkbox('removePassword', 1, false) ?>
                        <?= $form->label('removePassword', t('Remove the stored password'), ['class' => 'form-check-label']) ?>
                    </div>
                <?php } ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $form->label('fromAddress', t('Sender address')) ?>
                <?= $form->email('fromAddress', $settings->fromAddress, ['maxlength' => 200]) ?>
                <div class="form-text"><?= t('Must belong to the mailbox, otherwise the emails end up in the spam folder or are rejected.') ?></div>
            </div>
            <div class="col-md-6 mb-3">
                <?= $form->label('fromName', t('Sender name')) ?>
                <?= $form->text('fromName', $settings->fromName, ['maxlength' => 150, 'placeholder' => (string) app('site')->getSite()->getSiteName()]) ?>
                <div class="form-text"><?= t('Empty: the name of the website.') ?></div>
            </div>
        </div>
        <div class="mb-3" style="max-width: 30rem">
            <?= $form->label('heloDomain', t('Name of this website for the SMTP server (EHLO, optional)')) ?>
            <?= $form->text('heloDomain', $settings->heloDomain, ['maxlength' => 253, 'placeholder' => $defaultHelo, 'autocomplete' => 'off']) ?>
            <div class="form-text"><?= t('Empty: %s (the domain of the website). Only change it if the provider asks for it – never enter the domain of the sender address here.', '<code>' . h($defaultHelo) . '</code>') ?></div>
        </div>
        <p class="form-text mb-0"><?= t('The password is stored encrypted. Replies of guests go to their own address (Reply-To), not to the sender address.') ?></p>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button type="submit" class="btn btn-primary float-end"><?= t('Save') ?></button>
        </div>
    </div>
</form>

<form method="post" action="<?= h($view->action('test')) ?>" id="fewo-mail-test" class="mb-5">
    <?php $token->output('fewo_mail_test') ?>
    <fieldset>
        <legend><?= t('Test email') ?></legend>
        <p class="text-muted"><?= t('Sends a test email with the saved settings. Save changes first.') ?></p>
        <div class="input-group" style="max-width: 36rem">
            <?= $form->email('testRecipient', $testRecipient, ['aria-label' => t('Recipient of the test email'), 'placeholder' => t('Recipient')]) ?>
            <button type="submit" class="btn btn-secondary"><?= t('Send test email') ?></button>
        </div>
    </fieldset>
</form>

<script>
(() => {
    const form = document.getElementById('fewo-mail-form');
    const smtp = document.getElementById('fewo-mail-smtp');
    const port = form.querySelector('[name="port"]');
    const ports = <?= json_encode(MailSettings::PORTS) ?>;
    form.querySelectorAll('[name="mode"]').forEach((radio) => radio.addEventListener('change', () => {
        smtp.hidden = form.querySelector('[name="mode"]:checked').value !== '<?= MailSettings::MODE_SMTP ?>';
    }));
    // Verschlüsselung gewechselt: den üblichen Port vorschlagen, solange noch ein üblicher eingetragen ist
    form.querySelector('[name="encryption"]').addEventListener('change', (event) => {
        if (Object.values(ports).includes(parseInt(port.value, 10)) || port.value === '') {
            port.value = ports[event.target.value];
        }
    });
})();
</script>
