<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-kontaktformular input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-kontaktformular">
    <div class="alert-message block-message info">
        <?php echo "Bitte lokale Einstellungen für das Kontaktformular eingeben.";?>
    </div>
    
    <br />
    
    <?php echo $form->label("formular_name", "Name");?>
    <?php echo $form->text("formular_name", $formular_name);?>
    
    <br />
    
    <?php echo $form->label("email_empfaenger", "Empfänger");?>
    <?php echo $form->text("email_empfaenger", $email_empfaenger);?>
    
    <br />
    
    <?php echo $form->label("captcha", "Captcha-Code");?>
    <?php echo $form->select("captcha", array("N" => "Nein", "Y" => "Ja"), $captcha);?>
    
    <br />
    
    <?php echo $form->label("honeypot", "Verwende `Honeypot´");?>
    <?php echo $form->select("honeypot", array("N" => "Nein", "Y" => "Ja"), $honeypot);?>
    
    <br />
    
    <?php echo $form->label("pricelist", "Verwende Preislisten");?>
    <?php echo $form->select("pricelist", array("N" => "Nein", "Y" => "Ja"), $pricelist);?>
    
    <br />
    
    <?php echo $form->label("absender_cc", "Absender in CC");?>
    <?php echo $form->select("absender_cc", array("N" => "Nein", "Y" => "Ja"), $absender_cc);?>
    
    <br />

    <?php echo $form->label("smtp_host", "SMTP Host[:Port]");?>
    <?php echo $form->text("smtp_host", $smtp_host);?>
    
    <br />

    <?php echo $form->label("smtp_security", "SMTP Verschlüsselung");?>
    <?php echo $form->select("smtp_security", array("" => "Ohne Verschlüsselung", "SSL" => "SSL", "STARTTLS" => "STARTTLS"), $smtp_security);?>
    
    <br />

    <?php echo $form->label("smtp_username", "SMTP Benutzer");?>
    <?php echo $form->text("smtp_username", $smtp_username);?>
    
    <br />

    <?php echo $form->label("smtp_password", "SMTP Passwort");?>
    <?php echo $form->text("smtp_password", $smtp_password);?>
    
    <br />

</div>