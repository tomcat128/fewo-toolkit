<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-preisrechner input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-preisrechner">
    <div class="alert-message block-message info">
        <?php echo "Bitte eine Preisliste und Berechnungsmethode auswählen.";?>
    </div>
    <br />

    <?php echo $form->radio("type", "IN", ($type == "IN" ? "IN" : ""));?>
    <?php echo $form->label("Typ", "Interne Berechnung");?>
    <br />
    
    <?php echo $form->label("preisliste", "Preisliste");?>
    <?php echo $form->select("preisliste", $oPreisliste, $preisliste);?>
    <br />

    <?php echo $form->label("modus", "Modus"." ("."Stichtag der Berechnung".")");?>
    <?php echo $form->select("modus", $oModus, $modus);?>
    <br />
    
    <?php echo $form->radio("type", "EX", ($type == "EX" ? "EX" : ""));?>
    <?php echo $form->label("Typ", "Externe Berechnung");?>
    <br />

    <?php echo $form->label("url", "URL"." (extern)");?>
    <?php echo $form->text("url_extern", $url_extern);?>
    <br />
    
</div>