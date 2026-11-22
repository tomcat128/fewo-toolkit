<?php

defined('C5_EXECUTE') or die('Access Denied.');

use FewoTk2\Inquiry\InquiryMailer;

/**
 * Eingangsbestätigung an den Gast.
 *
 * Enthält bewusst keine frei eingegebenen Texte (Name, Nachricht), damit das
 * Formular nicht zum Versand von Spam an fremde Adressen missbraucht werden kann.
 *
 * @var FewoTk2\Inquiry\Inquiry $inquiry
 * @var string $siteName
 */

$rows = array_filter([
    t('Accommodation') => $inquiry->getAccommodation(),
    t('Arrival') => $inquiry->getArrival()->format('d.m.Y'),
    t('Departure') => $inquiry->getDeparture()->format('d.m.Y'),
    t('Guests') => (string) $inquiry->getGuests(),
], static fn ($value) => $value !== '');

$subject = t('Your inquiry at %s', $siteName);

$intro = t('Thank you for your inquiry. We have received the following details and will get back to you as soon as possible:');
$outro = t('This is an automatically generated confirmation. Availability is only confirmed by our reply.');

$body = t('Hello,') . "\n\n" . $intro . "\n\n";
$body .= InquiryMailer::textRows($rows);
$body .= "\n" . $outro . "\n\n" . t('Kind regards') . "\n" . $siteName . "\n";

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<!DOCTYPE html>
<html lang="<?= $e(substr(Concrete\Core\Localization\Localization::activeLocale(), 0, 2)) ?>">
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #222; line-height: 1.5;">
<p><?= $e(t('Hello,')) ?></p>
<p><?= $e($intro) ?></p>
<table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
    <?php foreach ($rows as $label => $value) { ?>
        <tr>
            <td style="border-bottom: 1px solid #eee; color: #666;"><?= $e($label) ?></td>
            <td style="border-bottom: 1px solid #eee;"><?= $e($value) ?></td>
        </tr>
    <?php } ?>
</table>
<p style="margin-top: 20px;"><?= $e($outro) ?></p>
<p><?= $e(t('Kind regards')) ?><br><?= $e($siteName) ?></p>
</body>
</html>
<?php
$bodyHTML = ob_get_clean();
