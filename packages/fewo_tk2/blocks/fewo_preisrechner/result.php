<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Support\Money;

/**
 * Ergebnis der Preisberechnung (wird aus Controller::renderResult() eingebunden).
 *
 * @var FewoTk2\Pricing\Quote|null $quote
 * @var string|null $error
 * @var string|null $inquiryUrl
 */

if ($error !== null) { ?>
    <p class="fewo-quote__error" role="alert"><?= h($error) ?></p>
    <?php
    return;
}
if ($quote === null) {
    return;
}

$money = static fn (int $cents): string => h(Money::format($cents, $quote->currency, null, false));
?>
<div class="fewo-quote__summary">
    <p class="fewo-quote__total">
        <span class="fewo-quote__total-label"><?= t('Total price') ?></span>
        <strong class="fewo-quote__total-amount"><?= $money($quote->getTotal()) ?></strong>
    </p>
    <p class="fewo-quote__meta">
        <?= t2('%s night', '%s nights', $quote->getNights()) ?>
        · <?= t2('%s guest', '%s guests', $quote->guests) ?>
        · <?= $quote->arrival->format('d.m.Y') ?> – <?= $quote->departure->format('d.m.Y') ?>
    </p>
</div>

<details class="fewo-quote__details">
    <summary><?= t('Price details') ?></summary>
    <table class="fewo-quote__table">
        <tbody>
        <?php foreach ($quote->lines as $line) { ?>
            <tr>
                <th scope="row">
                    <?= h($line->season) ?>
                    <small><?= $line->from->format('d.m.') ?> – <?= $line->to->format('d.m.Y') ?></small>
                </th>
                <td><?= t2('%1$s night × %2$s', '%1$s nights × %2$s', $line->nights, $money($line->pricePerNight)) ?></td>
                <td class="fewo-quote__amount"><?= $money($line->getSubtotal()) ?></td>
            </tr>
        <?php } ?>
        <?php if ($quote->cleaningFee > 0) { ?>
            <tr>
                <th scope="row" colspan="2"><?= t('Final cleaning') ?></th>
                <td class="fewo-quote__amount"><?= $money($quote->cleaningFee) ?></td>
            </tr>
        <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="row" colspan="2"><?= t('Total price') ?></th>
                <td class="fewo-quote__amount"><?= $money($quote->getTotal()) ?></td>
            </tr>
            <?php if ($quote->getDownPayment() > 0) { ?>
                <tr class="fewo-quote__secondary">
                    <th scope="row" colspan="2"><?= t('Down payment (%s %%)', h(Money::percent($quote->downPaymentPercent))) ?></th>
                    <td class="fewo-quote__amount"><?= $money($quote->getDownPayment()) ?></td>
                </tr>
            <?php } ?>
        </tfoot>
    </table>
</details>

<p class="fewo-quote__disclaimer"><?= t('Non-binding calculation. Availability is confirmed with your booking inquiry.') ?></p>

<?php if ($inquiryUrl) { ?>
    <p class="fewo-quote__cta">
        <a class="btn btn-primary fewo-btn" href="<?= h($inquiryUrl) ?>"><?= t('Send booking inquiry') ?></a>
    </p>
<?php } ?>
