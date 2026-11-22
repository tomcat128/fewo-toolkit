<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $apartmentID
 * @var string $title
 * @var string $newsSettingsUrl
 * @var array $apartmentOptions
 * @var string $apartmentsUrl
 */
?>
<?php Concrete\Core\View\View::element('apartment_select', [
    'form' => $form,
    'apartmentID' => $apartmentID,
    'apartmentOptions' => $apartmentOptions,
    'apartmentsUrl' => $apartmentsUrl,
    'required' => true,
    'help' => t('The news of this apartment are loaded from Fewo-Office (news URL of the apartment).'),
], 'fewo_tk2') ?>

<div class="mb-3">
    <?= $form->label('title', t('Heading (optional)')) ?>
    <?= $form->text('title', $title) ?>
    <div class="form-text"><?= t('Only shown when there is current news.') ?></div>
</div>

<div class="alert alert-info mb-0">
    <?= t('Importance levels, colours and the period of validity apply to all news blocks: <a href="%s" target="_blank">Fewo Toolkit 2 › News</a>.', h($newsSettingsUrl)) ?>
</div>

<p class="form-text mt-3 mb-0">
    <?= t('News are written in Fewo-Office. Each one appears here from the first to the last day of its period; when there is no current news, the block shows nothing.') ?>
</p>
