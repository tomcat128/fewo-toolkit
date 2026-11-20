<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-bildergalerie input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-bildergalerie">
    <div class="alert-message block-message info">
        <?php echo "Bitte ein Dateialbum mit Bildern auswählen.";?>
    </div>
    <br />
    
    <?php echo $form->label("dateialbum", "Dateialbum auswählen:");?>
    <?php echo $form->select("dateialbum", $filesetOptions, $dateialbum);?>
    <br />
    
    <?php echo $form->label("info", "Die Bildergalerie besitzt die folgende CSS-Struktur:");?>
    <br />
    <?php echo "div.bildergalerie &gt; a.thumbnail";?>

    <br />
    <br />
    <em><?php echo "Das Theme sollte daher diese Styles implementieren.";?></em>

</div>