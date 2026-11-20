<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var string $icsUrl
 * @var int $refreshMinutes
 * @var int $monthsVisible
 * @var int $monthsTotal
 * @var string $onRequestKeywords
 * @var bool $showLegend
 * @var array $refreshOptions
 * @var FewoToolkit\Entity\CalendarFeed|null $feed
 */

$dateService = app('date');
?>
<fieldset>
    <legend><?= t('Calendar') ?></legend>

    <div class="mb-3">
        <?= $form->label('icsUrl', t('Calendar URL (ICS / iCal)')) ?>
        <?= $form->url('icsUrl', $icsUrl, ['required' => 'required', 'placeholder' => 'https://www.airbnb.de/calendar/ical/….ics']) ?>
        <div class="form-text">
            <?= t('Export link of your booking calendar, e.g. Airbnb, Booking.com, Fewo-direkt/Vrbo, Smoobu or Google Calendar. webcal:// links are accepted as well.') ?>
        </div>
    </div>

    <div class="mb-3">
        <?= $form->label('refreshMinutes', t('Update interval')) ?>
        <?= $form->select('refreshMinutes', $refreshOptions, (int) $refreshMinutes) ?>
        <div class="form-text"><?= t('The calendar is loaded again on the next page view after this time. No cron job is required.') ?></div>
    </div>

    <?php if ($feed !== null) { ?>
        <div class="mb-3">
            <div class="alert <?= $feed->getLastError() ? 'alert-warning' : 'alert-info' ?> mb-2">
                <?php if ($feed->getFetchedAt()) { ?>
                    <?= t('Last successful update: %s', h($dateService->formatDateTime($feed->getFetchedAt(), true))) ?>
                <?php } else { ?>
                    <?= t('The calendar has not been loaded successfully yet.') ?>
                <?php } ?>
                <?php if ($feed->getLastError()) { ?>
                    <br><strong><?= t('Last error:') ?></strong> <?= h($feed->getLastError()) ?>
                <?php } ?>
            </div>
            <div class="form-check">
                <?= $form->checkbox('forceRefresh', 1, false) ?>
                <?= $form->label('forceRefresh', t('Reload the calendar now when saving'), ['class' => 'form-check-label']) ?>
            </div>
        </div>
    <?php } ?>

    <div class="mb-3">
        <?= $form->label('onRequestKeywords', t('Keywords for "on request"')) ?>
        <?= $form->text('onRequestKeywords', $onRequestKeywords) ?>
        <div class="form-text"><?= t('Events whose title contains one of these words (comma-separated) are shown as "on request" instead of "booked". Leave empty to show all events as booked.') ?></div>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Display') ?></legend>

    <div class="mb-3">
        <?= $form->label('title', t('Heading (optional)')) ?>
        <?= $form->text('title', $title) ?>
    </div>

    <div class="row">
        <div class="col-sm-6 mb-3">
            <?= $form->label('monthsVisible', t('Months shown initially')) ?>
            <?= $form->number('monthsVisible', (int) $monthsVisible, ['min' => 1, 'max' => 36]) ?>
        </div>
        <div class="col-sm-6 mb-3">
            <?= $form->label('monthsTotal', t('Months in total')) ?>
            <?= $form->number('monthsTotal', (int) $monthsTotal, ['min' => 1, 'max' => 36]) ?>
        </div>
    </div>

    <div class="form-check mb-3">
        <?= $form->checkbox('showLegend', 1, (bool) $showLegend) ?>
        <?= $form->label('showLegend', t('Show legend'), ['class' => 'form-check-label']) ?>
    </div>
</fieldset>
