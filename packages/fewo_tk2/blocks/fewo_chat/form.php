<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var string $welcomeMessage
 * @var string $exampleQuestions eine Frage pro Zeile
 * @var string $notice
 * @var bool $assistantReady
 * @var string $assistantUrl
 * @var string $assistantName Name aus den Einstellungen des Assistenten, leer = kein Name
 * @var int $maxExamples
 * @var int $maxLength
 */

?>
<?php if (!$assistantReady) { ?>
    <div class="alert alert-warning">
        <?= t('The AI assistant is not set up yet. Visitors only see the chat once it has been switched on and a model has been selected.') ?>
        <a href="<?= h($assistantUrl) ?>" target="_blank" rel="noopener"><?= t('Set up the assistant') ?></a>
    </div>
<?php } ?>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title, ['maxlength' => 255]) ?>
</div>

<div class="mb-3">
    <?= $form->label('welcomeMessage', t('Greeting')) ?>
    <?= $form->textarea('welcomeMessage', $welcomeMessage, ['rows' => 3]) ?>
    <div class="form-text">
        <?= t('First message of the assistant in the chat.') ?>
        <?= $assistantName !== ''
            ? t('The assistant is called "%s".', h($assistantName))
            : t('The assistant has no name yet.') ?>
        <a href="<?= h($assistantUrl) ?>" target="_blank" rel="noopener"><?= t('Change the name') ?></a>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><?= t('Example questions') ?></label>
    <?php Concrete\Core\View\View::element('question_rows', [
        'name' => 'exampleQuestion',
        'questions' => FewoTk2\Assistant\AssistantSettings::questionLines((string) $exampleQuestions),
        'max' => $maxExamples,
        'maxLength' => $maxLength,
    ], 'fewo_tk2') ?>
</div>

<div class="mb-3">
    <?= $form->label('notice', t('Note below the input field')) ?>
    <?= $form->textarea('notice', $notice, ['rows' => 2]) ?>
    <div class="form-text">
        <?= t('Tell your visitors that an AI is answering.') ?>
        <a href="<?= h($assistantUrl) ?>" target="_blank" rel="noopener"><?= t('Assistant settings') ?></a>
    </div>
</div>
