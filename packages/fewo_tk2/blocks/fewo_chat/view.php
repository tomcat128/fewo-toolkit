<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var bool $ready
 * @var bool $canEdit
 * @var string $assistantUrl
 * @var string $title
 * @var string $welcomeMessage
 * @var string $notice
 * @var string[] $examples
 * @var string $tokenAction
 * @var string $actionUrl
 * @var string $warmUrl
 * @var int $maxLength
 */

if (!$ready) {
    // Besucher sehen den Chat erst, wenn der Assistent eingerichtet ist
    if ($canEdit) { ?>
        <div class="fewo-notice fewo-notice--warning" role="status">
            <strong><?= t('Chat (AI Assistant)') ?>:</strong>
            <?= t('The AI assistant is not set up yet. Visitors only see the chat once it has been switched on and a model has been selected.') ?>
            <a href="<?= h($assistantUrl) ?>"><?= t('Set up the assistant') ?></a>
        </div>
    <?php }

    return;
}

$id = 'fewo-chat-' . (int) $bID;
$labels = [
    'failed' => t('The assistant cannot answer at the moment. Please try again later or contact us directly.'),
    'sources' => t('More on this:'),
    'you' => t('You'),
    'assistant' => t('Assistant'),
];
?>
<section class="fewo-chat" id="<?= $id ?>" data-fewo-chat
    data-url="<?= h($actionUrl) ?>"
    data-warm-url="<?= h($warmUrl) ?>"
    data-token="<?= h(app('token')->generate($tokenAction)) ?>"
    data-labels="<?= h((string) json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>"
    <?= $title !== '' ? 'aria-labelledby="' . $id . '-title"' : 'aria-label="' . h(t('Chat (AI Assistant)')) . '"' ?>>
    <?php if ($title !== '') { ?>
        <h2 class="fewo-chat__title" id="<?= $id ?>-title"><?= h($title) ?></h2>
    <?php } ?>

    <div class="fewo-chat__log" role="log" aria-live="polite" aria-label="<?= h(t('Conversation')) ?>" tabindex="0" data-fewo-chat-log>
        <?php if (trim((string) $welcomeMessage) !== '') { ?>
            <div class="fewo-chat__message fewo-chat__message--assistant">
                <span class="fewo-chat__author"><?= t('Assistant') ?></span>
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
        <label class="fewo-chat__label" for="<?= $id ?>-question"><?= t('Your question') ?></label>
        <div class="fewo-chat__row">
            <input type="text" id="<?= $id ?>-question" name="question" maxlength="<?= (int) $maxLength ?>" autocomplete="off" required
                placeholder="<?= h(t('e.g. Is there a parking space?')) ?>">
            <button type="submit" class="btn btn-primary fewo-chat__submit"><?= t('Send') ?></button>
        </div>
    </form>

    <?php if (trim((string) $notice) !== '') { ?>
        <p class="fewo-chat__notice"><?= nl2br(h($notice)) ?></p>
    <?php } ?>
    <noscript><p class="fewo-chat__notice"><?= t('The chat requires JavaScript.') ?></p></noscript>
</section>
