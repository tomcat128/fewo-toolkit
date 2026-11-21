<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Inquiry\InquiryMailer;
use FewoTk2\Support\Money;

/**
 * Benachrichtigung an den Vermieter über eine neue Anfrage.
 *
 * @var FewoTk2\Inquiry\Inquiry $inquiry
 * @var FewoTk2\Pricing\Quote|null $quote
 * @var string $siteName
 */

$rows = [
    t('Form') => $inquiry->getFormName(),
    t('Accommodation') => $inquiry->getAccommodation(),
    t('Arrival') => $inquiry->getArrival()->format('d.m.Y'),
    t('Departure') => $inquiry->getDeparture()->format('d.m.Y'),
    t('Nights') => (string) $inquiry->getNights(),
    t('Adults') => (string) $inquiry->getAdults(),
    t('Children') => (string) $inquiry->getChildren(),
    t('Non-binding price') => $quote ? Money::format($quote->getTotal(), $quote->currency, null, false) : '',
    t('Name') => trim($inquiry->getSalutation() . ' ' . $inquiry->getFullName()),
    t('Email') => $inquiry->getEmail(),
    t('Phone') => $inquiry->getPhone(),
    t('Address') => trim(implode(', ', array_filter([
        $inquiry->getStreet(),
        trim($inquiry->getPostalCode() . ' ' . $inquiry->getCity()),
        $inquiry->getCountry(),
    ]))),
    t('How did you find us?') => $inquiry->getReferralSource(),
];
$rows = array_filter($rows, static fn ($value) => $value !== '');

$subject = t('New inquiry') . ($inquiry->getFormName() !== '' ? ' (' . $inquiry->getFormName() . ')' : '')
    . ': ' . $inquiry->getArrival()->format('d.m.') . '–' . $inquiry->getDeparture()->format('d.m.Y')
    . ', ' . $inquiry->getFullName();

// Klartext
$body = t('New inquiry via %s', $siteName) . "\n\n";
$body .= InquiryMailer::textRows($rows);
if ($inquiry->getMessage() !== '') {
    $body .= "\n" . t('Message') . ":\n" . $inquiry->getMessage() . "\n";
}
$body .= "\n" . t('Simply reply to this email to answer the guest.') . "\n";

// HTML
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<!DOCTYPE html>
<html lang="<?= $e(substr(Concrete\Core\Localization\Localization::activeLocale(), 0, 2)) ?>">
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #222; line-height: 1.5;">
<h2 style="font-size: 18px; margin: 0 0 16px;"><?= $e(t('New inquiry via %s', $siteName)) ?></h2>
<table cellpadding="6" cellspacing="0" style="border-collapse: collapse; min-width: 320px;">
    <?php foreach ($rows as $label => $value) { ?>
        <tr>
            <td style="border-bottom: 1px solid #eee; color: #666; vertical-align: top; white-space: nowrap;"><?= $e($label) ?></td>
            <td style="border-bottom: 1px solid #eee;">
                <?php if ($label === t('Email')) { ?>
                    <a href="mailto:<?= $e($value) ?>"><?= $e($value) ?></a>
                <?php } else { ?>
                    <?= $e($value) ?>
                <?php } ?>
            </td>
        </tr>
    <?php } ?>
</table>
<?php if ($inquiry->getMessage() !== '') { ?>
    <h3 style="font-size: 15px; margin: 20px 0 6px;"><?= $e(t('Message')) ?></h3>
    <p style="margin: 0;"><?= nl2br($e($inquiry->getMessage())) ?></p>
<?php } ?>
<p style="margin-top: 24px; color: #666; font-size: 13px;"><?= $e(t('Simply reply to this email to answer the guest.')) ?></p>
</body>
</html>
<?php
$bodyHTML = ob_get_clean();
