<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Hinweis zum Datenabruf aus Fewo-Office. Nur für Personen einbinden, die die Seite bearbeiten dürfen.
 *
 * @var string $label Name des Blocks, z. B. "Belegungsplan"
 * @var string|null $error
 * @var FewoTk2\Entity\RemoteFeed|null $feed
 */

$lastError = $feed !== null ? (string) $feed->getLastError() : '';

if ($error !== null) { ?>
    <div class="fewo-notice fewo-notice--error" role="alert">
        <strong><?= h($label) ?>:</strong> <?= h($error) ?>
        <?php if ($lastError !== '') { ?>
            <br><small><?= h($lastError) ?></small>
        <?php } ?>
    </div>
<?php } elseif ($lastError !== '') { ?>
    <div class="fewo-notice fewo-notice--warning" role="status">
        <strong><?= h($label) ?>:</strong>
        <?= t('The last update failed (%s). The data from %s is shown.', h($lastError), $feed->getFetchedAt() ? h(app('date')->formatDateTime($feed->getFetchedAt())) : '–') ?>
    </div>
<?php }
