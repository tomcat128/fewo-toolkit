<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var array $values
 * @var array $errors
 * @var bool $sent
 * @var int $maxGuests
 * @var string[] $accommodationList
 * @var string[] $referralList
 * @var string[] $salutations
 * @var string|null $privacyUrl
 * @var Concrete\Core\Captcha\CaptchaInterface|null $captcha
 * @var string $tokenAction
 * @var string $actionUrl
 * @var bool $askForAddress
 * @var string $successMessage
 * @var DateTimeImmutable $today
 */

$id = 'fewo-inquiry-' . (int) $bID;
$token = app('token');
$v = static fn (string $key): string => h((string) ($values[$key] ?? ''));
$minDate = $today->format('Y-m-d');
$maxDate = $today->modify('+3 years')->format('Y-m-d');

// Hilfsfunktionen für einheitliche Felder mit Fehlermeldungen
$fieldAttrs = static function (string $name, bool $required = false) use ($id, $errors): string {
    $attrs = ' id="' . $id . '-' . $name . '" name="' . $name . '"';
    if ($required) {
        $attrs .= ' required aria-required="true"';
    }
    if (isset($errors[$name])) {
        $attrs .= ' aria-invalid="true" aria-describedby="' . $id . '-' . $name . '-error"';
    }

    return $attrs;
};
$error = static function (string $name) use ($id, $errors): void {
    if (isset($errors[$name])) {
        echo '<p class="fewo-form__error" id="' . $id . '-' . $name . '-error">' . h($errors[$name]) . '</p>';
    }
};
$label = static function (string $name, string $text, bool $required = false) use ($id): void {
    echo '<label for="' . $id . '-' . $name . '">' . h($text) . ($required ? ' <span class="fewo-form__required" aria-hidden="true">*</span>' : '') . '</label>';
};
?>
<div class="fewo-form" id="<?= $id ?>">
    <?php if ($sent) { ?>
        <div class="fewo-form__success" role="status" tabindex="-1" data-fewo-focus>
            <?= nl2br(h($successMessage !== '' ? $successMessage : t('Thank you for your inquiry! We will get back to you as soon as possible.'))) ?>
        </div>
    <?php } else { ?>
        <?php if ($errors !== []) { ?>
            <div class="fewo-form__alert" role="alert" tabindex="-1" data-fewo-focus>
                <?= isset($errors['form']) ? h($errors['form']) : t('Please check the highlighted fields.') ?>
            </div>
        <?php } ?>

        <form method="post" action="<?= h($actionUrl) ?>#<?= $id ?>" novalidate data-fewo-inquiry>
            <?php /* Eigener Feldname statt ccm_token, damit es nicht mit anderen Formularen auf der Seite kollidiert. */ ?>
            <input type="hidden" name="fewo_token" value="<?= h($token->generate($tokenAction)) ?>">

            <div class="fewo-form__hp" aria-hidden="true">
                <label for="<?= $id ?>-website"><?= t('Website') ?></label>
                <input type="text" id="<?= $id ?>-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <fieldset class="fewo-form__group">
                <legend><?= t('Your stay') ?></legend>
                <div class="fewo-form__row">
                    <div class="fewo-form__field">
                        <?php $label('arrival', t('Arrival'), true) ?>
                        <input type="date"<?= $fieldAttrs('arrival', true) ?> min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= $v('arrival') ?>">
                        <?php $error('arrival') ?>
                    </div>
                    <div class="fewo-form__field">
                        <?php $label('departure', t('Departure'), true) ?>
                        <input type="date"<?= $fieldAttrs('departure', true) ?> min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= $v('departure') ?>">
                        <?php $error('departure') ?>
                    </div>
                </div>
                <div class="fewo-form__row">
                    <div class="fewo-form__field">
                        <?php $label('adults', t('Adults'), true) ?>
                        <select<?= $fieldAttrs('adults', true) ?>>
                            <?php for ($i = 1; $i <= $maxGuests; $i++) { ?>
                                <option value="<?= $i ?>"<?= (int) ($values['adults'] ?? 2) === $i ? ' selected' : '' ?>><?= $i ?></option>
                            <?php } ?>
                        </select>
                        <?php $error('adults') ?>
                    </div>
                    <div class="fewo-form__field">
                        <?php $label('children', t('Children')) ?>
                        <select<?= $fieldAttrs('children') ?>>
                            <?php for ($i = 0; $i < $maxGuests; $i++) { ?>
                                <option value="<?= $i ?>"<?= (int) ($values['children'] ?? 0) === $i ? ' selected' : '' ?>><?= $i ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php if ($accommodationList !== []) { ?>
                    <div class="fewo-form__field">
                        <?php $label('accommodation', t('Accommodation'), true) ?>
                        <select<?= $fieldAttrs('accommodation', true) ?>>
                            <option value=""><?= t('Please choose') ?></option>
                            <?php foreach ($accommodationList as $option) { ?>
                                <option value="<?= h($option) ?>"<?= ($values['accommodation'] ?? '') === $option ? ' selected' : '' ?>><?= h($option) ?></option>
                            <?php } ?>
                        </select>
                        <?php $error('accommodation') ?>
                    </div>
                <?php } ?>
            </fieldset>

            <fieldset class="fewo-form__group">
                <legend><?= t('Your contact details') ?></legend>
                <div class="fewo-form__row fewo-form__row--3">
                    <div class="fewo-form__field">
                        <?php $label('salutation', t('Salutation')) ?>
                        <select<?= $fieldAttrs('salutation') ?> autocomplete="honorific-prefix">
                            <option value=""></option>
                            <?php foreach ($salutations as $salutation) { ?>
                                <option value="<?= h($salutation) ?>"<?= ($values['salutation'] ?? '') === $salutation ? ' selected' : '' ?>><?= h($salutation) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="fewo-form__field">
                        <?php $label('firstName', t('First name')) ?>
                        <input type="text"<?= $fieldAttrs('firstName') ?> autocomplete="given-name" maxlength="255" value="<?= $v('firstName') ?>">
                    </div>
                    <div class="fewo-form__field">
                        <?php $label('lastName', t('Last name'), true) ?>
                        <input type="text"<?= $fieldAttrs('lastName', true) ?> autocomplete="family-name" maxlength="255" value="<?= $v('lastName') ?>">
                        <?php $error('lastName') ?>
                    </div>
                </div>
                <div class="fewo-form__row">
                    <div class="fewo-form__field">
                        <?php $label('email', t('Email'), true) ?>
                        <input type="email"<?= $fieldAttrs('email', true) ?> autocomplete="email" maxlength="255" value="<?= $v('email') ?>">
                        <?php $error('email') ?>
                    </div>
                    <div class="fewo-form__field">
                        <?php $label('phone', t('Phone')) ?>
                        <input type="tel"<?= $fieldAttrs('phone') ?> autocomplete="tel" maxlength="40" value="<?= $v('phone') ?>">
                        <?php $error('phone') ?>
                    </div>
                </div>
                <?php if ($askForAddress) { ?>
                    <div class="fewo-form__field">
                        <?php $label('street', t('Street and house number')) ?>
                        <input type="text"<?= $fieldAttrs('street') ?> autocomplete="street-address" maxlength="255" value="<?= $v('street') ?>">
                    </div>
                    <div class="fewo-form__row fewo-form__row--zip">
                        <div class="fewo-form__field">
                            <?php $label('postalCode', t('Postal code')) ?>
                            <input type="text"<?= $fieldAttrs('postalCode') ?> autocomplete="postal-code" maxlength="16" value="<?= $v('postalCode') ?>">
                            <?php $error('postalCode') ?>
                        </div>
                        <div class="fewo-form__field">
                            <?php $label('city', t('City')) ?>
                            <input type="text"<?= $fieldAttrs('city') ?> autocomplete="address-level2" maxlength="255" value="<?= $v('city') ?>">
                        </div>
                        <div class="fewo-form__field">
                            <?php $label('country', t('Country')) ?>
                            <input type="text"<?= $fieldAttrs('country') ?> autocomplete="country-name" maxlength="255" value="<?= $v('country') ?>">
                        </div>
                    </div>
                <?php } ?>
            </fieldset>

            <fieldset class="fewo-form__group">
                <legend><?= t('Your message') ?></legend>
                <div class="fewo-form__field">
                    <?php $label('message', t('Message')) ?>
                    <textarea<?= $fieldAttrs('message') ?> rows="5" maxlength="5000"><?= $v('message') ?></textarea>
                    <?php $error('message') ?>
                </div>
                <?php if ($referralList !== []) { ?>
                    <div class="fewo-form__field">
                        <?php $label('referralSource', t('How did you find us?')) ?>
                        <select<?= $fieldAttrs('referralSource') ?>>
                            <option value=""></option>
                            <?php foreach ($referralList as $option) { ?>
                                <option value="<?= h($option) ?>"<?= ($values['referralSource'] ?? '') === $option ? ' selected' : '' ?>><?= h($option) ?></option>
                            <?php } ?>
                        </select>
                        <?php $error('referralSource') ?>
                    </div>
                <?php } ?>
            </fieldset>

            <?php if ($captcha !== null) { ?>
                <div class="fewo-form__field fewo-form__captcha">
                    <?php if ($captcha->label()) { ?>
                        <label><?= $captcha->label() ?></label>
                    <?php } ?>
                    <div><?php $captcha->display() ?></div>
                    <div><?php $captcha->showInput() ?></div>
                    <?php $error('captcha') ?>
                </div>
            <?php } ?>

            <div class="fewo-form__field fewo-form__consent">
                <label>
                    <input type="checkbox" name="consent" value="1" required<?= !empty($values['consent']) ? ' checked' : '' ?><?= isset($errors['consent']) ? ' aria-invalid="true"' : '' ?>>
                    <?php if ($privacyUrl) { ?>
                        <?= t('I agree that my details will be used to answer my inquiry. More information can be found in the %sprivacy policy%s.', '<a href="' . h($privacyUrl) . '" target="_blank" rel="noopener">', '</a>') ?>
                    <?php } else { ?>
                        <?= t('I agree that my details will be used to answer my inquiry.') ?>
                    <?php } ?>
                    <span class="fewo-form__required" aria-hidden="true">*</span>
                </label>
                <?php $error('consent') ?>
            </div>

            <div class="fewo-form__actions">
                <button type="submit" class="btn btn-primary fewo-btn fewo-form__submit"><?= t('Send inquiry') ?></button>
                <p class="fewo-form__hint"><span aria-hidden="true">*</span> <?= t('Required field') ?></p>
            </div>
        </form>
    <?php } ?>
</div>
