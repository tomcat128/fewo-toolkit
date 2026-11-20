<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoToolkit\Support\Money;

/**
 * @var FewoToolkit\Entity\Pricelist|null $pricelist
 * @var string $title
 * @var bool $showConditions
 */

$page = Concrete\Core\Page\Page::getCurrentPage();
$isEditMode = $page && $page->isEditMode();

if ($pricelist === null) {
    if ($isEditMode) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('Price overview: please select a price list.') ?></div>
    <?php }

    return;
}

$seasons = $pricelist->getCurrentSeasons();
$currency = $pricelist->getCurrency();
$guestsLabel = static function (int $min, int $max): string {
    if ($min === $max) {
        return t2('%s guest', '%s guests', $max);
    }

    return t('%1$s–%2$s guests', $min, $max);
};
?>
<div class="fewo-prices">
    <?php if ($title !== '') { ?>
        <h2 class="fewo-prices__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($seasons === []) { ?>
        <p class="fewo-prices__empty"><?= t('Prices on request.') ?></p>
    <?php } else { ?>
        <div class="fewo-prices__seasons">
            <?php foreach ($seasons as $season) { ?>
                <article class="fewo-prices__season">
                    <header class="fewo-prices__season-header">
                        <h3 class="fewo-prices__season-name"><?= h($season->getName()) ?></h3>
                        <p class="fewo-prices__season-dates">
                            <time datetime="<?= $season->getDateFrom()->format('Y-m-d') ?>"><?= $season->getDateFrom()->format('d.m.Y') ?></time>
                            –
                            <time datetime="<?= $season->getDateTo()->format('Y-m-d') ?>"><?= $season->getDateTo()->format('d.m.Y') ?></time>
                        </p>
                    </header>
                    <dl class="fewo-prices__rates">
                        <?php foreach ($season->getPriceRanges() as $range) { ?>
                            <div class="fewo-prices__rate">
                                <dt><?= h($guestsLabel($range['min'], $range['max'])) ?></dt>
                                <dd><?= h(Money::format($range['price'], $currency)) ?> <span class="fewo-prices__unit"><?= t('/ night') ?></span></dd>
                            </div>
                        <?php } ?>
                    </dl>
                    <?php if ($season->getMinNights() !== null && $season->getMinNights() > $pricelist->getMinNights()) { ?>
                        <p class="fewo-prices__season-note"><?= t2('Minimum stay %s night', 'Minimum stay %s nights', $season->getMinNights()) ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($showConditions) { ?>
        <ul class="fewo-prices__conditions">
            <?php if ($pricelist->getMinNights() > 1) { ?>
                <li><?= t2('Minimum stay: %s night', 'Minimum stay: %s nights', $pricelist->getMinNights()) ?></li>
            <?php } ?>
            <?php if ($pricelist->getCleaningFee() > 0) { ?>
                <li><?= t('Final cleaning: %s (once per stay)', h(Money::format($pricelist->getCleaningFee(), $currency))) ?></li>
            <?php } ?>
            <?php if ($pricelist->getSecurityDeposit() > 0) { ?>
                <li><?= t('Security deposit: %s (refunded after departure)', h(Money::format($pricelist->getSecurityDeposit(), $currency))) ?></li>
            <?php } ?>
            <?php if ($pricelist->getIncludedServices() !== '') { ?>
                <li><?= h($pricelist->getIncludedServices()) ?></li>
            <?php } ?>
        </ul>
        <?php if ($pricelist->getNote() !== '') { ?>
            <p class="fewo-prices__note"><?= nl2br(h($pricelist->getNote())) ?></p>
        <?php } ?>
    <?php } ?>
</div>
