<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\FewoTk2\Block\FewoPreisrechner\Controller $controller
 * @var FewoTk2\Pricing\Pricelist|null $pricelist
 * @var FewoTk2\Entity\RemoteFeed|null $feed
 * @var string|null $loadError
 * @var bool $canEdit
 * @var DateTimeImmutable $today
 * @var DateTimeImmutable $maxDate
 * @var array<int, array{0: string, 1: string}>|null $bookedRanges belegte Zeiträume, null = keine Belegung bekannt
 * @var string $actionUrl
 * @var string $title
 * @var array $values
 * @var FewoTk2\Pricing\Quote|null $quote
 * @var string|null $error
 */

if ($pricelist === null) {
    // Besucher sehen keinen Rechner ohne Preisliste, Redakteure den Grund
    if ($canEdit) { ?>
        <div class="fewo-quote">
            <?php Concrete\Core\View\View::element('feed_notice', ['label' => t('Price calculator'), 'error' => $loadError, 'feed' => $feed], 'fewo_tk2') ?>
        </div>
    <?php }

    return;
}

$id = 'fewo-quote-' . (int) $bID;
$minDate = $today->format('Y-m-d');
$maxDate = $maxDate->format('Y-m-d');
$selectedGuests = (int) ($values['guests'] ?? 0) ?: min(2, $pricelist->maxGuests);
?>
<div class="fewo-quote" id="<?= $id ?>">
    <?php if ($title !== '') { ?>
        <h2 class="fewo-quote__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($canEdit) {
        Concrete\Core\View\View::element('feed_notice', ['label' => t('Price calculator'), 'error' => $loadError, 'feed' => $feed], 'fewo_tk2');
    } ?>

    <form class="fewo-quote__form" method="post" action="<?= h($actionUrl) ?>#<?= $id ?>" data-fewo-quote novalidate>
        <div class="fewo-quote__field">
            <label for="<?= $id ?>-arrival"><?= t('Arrival') ?></label>
            <input type="date" id="<?= $id ?>-arrival" name="arrival" required min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= h($values['arrival'] ?? '') ?>">
        </div>
        <div class="fewo-quote__field">
            <label for="<?= $id ?>-departure"><?= t('Departure') ?></label>
            <input type="date" id="<?= $id ?>-departure" name="departure" required min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= h($values['departure'] ?? '') ?>">
        </div>
        <div class="fewo-quote__field">
            <label for="<?= $id ?>-guests"><?= t('Guests') ?></label>
            <select id="<?= $id ?>-guests" name="guests" required>
                <?php for ($i = 1; $i <= $pricelist->maxGuests; $i++) { ?>
                    <option value="<?= $i ?>"<?= $i === $selectedGuests ? ' selected' : '' ?>><?= $i ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="fewo-quote__actions">
            <button type="submit" class="fewo-quote__submit btn btn-primary"><?= t('Calculate price') ?></button>
        </div>

        <?php if ($bookedRanges !== null) {
            // Kalender der Felder "Anreise" und "Abreise" mit der Belegung der Wohnung. Er wird per JavaScript
            // aufgebaut und öffnet sich unter dem angeklickten Feld; belegte Tage lassen sich nicht wählen.
            // Ohne JavaScript bleiben die Datumsfelder des Browsers; die Belegung prüft dann der Server.
            $calendar = [
                'booked' => $bookedRanges,
                'labels' => [
                    'heading' => t('Availability'),
                    'previous' => t('Previous month'),
                    'next' => t('Next month'),
                    'selectArrival' => t('Please select your arrival day.'),
                    'selectDeparture' => t('Please select your departure day.'),
                    'free' => t('free'),
                    'booked' => t('booked'),
                    'past' => t('in the past'),
                    'arrival' => t('Arrival'),
                    'departure' => t('Departure'),
                    'legendFree' => t('Free'),
                    'legendBooked' => t('Booked'),
                    'legendSelected' => t('Your stay'),
                    'unavailable' => t('The apartment is already booked in the selected period. Please choose different dates.'),
                ],
            ];
            ?>
            <div class="fewo-quote__calendar" data-fewo-quote-calendar="<?= h(json_encode($calendar, JSON_UNESCAPED_UNICODE)) ?>" hidden></div>
        <?php } ?>
    </form>

    <div class="fewo-quote__result" aria-live="polite" data-fewo-quote-result>
        <?php if ($quote !== null || $error !== null) {
            echo $controller->renderResult($quote, $error);
        } ?>
    </div>
</div>
