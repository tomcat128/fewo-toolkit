<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoTk2\Assistant\AssistantSettings $settings
 * @var string[] $models in Ollama installierte Modelle
 * @var string|null $connectionError
 * @var array<int, string> $contextOptions
 * @var array<int, array{title: string, url: string, chars: int}> $overview
 * @var int $questionsLastHour
 * @var int $questionsLastDay
 * @var string $testQuestion
 * @var string|null $testAnswer
 * @var string|null $testError
 * @var float|null $testSeconds
 */

$token = app('token');
$form = app('helper/form');
$totalChars = array_sum(array_column($overview, 'chars'));
$number = static fn (int $value): string => number_format($value, 0, ',', '.');
?>
<p class="lead"><?= t('The assistant answers visitors\' questions in the block "Chat (AI Assistant)" – based on the current content of this website and with a language model running in Ollama on your own server.') ?></p>

<div id="fewo-assistant-status">
    <?php if ($connectionError !== null) { ?>
        <div class="alert alert-danger"><strong><?= t('No connection to Ollama.') ?></strong> <?= h($connectionError) ?></div>
    <?php } elseif ($models === []) { ?>
        <div class="alert alert-warning"><?= t('Ollama is reachable, but no model is installed yet. Install one on the server, e.g. with "ollama pull gemma3:4b".') ?></div>
    <?php } elseif ($settings->model !== '' && !in_array($settings->model, $models, true)) { ?>
        <div class="alert alert-warning"><?= t('Ollama is reachable, but the model "%s" is not installed there.', h($settings->model)) ?></div>
    <?php } elseif (!$settings->isReady()) { ?>
        <div class="alert alert-info"><?= t('Ollama is reachable. The assistant is switched off or no model has been chosen – visitors do not see the chat yet.') ?></div>
    <?php } else { ?>
        <div class="alert alert-success"><?= t('The assistant is switched on and answers with the model "%s".', h($settings->model)) ?></div>
    <?php } ?>
</div>

