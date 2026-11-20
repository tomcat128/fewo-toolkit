<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-code input[type=text] {
    width: 100%;
}
.edit-code textarea.form-control {
    font-family: monospace;
    white-space: pre;
    font-size: 12px;
}
</style>
<div class="ccm-ui edit-code">
    <?php echo $form->label("images", t("Pannellum-Images"));?>
    <br />
    <?php echo $form->textarea("images", $images, array("rows" => 3));?>
    <br />
    <?php echo $form->label("images", t("Pannellum-Images for mobile devices"));?>
    <br />
    <?php echo $form->textarea("images_mobile", $images_mobile, array("rows" => 3));?>
    <br />
    <?php echo $form->label("viewer_code", t("Pannellum-Settings"));?>
    <br />
    <?php echo $form->textarea("viewer_code", $viewer_code, array("rows" => 9));?>
    <br />
    <?php echo $form->label("default_css_class", t("Default CSS-Class"));?>
    <br />
    <?php echo $form->text("default_css_class", !isset($default_css_class) ? "panorama" : $default_css_class) ;?>
    <br />
    <?php echo $form->label("aspect_ratio", t("Seitenverhältnis"));?>
    <br />
    <?php echo $form->select("aspect_ratio", array("Manual" => "Manuell (use default CSS class)", "Auto:4:3" => "Automatisch (4:3)", "Auto:3:2" => "Automatisch (3:2)", "Auto:16:9" => "Automatisch (16:9)"), $aspect_ratio);?>
</div>