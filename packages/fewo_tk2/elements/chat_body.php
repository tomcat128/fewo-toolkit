<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Inhalt eines Chats: Gespräch, Beispielfragen, Eingabefeld und Hinweis – im Block "Chat" und im Chat auf jeder Seite.
 *
 * @var string $id eindeutige Kennung des Chats auf der Seite
 * @var string $assistantName
 * @var string $welcomeMessage
 * @var string[] $examples
 * @var string $notice
 * @var string $actionUrl
 * @var int $maxLength
 */
?>
<div class="fewo-chat__log" role="log" aria-live="polite" aria-label="<?= h(t('Conversation')) ?>" tabindex="0" data-fewo-chat-log>
    <?php if (trim((string) $welcomeMessage) !== '') { ?>
        <div class="fewo-chat__message fewo-chat__message--assistant">
            <span class="fewo-chat__author"><?= h($assistantName) ?></span>
            <div class="fewo-chat__text"><?= nl2br(h($welcomeMessage)) ?></div>
        </div>
    <?php } ?>
</div>

<?php if ($examples !== []) { ?>
    <div class="fewo-chat__examples" role="group" aria-label="<?= h(t('Example questions')) ?>">
        <?php foreach ($examples as $example) { ?>
            <button type="button" class="fewo-chat__example" data-fewo-chat-example><?= h($example) ?></button>
        <?php } ?>
    </div>
<?php } ?>

<form class="fewo-chat__form" action="<?= h($actionUrl) ?>" method="post" novalidate data-fewo-chat-form>
    <label class="fewo-chat__label" for="<?= h($id) ?>-question"><?= t('Your question') ?></label>
    <div class="fewo-chat__row">
        <input type="text" id="<?= h($id) ?>-question" name="question" maxlength="<?= (int) $maxLength ?>" autocomplete="off" required
            placeholder="<?= h(t('e.g. Is there a parking space?')) ?>">
        <button type="submit" class="btn btn-primary fewo-btn fewo-chat__submit"><?= t('Send') ?></button>
    </div>
</form>

<?php if (trim((string) $notice) !== '') { ?>
    <p class="fewo-chat__notice"><?= nl2br(h($notice)) ?></p>
<?php } ?>
<noscript><p class="fewo-chat__notice"><?= t('The chat requires JavaScript.') ?></p></noscript>
