<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoTk2\News\NewsSettings $settings
 */

$token = app('token');
$form = app('helper/form');
$apartmentsUrl = (string) Concrete\Core\Support\Facade\Url::to('/dashboard/fewo_tk2/apartments');
?>
<p class="lead"><?= t('News are written in Fewo-Office. Here you decide how the blocks "News" show them – the settings apply to all news blocks of this website.') ?></p>
<p class="text-muted"><?= t('The address of the news of each apartment is stored under <a href="%s">Apartments</a>; in the block you only choose the apartment and a heading.', h($apartmentsUrl)) ?></p>

<form method="post" action="<?= h($view->action('save')) ?>" id="fewo-news-settings">
    <?php $token->output('fewo_news_save') ?>

    <fieldset class="mb-4">
        <legend><?= t('Display') ?></legend>
        <div class="form-check form-switch">
            <?= $form->checkbox('showPeriod', 1, $settings->showPeriod, ['role' => 'switch']) ?>
            <?= $form->label('showPeriod', t('Show the period of validity'), ['class' => 'form-check-label']) ?>
            <div class="form-text"><?= t('Below the title, e.g. „Valid from 01.12. to 24.12.“. When switched off, only the title and text are shown.') ?></div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend><?= t('Importance levels') ?></legend>
        <?php Concrete\Core\View\View::element('importance_rows', ['levels' => $settings->levels], 'fewo_tk2') ?>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button type="submit" class="btn btn-primary float-end"><?= t('Save') ?></button>
        </div>
    </div>
</form>