<form method="post" action="<?= h($view->action('save')) ?>" id="fewo-assistant-form" style="max-width: 48rem">
    <?php $token->output('fewo_assistant_save') ?>

    <fieldset class="mb-4">
        <legend><?= t('Assistant') ?></legend>
        <div class="form-check">
            <?= $form->checkbox('enabled', 1, $settings->enabled) ?>
            <?= $form->label('enabled', t('Switch on the assistant (visitors see the chat blocks)'), ['class' => 'form-check-label']) ?>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend><?= t('Ollama') ?></legend>
        <div class="mb-3">
            <?= $form->label('ollamaUrl', t('Ollama URL')) ?>
            <?= $form->text('ollamaUrl', $settings->ollamaUrl, ['required' => 'required', 'maxlength' => 500, 'placeholder' => FewoTk2\Assistant\AssistantSettings::DEFAULT_URL]) ?>
            <div class="form-text"><?= t('Address at which this web server reaches Ollama, e.g. http://localhost:11434 – or http://ollama:11434 if Ollama runs as a Docker container in the same network.') ?></div>
        </div>
        <div class="mb-3">
            <?= $form->label('model', t('Model')) ?>
            <?= $form->text('model', $settings->model, ['maxlength' => 200, 'list' => 'fewo-assistant-models', 'autocomplete' => 'off', 'placeholder' => 'gemma3:4b']) ?>
            <datalist id="fewo-assistant-models">
                <?php foreach ($models as $model) { ?>
                    <option value="<?= h($model) ?>"></option>
                <?php } ?>
            </datalist>
            <div class="form-text">
                <?php if ($models !== []) { ?>
                    <?= t('Installed in Ollama: %s', h(implode(', ', $models))) ?>
                <?php } else { ?>
                    <?= t('Name of a model installed in Ollama, e.g. gemma3:4b.') ?>
                <?php } ?>
            </div>
        </div>
        <div class="mb-3">
            <?= $form->label('apiKey', t('API key (optional)')) ?>
            <?= $form->password('apiKey', '', ['maxlength' => 500, 'autocomplete' => 'new-password', 'placeholder' => $settings->apiKey !== '' ? t('saved – leave empty to keep it') : '']) ?>
            <div class="form-text"><?= t('Only needed if Ollama is protected by a reverse proxy that expects a bearer token. Ollama itself needs no key.') ?></div>
            <?php if ($settings->apiKey !== '') { ?>
                <div class="form-check mt-1">
                    <?= $form->checkbox('clearApiKey', 1, false) ?>
                    <?= $form->label('clearApiKey', t('Delete the saved key'), ['class' => 'form-check-label']) ?>
                </div>
            <?php } ?>
        </div>
        <div class="mb-3" style="max-width: 20rem">
            <?= $form->label('timeout', t('Maximum waiting time for an answer (seconds)')) ?>
            <?= $form->number('timeout', $settings->timeout, ['min' => 10, 'max' => 600]) ?>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend><?= t('Knowledge') ?></legend>
        <div class="mb-3" style="max-width: 20rem">
            <?= $form->label('contextChars', t('Website content per question')) ?>
            <?= $form->select('contextChars', $contextOptions, $settings->contextChars) ?>
        </div>
        <div class="form-text mb-3"><?= t('Best: the whole website fits (see "What the assistant knows" below). The model then always gets everything; Ollama reads it once and afterwards answers start within seconds. If the website has more text, only the sections that match the question best are passed on – slower and less accurate.') ?></div>
        <div class="form-check mb-3">
            <?= $form->checkbox('includeApartments', 1, $settings->includeApartments) ?>
            <?= $form->label('includeApartments', t('Include prices and free periods of the apartments (from Fewo-Office)'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="mb-3">
            <?= $form->label('excludedPaths', t('Pages the assistant should not know')) ?>
            <?= $form->textarea('excludedPaths', $settings->excludedPaths, ['rows' => 3, 'placeholder' => "/impressum\n/datenschutz"]) ?>
            <div class="form-text"><?= t('One path per line; subpages are excluded as well. Pages that are not public or are excluded from the search index are never included.') ?></div>
        </div>
        <div class="mb-3">
            <?= $form->label('instructions', t('Additional instructions for the assistant (optional)')) ?>
            <?= $form->textarea('instructions', $settings->instructions, ['rows' => 4, 'maxlength' => 4000, 'placeholder' => t('e.g. Address visitors informally. Pets are welcome on request.')]) ?>
            <div class="form-text"><?= t('Tone of voice or facts that are not on the website. The basic rules (answer only from the website content, no invented prices) always apply.') ?></div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend><?= t('Limits') ?></legend>
        <div class="row g-3" style="max-width: 40rem">
            <div class="col-sm-6">
                <?= $form->label('visitorLimit', t('Questions per visitor and hour')) ?>
                <?= $form->number('visitorLimit', $settings->visitorLimit, ['min' => 1, 'max' => 1000]) ?>
            </div>
            <div class="col-sm-6">
                <?= $form->label('siteLimit', t('Questions per hour in total')) ?>
                <?= $form->number('siteLimit', $settings->siteLimit, ['min' => 1, 'max' => 100000]) ?>
            </div>
        </div>
        <div class="form-text"><?= t('Protects the server from overload. Questions in the last hour: %1$s, in the last 24 hours: %2$s. Questions and answers are not stored.', $number($questionsLastHour), $number($questionsLastDay)) ?></div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button type="submit" class="btn btn-primary float-end"><?= t('Save') ?></button>
        </div>
    </div>
</form>

<hr class="my-5">

<h3><?= t('Try it out') ?></h3>
<form method="post" action="<?= h($view->action('ask')) ?>#fewo-assistant-test" id="fewo-assistant-test" style="max-width: 48rem">
    <?php $token->output('fewo_assistant_ask') ?>
    <div class="input-group mb-2">
        <?= $form->text('testQuestion', $testQuestion, ['maxlength' => 500, 'placeholder' => t('e.g. What does a night cost?'), 'aria-label' => t('Test question')]) ?>
        <button type="submit" class="btn btn-secondary"><?= t('Ask') ?></button>
    </div>
    <div class="form-text"><?= t('Uses the saved settings and also works while the assistant is still switched off.') ?></div>
    <?php if ($testError !== null) { ?>
        <div class="alert alert-danger mt-3" id="fewo-assistant-test-error"><?= h($testError) ?></div>
    <?php } elseif ($testAnswer !== null) { ?>
        <div class="card mt-3">
            <div class="card-body">
                <p class="card-text" id="fewo-assistant-test-answer" style="white-space: pre-wrap"><?= h($testAnswer) ?></p>
                <p class="card-text text-muted small"><?= t('Answered in %s seconds.', number_format((float) $testSeconds, 1, ',', '.')) ?></p>
            </div>
        </div>
    <?php } ?>
</form>

<hr class="my-5">

<h3><?= t('What the assistant knows') ?></h3>
<p><?= t('Public pages of this website with the text of their blocks, plus prices and free periods from Fewo-Office. Changes are taken over when a page is published, at the latest after ten minutes.') ?></p>
<?php if ($overview === []) { ?>
    <div class="alert alert-warning"><?= t('No content found yet.') ?></div>
<?php } else { ?>
    <?php if ($totalChars > $settings->contextChars) { ?>
        <div class="alert alert-warning" id="fewo-assistant-too-much" style="max-width: 48rem"><?= t('The website has more text (%1$s characters) than is passed on per question (%2$s). Increase "Website content per question" or exclude pages – otherwise the assistant only sees a selection for each question.', $number($totalChars), $number($settings->contextChars)) ?></div>
    <?php } ?>
    <div class="table-responsive" style="max-width: 48rem">
        <table class="table table-sm" id="fewo-assistant-knowledge">
            <thead>
                <tr>
                    <th><?= t('Page') ?></th>
                    <th><?= t('Path') ?></th>
                    <th class="text-end"><?= t('Characters') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overview as $entry) { ?>
                    <tr>
                        <td><?= h($entry['title']) ?></td>
                        <td><?php if ($entry['url'] !== '') { ?><a href="<?= h($entry['url']) ?>" target="_blank" rel="noopener"><?= h($entry['url']) ?></a><?php } else { ?>–<?php } ?></td>
                        <td class="text-end"><?= $number($entry['chars']) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2"><?= t('Total') ?></th>
                    <th class="text-end"><?= $number($totalChars) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
<?php } ?>
<form method="post" action="<?= h($view->action('refresh')) ?>">
    <?php $token->output('fewo_assistant_refresh') ?>
    <button type="submit" class="btn btn-secondary"><?= t('Read the content again now') ?></button>
</form>
