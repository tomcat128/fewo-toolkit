<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Pricing\PriceTable;
use FewoTk2\Support\Money;

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var array<int, array{apartment: FewoTk2\Entity\Apartment, loaded: ?FewoTk2\Pricing\LoadedPricelist}> $rows
 */

$token = app('token');
$dateService = app('date');
$apartmentsUrl = (string) Concrete\Core\Support\Facade\Url::to('/dashboard/fewo_tk2/apartments');
$guestLabel = static fn (int $guests): string => t2('%s guest', '%s guests', $guests);
?>
<?php if ($rows !== []) { ?>
    <div class="ccm-dashboard-header-buttons">
        <form method="post" action="<?= h($view->action('refresh')) ?>">
            <?php $token->output('fewo_prices_refresh') ?>
            <button type="submit" class="btn btn-primary"><?= t('Update now') ?></button>
        </form>
    </div>
<?php } ?>

<p class="text-muted">
    <?= t('The prices as Fewo-Office delivers them – the price overview, the price calculator and the AI assistant work with exactly these figures. Prices are maintained in Fewo-Office; "Update now" fetches the current state immediately.') ?>
</p>

<?php if ($rows === []) { ?>
    <div class="alert alert-info"><?= t('There are no apartments yet.') ?> <a href="<?= h($apartmentsUrl) ?>"><?= t('Add apartment') ?></a></div>
<?php } ?>

