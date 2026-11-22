<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var array<int, array{apartment: FewoTk2\Entity\Apartment, occupancyFeed: ?FewoTk2\Entity\RemoteFeed, pricelistFeed: ?FewoTk2\Entity\RemoteFeed, newsFeed: ?FewoTk2\Entity\RemoteFeed, seasonCount: ?int, newsCount: ?int}> $rows
 */

$token = app('token');
$dateService = app('date');

/**
 * Stand des letzten Abrufs einer Adresse.
 */
$feedStatus = static function (string $url, ?FewoTk2\Entity\RemoteFeed $feed, ?string $detail = null) use ($dateService): string {
    if ($url === '') {
        return '<span class="text-muted">' . h(t('not stored')) . '</span>';
    }
    if ($feed === null || $feed->getFetchedAt() === null) {
        $html = '<span class="badge bg-danger">' . h(t('not loaded yet')) . '</span>';
    } elseif ($feed->getLastError()) {
        $html = '<span class="badge bg-warning text-dark">' . h(t('last update failed')) . '</span>';
    } else {
        $html = '<span class="badge bg-success">' . h(t('OK')) . '</span>';
    }
    if ($detail !== null) {
        $html .= ' ' . h($detail);
    }
    if ($feed !== null && $feed->getFetchedAt() !== null) {
        $html .= '<div class="small text-muted">' . h(t('As of %s', $dateService->formatDateTime($feed->getFetchedAt()))) . '</div>';
    }
    if ($feed !== null && $feed->getLastError()) {
        $html .= '<div class="small text-danger">' . h($feed->getLastError()) . '</div>';
    }

    return $html . '<div class="small text-muted text-break">' . h($url) . '</div>';
};
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($view->action('add')) ?>" class="btn btn-primary"><?= t('Add apartment') ?></a>
</div>

<p class="text-muted">
    <?= t('Occupancy, prices and news are not maintained on the website, they always come from Fewo-Office. Store the Fewo-Office URLs of each apartment here once; the blocks only select the apartment.') ?>
</p>

<?php if ($rows === []) { ?>
    <div class="alert alert-info"><?= t('There are no apartments yet. Add an apartment to use the occupancy calendar, the price overview, the price calculator and the news.') ?></div>
<?php } else { ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle" id="fewo-apartments">
            <thead>
                <tr>
                    <th><?= t('Apartment') ?></th>
                    <th><?= t('Occupancy') ?></th>
                    <th><?= t('Price list') ?></th>
                    <th><?= t('News') ?></th>
                    <th><?= t('Update interval') ?></th>
                    <th class="text-end"><?= t('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) {
                    $apartment = $row['apartment'];
                    $minutes = $apartment->getRefreshMinutes();
                    ?>
                    <tr>
                        <td><a href="<?= h($view->action('edit', $apartment->getId())) ?>"><strong><?= h($apartment->getName()) ?></strong></a></td>
                        <td><?= $feedStatus($apartment->getOccupancyUrl(), $row['occupancyFeed']) ?></td>
                        <td><?= $feedStatus(
                            $apartment->getPricelistUrl(),
                            $row['pricelistFeed'],
                            $row['seasonCount'] !== null ? t2('%s season', '%s seasons', $row['seasonCount']) : null
                        ) ?></td>
                        <td><?= $feedStatus(
                            $apartment->getNewsUrl(),
                            $row['newsFeed'],
                            $row['newsCount'] !== null ? t2('%s current news item', '%s current news items', $row['newsCount']) : null
                        ) ?></td>
                        <td class="text-nowrap"><?= $minutes < 60 ? t2('%s minute', '%s minutes', $minutes) : t2('%s hour', '%s hours', intdiv($minutes, 60)) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= h($view->action('edit', $apartment->getId())) ?>" class="btn btn-sm btn-secondary"><?= t('Edit') ?></a>
                            <form method="post" action="<?= h($view->action('refresh', $apartment->getId())) ?>" class="d-inline">
                                <?php $token->output('fewo_apartment_refresh') ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= t('Update now') ?></button>
                            </form>
                            <form method="post" action="<?= h($view->action('delete', $apartment->getId())) ?>" class="d-inline"
                                  onsubmit="return confirm(<?= h(json_encode(t('Delete the apartment "%s"? Blocks using it will no longer show any data.', $apartment->getName()))) ?>)">
                                <?php $token->output('fewo_apartment_delete') ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= t('Delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>
