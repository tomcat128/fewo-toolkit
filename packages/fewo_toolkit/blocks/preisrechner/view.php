<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\FewoToolkit\Block\Preisrechner\Controller $controller
 * @var FewoToolkit\Entity\Pricelist|null $pricelist
 * @var DateTimeImmutable $today
 * @var string $actionUrl
 * @var string $title
 * @var array $values
 * @var FewoToolkit\Pricing\Quote|null $quote
 * @var string|null $error
 */

$page = Concrete\Core\Page\Page::getCurrentPage();
if ($pricelist === null) {
    if ($page && $page->isEditMode()) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('Price calculator: please select a price list.') ?></div>
    <?php }

    return;
}

$id = 'fewo-quote-' . (int) $bID;
$minDate = $today->format('Y-m-d');
$maxDate = $today->modify('+2 years')->format('Y-m-d');
$selectedGuests = (int) ($values['guests'] ?? 0) ?: min(2, $pricelist->getMaxGuests());
?>
<div class="fewo-quote" id="<?= $id ?>">
    <?php if ($title !== '') { ?>
        <h2 class="fewo-quote__title"><?= h($title) ?></h2>
    <?php } ?>

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
                <?php for ($i = 1; $i <= $pricelist->getMaxGuests(); $i++) { ?>
                    <option value="<?= $i ?>"<?= $i === $selectedGuests ? ' selected' : '' ?>><?= $i ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="fewo-quote__actions">
            <button type="submit" class="fewo-quote__submit btn btn-primary"><?= t('Calculate price') ?></button>
        </div>
    </form>

    <div class="fewo-quote__result" aria-live="polite" data-fewo-quote-result>
        <?php if ($quote !== null || $error !== null) {
            echo $controller->renderResult($quote, $error);
        } ?>
    </div>
</div>