<?php foreach ($rows as $row) {
    $apartment = $row['apartment'];
    $loaded = $row['loaded'];
    $pricelist = $loaded?->pricelist;
    $feed = $loaded?->feed;
    $currency = $pricelist?->currency ?? 'EUR';
    $seasons = $pricelist?->getSeasons() ?? [];
    // Spalten: jede Gästezahl bis zur Höchstbelegung – auch die, für die der Preis der nächsthöheren gilt
    $maxGuests = $pricelist !== null ? max($pricelist->maxGuests, $pricelist->defaultPrices->getMaxGuests(), ...array_map(static fn ($season) => $season->prices->getMaxGuests(), $seasons)) : 0;
    $priceCells = static function (PriceTable $prices) use ($maxGuests, $currency): string {
        $html = '';
        for ($guests = 1; $guests <= $maxGuests; $guests++) {
            $cents = $guests <= $prices->getMaxGuests() ? $prices->forGuests($guests) : null;
            $html .= '<td class="text-end text-nowrap">' . ($cents !== null ? h(Money::format($cents, $currency)) : '<span class="text-muted">–</span>') . '</td>';
        }

        return $html . '<td class="text-end text-nowrap">' . ($prices->getMinOccupancy() > 0 ? h(t2('%s guest', '%s guests', $prices->getMinOccupancy())) : '<span class="text-muted">–</span>') . '</td>';
    };
    ?>
    <section class="card mb-4 fewo-prices-card" data-apartment="<?= h($apartment->getName()) ?>">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h2 class="h5 mb-0 me-auto">
                <?= h($apartment->getName()) ?>
                <?php if ($pricelist !== null && $pricelist->title !== '' && $pricelist->title !== $apartment->getName()) { ?>
                    <small class="text-muted">– <?= h($pricelist->title) ?></small>
                <?php } ?>
            </h2>
            <?php if ($feed !== null && $feed->getFetchedAt() !== null) { ?>
                <span class="small text-muted"><?= h(t('As of %s', $dateService->formatDateTime($feed->getFetchedAt()))) ?></span>
            <?php } ?>
            <?php if ($apartment->getPricelistUrl() !== '') { ?>
                <form method="post" action="<?= h($view->action('refresh', $apartment->getId())) ?>">
                    <?php $token->output('fewo_prices_refresh') ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= t('Update now') ?></button>
                </form>
            <?php } ?>
        </div>
        <div class="card-body">
            <?php if ($apartment->getPricelistUrl() === '') { ?>
                <p class="mb-0 text-muted"><?= t('No price list URL is stored for this apartment.') ?>
                    <a href="<?= h($apartmentsUrl . '/edit/' . $apartment->getId()) ?>"><?= t('Edit') ?></a></p>
            <?php } else { ?>
                <?php if ($feed !== null && $feed->getLastError()) { ?>
                    <div class="alert alert-warning">
                        <strong><?= t('last update failed') ?>:</strong> <?= h($feed->getLastError()) ?>
                        <?= $pricelist !== null ? h(t('The prices below are the last state that could be loaded.')) : '' ?>
                    </div>
                <?php } ?>
                <?php if ($pricelist === null) { ?>
                    <p class="mb-0 text-danger"><?= h($loaded?->error ?? t('The price list could not be loaded.')) ?></p>
                <?php } else { ?>
                    <dl class="row mb-3 fewo-prices-conditions">
                        <dt class="col-sm-4 col-xl-3"><?= t('Minimum stay') ?></dt>
                        <dd class="col-sm-8 col-xl-9" data-fewo-condition="minNights"><?= t2('%s night', '%s nights', $pricelist->minNights) ?></dd>
                        <dt class="col-sm-4 col-xl-3"><?= t('Final cleaning') ?></dt>
                        <dd class="col-sm-8 col-xl-9" data-fewo-condition="cleaning"><?= $pricelist->cleaningFee > 0 ? h(t('%s (once per stay)', Money::format($pricelist->cleaningFee, $currency))) : h(t('none')) ?></dd>
                        <dt class="col-sm-4 col-xl-3"><?= t('Maximum occupancy') ?></dt>
                        <dd class="col-sm-8 col-xl-9" data-fewo-condition="maxGuests"><?= h($guestLabel($pricelist->maxGuests)) ?></dd>
                        <dt class="col-sm-4 col-xl-3"><?= t('Down payment') ?></dt>
                        <dd class="col-sm-8 col-xl-9 mb-0" data-fewo-condition="downPayment"><?= $pricelist->downPaymentPercent > 0 ? h(t('%s %% of the total price', Money::percent($pricelist->downPaymentPercent))) : h(t('none')) ?></dd>
                    </dl>

                    <?php if (!$pricelist->hasPrices()) { ?>
                        <p class="mb-0 text-muted"><?= t('Fewo-Office does not deliver any current prices for this apartment.') ?></p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-2">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= t('Season') ?></th>
                                        <th scope="col"><?= t('Period') ?></th>
                                        <?php for ($guests = 1; $guests <= $maxGuests; $guests++) { ?>
                                            <th scope="col" class="text-end text-nowrap"><?= h($guestLabel($guests)) ?></th>
                                        <?php } ?>
                                        <th scope="col" class="text-end text-nowrap"><?= t('Minimum occupancy') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seasons as $season) { ?>
                                        <tr>
                                            <th scope="row" class="fw-normal"><?= h($season->name !== '' ? $season->name : t('Season')) ?></th>
                                            <td class="text-nowrap"><?= $season->dateFrom->format('d.m.Y') ?> – <?= $season->dateTo->format('d.m.Y') ?></td>
                                            <?= $priceCells($season->prices) ?>
                                        </tr>
                                    <?php } ?>
                                    <?php if (!$pricelist->defaultPrices->isEmpty()) { ?>
                                        <tr>
                                            <th scope="row" class="fw-normal"><?= t('Other periods') ?></th>
                                            <td class="text-muted"><?= t('standard price') ?></td>
                                            <?= $priceCells($pricelist->defaultPrices) ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-0"><?= t('Prices per night. Minimum occupancy: up to this number of guests the lowest price applies – fewer guests pay the same. A night costs the price of the season in which it ends.') ?></p>
                    <?php } ?>
                <?php } ?>
            <?php } ?>
        </div>
    </section>
<?php } ?>
