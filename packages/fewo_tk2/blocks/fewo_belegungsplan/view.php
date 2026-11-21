<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\FewoTk2\Block\FewoBelegungsplan\Controller $controller
 * @var string $title
 * @var array $months
 * @var int $monthsVisible
 * @var bool $showLegend
 * @var bool $hasRequestStatus
 * @var FewoTk2\Entity\RemoteFeed|null $feed
 * @var string|null $error
 * @var bool $canEdit
 * @var Concrete\Core\Localization\Service\Date $dateService
 */

$statusText = static function (string $am, string $pm): string {
    $label = [
        'free' => t('free'),
        'booked' => t('booked'),
        'request' => t('on request'),
    ];
    if ($am === $pm) {
        return $label[$am];
    }
    if ($am === 'free') {
        return t('arrival day – %s from the afternoon', $label[$pm]);
    }
    if ($pm === 'free') {
        return t('departure day – %s until the morning, free from the afternoon', $label[$am]);
    }

    return t('changeover day');
};

// Wochentagsnamen ab Montag (5. Januar 2026 war ein Montag)
$weekdays = [];
for ($i = 0; $i < 7; $i++) {
    $day = new DateTimeImmutable('2026-01-05 +' . $i . ' days');
    $weekdays[] = [
        'short' => $dateService->formatCustom('D', $day, 'system'),
        'long' => $dateService->formatCustom('l', $day, 'system'),
    ];
}

$renderMonth = static function (array $month) use ($dateService, $weekdays, $statusText): void {
    ?>
    <section class="fewo-calendar__month" aria-label="<?= h($dateService->formatCustom('F Y', $month['month'], 'system')) ?>">
        <h3 class="fewo-calendar__month-title"><?= h($dateService->formatCustom('F Y', $month['month'], 'system')) ?></h3>
        <div class="fewo-calendar__weekdays" aria-hidden="true">
            <?php foreach ($weekdays as $weekday) { ?>
                <span title="<?= h($weekday['long']) ?>"><?= h($weekday['short']) ?></span>
            <?php } ?>
        </div>
        <ol class="fewo-calendar__days" role="list">
            <?php for ($i = 0; $i < $month['leading']; $i++) { ?>
                <li class="fewo-day fewo-day--empty" aria-hidden="true"></li>
            <?php } ?>
            <?php foreach ($month['days'] as $day) {
                $classes = ['fewo-day', 'fewo-day--am-' . $day['am'], 'fewo-day--pm-' . $day['pm']];
                if ($day['past']) {
                    $classes[] = 'fewo-day--past';
                }
                if ($day['today']) {
                    $classes[] = 'fewo-day--today';
                }
                $label = $dateService->formatCustom('l, j. F Y', $day['date'], 'system') . ': ' . ($day['past'] ? t('in the past') : $statusText($day['am'], $day['pm']));
                ?>
                <li class="<?= implode(' ', $classes) ?>" title="<?= h($label) ?>"<?= $day['today'] ? ' aria-current="date"' : '' ?>>
                    <span class="fewo-day__number" aria-hidden="true"><?= $day['date']->format('j') ?></span>
                    <span class="fewo-visually-hidden"><?= h($label) ?></span>
                </li>
            <?php } ?>
        </ol>
    </section>
    <?php
};
?>
<div class="fewo-calendar" id="fewo-calendar-<?= (int) $bID ?>" style="--fewo-columns: <?= (int) $monthsVisible ?>">
    <?php if ($title !== '') { ?>
        <h2 class="fewo-calendar__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($canEdit) {
        Concrete\Core\View\View::element('feed_notice', ['label' => t('Occupancy calendar'), 'error' => $error, 'feed' => $feed], 'fewo_tk2');
    } ?>

    <?php if ($error !== null && !$canEdit) { ?>
        <p class="fewo-calendar__unavailable"><?= t('The occupancy calendar is currently not available. Please contact us for availability.') ?></p>
    <?php } else { ?>
        <div class="fewo-calendar__months">
            <?php foreach (array_slice($months, 0, $monthsVisible) as $month) {
                $renderMonth($month);
            } ?>
        </div>

        <?php if (count($months) > $monthsVisible) { ?>
            <details class="fewo-calendar__more">
                <summary class="fewo-calendar__more-toggle">
                    <span class="fewo-calendar__more-show"><?= t('Show more months') ?></span>
                    <span class="fewo-calendar__more-hide"><?= t('Show fewer months') ?></span>
                </summary>
                <div class="fewo-calendar__months">
                    <?php foreach (array_slice($months, $monthsVisible) as $month) {
                        $renderMonth($month);
                    } ?>
                </div>
            </details>
        <?php } ?>

        <?php if ($showLegend) { ?>
            <ul class="fewo-calendar__legend" aria-label="<?= h(t('Legend')) ?>">
                <li><span class="fewo-day fewo-day--am-free fewo-day--pm-free" aria-hidden="true"></span> <?= t('Free') ?></li>
                <li><span class="fewo-day fewo-day--am-booked fewo-day--pm-booked" aria-hidden="true"></span> <?= t('Booked') ?></li>
                <li><span class="fewo-day fewo-day--am-free fewo-day--pm-booked" aria-hidden="true"></span> <?= t('Arrival/departure day') ?></li>
                <?php if ($hasRequestStatus) { ?>
                    <li><span class="fewo-day fewo-day--am-request fewo-day--pm-request" aria-hidden="true"></span> <?= t('On request') ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    <?php } ?>
</div>
