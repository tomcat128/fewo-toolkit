<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $title
 * @var bool $showPeriod
 * @var FewoTk2\News\NewsItem[] $items
 * @var array<int, array{code: string, label: string, color: string}> $levels
 * @var FewoTk2\Entity\RemoteFeed|null $feed
 * @var string|null $error
 * @var bool $canEdit
 */

$page = Concrete\Core\Page\Page::getCurrentPage();
$isEditMode = $page && !$page->isError() && $page->isEditMode();

if ($items === []) {
    // Keine aktuelle Neuigkeit: Besucher sehen nichts, Redakteure den Grund
    if ($canEdit && ($error !== null || $feed?->getLastError())) { ?>
        <div class="fewo-news">
            <?php Concrete\Core\View\View::element('feed_notice', ['label' => t('News'), 'error' => $error, 'feed' => $feed], 'fewo_tk2') ?>
        </div>
    <?php } elseif ($isEditMode) { ?>
        <div class="ccm-edit-mode-disabled-item"><?= t('News: there is no current news at the moment. The block is only visible while there is.') ?></div>
    <?php }

    return;
}

$itemHeading = $title !== '' ? 'h3' : 'h2';
$period = static function (FewoTk2\News\NewsItem $item): string {
    $from = '<time datetime="' . $item->dateFrom->format('Y-m-d') . '">' . $item->dateFrom->format('d.m.Y') . '</time>';
    if ($item->dateFrom->format('Y-m-d') === $item->dateTo->format('Y-m-d')) {
        return t('Valid on %s', $from);
    }

    return t('Valid from %1$s to %2$s', $from, '<time datetime="' . $item->dateTo->format('Y-m-d') . '">' . $item->dateTo->format('d.m.Y') . '</time>');
};
?>
<section class="fewo-news"<?= $title !== '' ? '' : ' aria-label="' . h(t('News')) . '"' ?>>
    <?php if ($title !== '') { ?>
        <h2 class="fewo-news__title"><?= h($title) ?></h2>
    <?php } ?>

    <?php if ($canEdit) {
        Concrete\Core\View\View::element('feed_notice', ['label' => t('News'), 'error' => $error, 'feed' => $feed], 'fewo_tk2');
    } ?>

    <?php foreach ($items as $item) {
        // Farben der Stufe aus dem Block; Meldungen mit unbekanntem Code neutral und ohne Bezeichnung
        $level = FewoTk2\News\ImportanceLevels::find($levels, $item->importance);
        $colors = FewoTk2\News\ImportanceLevels::colors($level['color'] ?? FewoTk2\News\ImportanceLevels::NEUTRAL_COLOR);
        ?>
        <article class="fewo-news__item fewo-news__item--<?= h($item->importance) ?>" data-fewo-importance="<?= h($item->importance) ?>"
                 style="--fewo-news-level: <?= h($colors['color']) ?>; --fewo-news-level-text: <?= h($colors['text']) ?>; --fewo-news-level-bg: <?= h($colors['background']) ?>">
            <?php // Die Stufe steht auch als Text da: Die Farbe allein erreicht nicht alle Besucher ?>
            <?php if (($level['label'] ?? '') !== '') { ?>
                <p class="fewo-news__importance"><?= h($level['label']) ?></p>
            <?php } ?>
            <?php if ($item->title !== '') { ?>
                <<?= $itemHeading ?> class="fewo-news__item-title"><?= h($item->title) ?></<?= $itemHeading ?>>
            <?php } ?>
            <?php if ($showPeriod) { ?>
                <p class="fewo-news__period"><?= $period($item) ?></p>
            <?php } ?>
            <?php foreach ($item->paragraphs() as $paragraph) { ?>
                <p class="fewo-news__text"><?= nl2br(h($paragraph), false) ?></p>
            <?php } ?>
        </article>
    <?php } ?>
</section>
