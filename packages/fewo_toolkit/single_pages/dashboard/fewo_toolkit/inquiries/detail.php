<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoToolkit\Entity\Inquiry;
use FewoToolkit\Support\Money;

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var FewoToolkit\Entity\Inquiry $inquiry
 */

$token = app('token');
$dateService = app('date');
$address = array_filter([
    $inquiry->getStreet(),
    trim($inquiry->getPostalCode() . ' ' . $inquiry->getCity()),
    $inquiry->getCountry(),
]);
$replySubject = t('Your inquiry from %1$s to %2$s', $inquiry->getArrival()->format('d.m.Y'), $inquiry->getDeparture()->format('d.m.Y'));
$rows = [
    t('Received') => h($dateService->formatDateTime($inquiry->getCreatedAt(), true)),
    t('Form') => h($inquiry->getFormName()),
    t('Accommodation') => h($inquiry->getAccommodation()),
    t('Travel dates') => $inquiry->getArrival()->format('d.m.Y') . ' – ' . $inquiry->getDeparture()->format('d.m.Y') . ' (' . t2('%s night', '%s nights', $inquiry->getNights()) . ')',
    t('Guests') => t('%1$s adults, %2$s children', $inquiry->getAdults(), $inquiry->getChildren()),
    t('Non-binding price') => $inquiry->getQuotedTotal() !== null ? h(Money::format($inquiry->getQuotedTotal(), $inquiry->getCurrency(), null, false)) : '',
    t('Name') => h(trim($inquiry->getSalutation() . ' ' . $inquiry->getFullName())),
    t('Email') => '<a href="mailto:' . h($inquiry->getEmail()) . '?subject=' . rawurlencode($replySubject) . '">' . h($inquiry->getEmail()) . '</a>',
    t('Phone') => $inquiry->getPhone() !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $inquiry->getPhone())) . '">' . h($inquiry->getPhone()) . '</a>' : '',
    t('Address') => h(implode(', ', $address)),
    t('How did you find us?') => h($inquiry->getReferralSource()),
];
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($view->action('view')) ?>" class="btn btn-secondary"><?= t('Back to list') ?></a>
    <a href="mailto:<?= h($inquiry->getEmail()) ?>?subject=<?= rawurlencode($replySubject) ?>" class="btn btn-primary"><?= t('Reply by email') ?></a>
</div>

<div class="row">
    <div class="col-lg-8">
        <table class="table">
            <tbody>
                <?php foreach ($rows as $label => $value) {
                    if ($value === '') {
                        continue;
                    } ?>
                    <tr>
                        <th scope="row" class="text-nowrap" style="width: 14rem"><?= h($label) ?></th>
                        <td><?= $value ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php if ($inquiry->getMessage() !== '') { ?>
            <h4><?= t('Message') ?></h4>
            <div class="card card-body bg-light" style="white-space: pre-wrap"><?= h($inquiry->getMessage()) ?></div>
        <?php } ?>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?= t('Status') ?></h5>
                <form method="post" action="<?= h($view->action('set_status', $inquiry->getId())) ?>" class="mb-3">
                    <?php $token->output('fewo_inquiry_status') ?>
                    <?php if ($inquiry->isNew()) { ?>
                        <input type="hidden" name="status" value="<?= Inquiry::STATUS_DONE ?>">
                        <button type="submit" class="btn btn-success w-100"><?= t('Mark as done') ?></button>
                    <?php } else { ?>
                        <input type="hidden" name="status" value="<?= Inquiry::STATUS_NEW ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100"><?= t('Mark as new') ?></button>
                    <?php } ?>
                </form>
                <form method="post" action="<?= h($view->action('delete', $inquiry->getId())) ?>"
                      onsubmit="return confirm(<?= h(json_encode(t('Delete this inquiry permanently?'))) ?>)">
                    <?php $token->output('fewo_inquiry_delete') ?>
                    <button type="submit" class="btn btn-outline-danger w-100"><?= t('Delete inquiry') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
