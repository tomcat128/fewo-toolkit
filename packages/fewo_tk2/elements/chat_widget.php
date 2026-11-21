<?php

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Support\Facade\Url;

/**
 * Chat auf jeder Seite: unten rechts eine kleine Schaltfläche mit Hinweistext, die den Chat öffnet.
 * Wird vom Paket ans Ende jeder Seite gehängt (Dashboard › Fewo Toolkit › KI-Assistent).
 * Ohne JavaScript bleibt er unsichtbar.
 *
 * @var FewoTk2\Assistant\AssistantSettings $settings
 * @var int $maxLength
 */

$name = $settings->name !== '' ? $settings->name : t('Assistant');
$labels = [
    'failed' => t('The assistant cannot answer at the moment. Please try again later or contact us directly.'),
    'sources' => t('More on this:'),
    'you' => t('You'),
    'assistant' => $name,
];
$openLabel = $settings->name !== '' ? t('Open the chat with %s', $settings->name) : t('Open the chat');
?>
<div class="fewo-chat-widget" data-fewo-chat-widget hidden>
    <button type="button" class="fewo-chat-widget__launcher<?= $settings->widgetHint === '' ? ' fewo-chat-widget__launcher--icon' : '' ?>"
        data-fewo-chat-open aria-expanded="false" aria-controls="fewo-chat-widget-panel"
        <?= $settings->widgetHint === '' ? 'aria-label="' . h($openLabel) . '"' : 'title="' . h($openLabel) . '"' ?>>
        <svg class="fewo-chat-widget__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.4L3 21l2.1-5.7A8.4 8.4 0 1 1 21 11.5z"/>
        </svg>
        <?php if ($settings->widgetHint !== '') { ?>
            <span class="fewo-chat-widget__hint"><?= h($settings->widgetHint) ?></span>
        <?php } ?>
    </button>

    <div class="fewo-chat-widget__panel" id="fewo-chat-widget-panel" role="dialog" aria-labelledby="fewo-chat-widget-title" data-fewo-chat-panel hidden>
        <div class="fewo-chat-widget__header">
            <strong class="fewo-chat-widget__title" id="fewo-chat-widget-title"><?= h($name) ?></strong>
            <button type="button" class="fewo-chat-widget__close" data-fewo-chat-close aria-label="<?= h(t('Close the chat')) ?>">×</button>
        </div>
        <section class="fewo-chat fewo-chat--widget" data-fewo-chat data-persist="widget"
            data-url="<?= h((string) Url::to('/fewo_tk2/chat/ask')) ?>"
            data-warm-url="<?= h((string) Url::to('/fewo_tk2/chat/warm')) ?>"
            data-token-url="<?= h((string) Url::to('/fewo_tk2/chat/token')) ?>"
            data-labels="<?= h((string) json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>"
            aria-labelledby="fewo-chat-widget-title">
            <?php Concrete\Core\View\View::element('chat_body', [
                'id' => 'fewo-chat-widget',
                'assistantName' => $name,
                'welcomeMessage' => $settings->widgetGreeting,
                'examples' => $settings->getWidgetQuestions(),
                'notice' => $settings->widgetNotice,
                'actionUrl' => (string) Url::to('/fewo_tk2/chat/ask'),
                'maxLength' => $maxLength,
            ], 'fewo_tk2') ?>
        </section>
    </div>
</div>
