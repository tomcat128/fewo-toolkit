<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Pricing\PriceTable;
use FewoTk2\Support\Money;

/**
 * @var FewoTk2\Pricing\Pricelist|null $pricelist
 * @var FewoTk2\Entity\RemoteFeed|null $feed
 * @var string|null $error
 * @var bool $canEdit
 * @var string $title
 * @var string $layout
 * @var bool $showConditions
 */

$currency = $pricelist?->currency ?? 'EUR';
$seasons = $pricelist?->getSeasons() ?? [];
$hasDefaultPrices = $pricelist !== null && !$pricelist->defaultPrices->isEmpty();

$conditions = [];
if ($showConditions && $pricelist !== null) {
    if ($pricelist->minNights > 1) {
        $conditions[] = t2('Minimum stay: %s night', 'Minimum stay: %s nights', $pricelist->minNights);
    }
    if ($pricelist->cleaningFee > 0) {
        $conditions[] = t('Final cleaning: %s (once per stay)', Money::format($pricelist->cleaningFee, $currency));
    }
    if ($pricelist->downPaymentPercent > 0) {
        $conditions[] = t('Down payment: %s %% of the total price', Money::percent($pricelist->downPaymentPercent));
    }
}

$guestsLabel = static function (int $min, int $max): string {
    if ($min === $max) {
        return t2('%s guest', '%s guests', $max);
    }

    return t('%1$s–%2$s guests', $min, $max);
};
$renderRates = static function (PriceTable $prices) use ($guestsLabel, $currency): void { ?>
    <dl class="fewo-prices__rates">
        <?php foreach ($prices->ranges() as $range) { ?>
            <div class="fewo-prices__rate">
                <dt><?= h($guestsLabel($range['min'], $range['max'])) ?></dt>
                <dd><?= h(Money::format($range['price'], $currency)) ?> <span class="fewo-prices__unit"><?= t('/ night') ?></span></dd>
            </div>
        <?php } ?>
    </dl>
<?php };
?>
<div class="fewo-prices fewo-prices--<?= h($layout) ?>">
    <?php if ($title !== '') { ?>
        <h2 class="fewo-prices__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($canEdit) {
        Concrete\Core\View\View::element('feed_notice', ['label' => t('Price overview'), 'error' => $error, 'feed' => $feed], 'fewo_tk2');
    } ?>

    <?php if ($pricelist === null || !$pricelist->hasPrices()) { ?>
        <p class="fewo-prices__empty"><?= t('Prices on request.') ?></p>
    <?php } else { ?>
        <div class="fewo-prices__seasons">
            <?php foreach ($seasons as $season) { ?>
                <article class="fewo-prices__season">
                    <header class="fewo-prices__season-header">
                        <h3 class="fewo-prices__season-name"><?= h($season->name) ?></h3>
                        <p class="fewo-prices__season-dates">
                            <time datetime="<?= $season->dateFrom->format('Y-m-d') ?>"><?= $season->dateFrom->format('d.m.Y') ?></time>
                            –
                            <time datetime="<?= $season->dateTo->format('Y-m-d') ?>"><?= $season->dateTo->format('d.m.Y') ?></time>
                        </p>
                    </header>
                    <?php $renderRates($season->prices) ?>
                </article>
            <?php } ?>
            <?php if ($hasDefaultPrices) { ?>
                <article class="fewo-prices__season fewo-prices__season--default">
                    <header class="fewo-prices__season-header">
                        <h3 class="fewo-prices__season-name"><?= $seasons === [] ? t('Price per night') : t('Other periods') ?></h3>
                    </header>
                    <?php $renderRates($pricelist->defaultPrices) ?>
                </article>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($conditions !== []) { ?>
        <ul class="fewo-prices__conditions">
            <?php foreach ($conditions as $condition) { ?>
                <li><?= h($condition) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>
