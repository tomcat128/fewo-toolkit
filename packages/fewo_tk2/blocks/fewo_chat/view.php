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
 * @var string $assistantName Name des Assistenten (Einstellungen) oder "Assistent"
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
    'assistant' => $assistantName,
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

    <?php Concrete\Core\View\View::element('chat_body', [
        'id' => $id,
        'assistantName' => $assistantName,
        'welcomeMessage' => (string) $welcomeMessage,
        'examples' => $examples,
        'notice' => (string) $notice,
        'actionUrl' => $actionUrl,
        'maxLength' => $maxLength,
    ], 'fewo_tk2') ?>
</section>
