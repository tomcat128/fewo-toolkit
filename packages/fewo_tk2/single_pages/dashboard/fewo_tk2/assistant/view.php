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
        <div class="mt-3">
            <?= $form->label('name', t('Name of the assistant (optional)')) ?>
            <?= $form->text('name', $settings->name, ['maxlength' => FewoTk2\Assistant\AssistantSettings::MAX_NAME_CHARS, 'placeholder' => t('e.g. Emma'), 'style' => 'max-width: 20rem']) ?>
            <div class="form-text"><?= t('Shown in the chat above each answer; the assistant also knows its name. Without a name it is called "Assistant".') ?></div>
        </div>
    </fieldset>

    <fieldset class="mb-4" id="fewo-assistant-widget">
        <legend><?= t('Chat at the bottom right') ?></legend>
        <div class="form-check">
            <?= $form->checkbox('widgetEnabled', 1, $settings->widgetEnabled) ?>
            <?= $form->label('widgetEnabled', t('Show the chat at the bottom right of the website'), ['class' => 'form-check-label']) ?>
        </div>
        <div class="form-text mb-3"><?= t('Visitors first see a small button with the hint text; a click opens the chat. It needs no chat block and appears once the assistant is switched on. On pages that contain a chat block, only the block is shown.') ?></div>
        <div class="mb-3" id="fewo-assistant-widget-pages">
            <div class="form-check">
                <?= $form->radio('widgetPagesOnly', 0, $settings->widgetPagesOnly ? 1 : 0, ['id' => 'widgetPagesAll']) ?>
                <label class="form-check-label" for="widgetPagesAll"><?= t('On all pages') ?></label>
            </div>
            <div class="form-check">
                <?= $form->radio('widgetPagesOnly', 1, $settings->widgetPagesOnly ? 1 : 0, ['id' => 'widgetPagesSelected']) ?>
                <label class="form-check-label" for="widgetPagesSelected"><?= t('Only on certain pages') ?></label>
            </div>
            <div class="mt-2" data-fewo-widget-pages<?= $settings->widgetPagesOnly ? '' : ' hidden' ?>>
                <?= $form->label('widgetPages', t('Pages with the chat')) ?>
                <?= $form->textarea('widgetPages', $settings->widgetPages, ['rows' => 4, 'placeholder' => "/\n/ferienwohnung\n/preise", 'style' => 'max-width: 28rem']) ?>
                <div class="form-text"><?= t('One path per line, e.g. /prices – subpages are included. A single / stands for the home page. You can also paste the address of a page from the browser.') ?></div>
            </div>
        </div>
        <script>
        (() => {
            const root = document.getElementById('fewo-assistant-widget-pages');
            const field = root.querySelector('[data-fewo-widget-pages]');
            const update = () => {
                field.hidden = !root.querySelector('#widgetPagesSelected').checked;
            };
            root.querySelectorAll('input[type="radio"]').forEach((radio) => radio.addEventListener('change', update));
            update();
        })();
        </script>
        <div class="mb-3">
            <?= $form->label('widgetHint', t('Hint text of the button')) ?>
            <?= $form->text('widgetHint', $settings->widgetHint, ['maxlength' => FewoTk2\Assistant\AssistantSettings::MAX_HINT_CHARS, 'style' => 'max-width: 28rem']) ?>
            <div class="form-text"><?= t('Short invitation next to the chat symbol. Leave empty to show only the symbol.') ?></div>
        </div>
        <div class="mb-3">
            <?= $form->label('widgetGreeting', t('Greeting')) ?>
            <?= $form->textarea('widgetGreeting', $settings->widgetGreeting, ['rows' => 3, 'maxlength' => 1000]) ?>
            <div class="form-text"><?= t('First message of the assistant in the chat.') ?></div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= t('Example questions') ?></label>
            <?php Concrete\Core\View\View::element('question_rows', [
                'name' => 'widgetQuestions',
                'questions' => $settings->getWidgetQuestions(),
                'max' => FewoTk2\Assistant\AssistantSettings::MAX_EXAMPLES,
                'maxLength' => FewoTk2\Assistant\AssistantSettings::MAX_QUESTION_CHARS,
            ], 'fewo_tk2') ?>
        </div>
        <div class="mb-3">
            <?= $form->label('widgetNotice', t('Note below the input field')) ?>
            <?= $form->textarea('widgetNotice', $settings->widgetNotice, ['rows' => 2, 'maxlength' => 1000]) ?>
            <div class="form-text"><?= t('Tell your visitors that an AI is answering.') ?></div>
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
            <?= $form->label('includeApartments', t('Include prices, free periods and current news of the apartments (from Fewo-Office)'), ['class' => 'form-check-label']) ?>
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

    <fieldset class="mb-4" id="fewo-assistant-keywords">
        <legend><?= t('Explanations for keywords') ?></legend>
        <p class="form-text mt-0"><?= t('If a visitor\'s question contains one of the keywords, the assistant receives your explanation with it and answers accordingly – even if the website says something different. Use this to correct wrong answers or to add what the website does not say. Separate several keywords with commas; upper and lower case and umlauts do not matter, and a keyword also matches inside longer words ("parking" in "parking space").') ?></p>
        <?php Concrete\Core\View\View::element('note_rows', [
            'notes' => $settings->notes,
            'max' => FewoTk2\Assistant\KeywordNotes::MAX_NOTES,
            'maxKeywords' => FewoTk2\Assistant\KeywordNotes::MAX_KEYWORDS_CHARS,
            'maxText' => FewoTk2\Assistant\KeywordNotes::MAX_TEXT_CHARS,
        ], 'fewo_tk2') ?>
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
