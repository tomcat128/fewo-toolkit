<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-code input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-code">
    <?php echo $form->label("php_code", t("PHP-Code"));?>
    <br />
    &lt;?php    
    <?php echo $form->textarea("php_code", $php_code, array("rows" => 7));?>
    ?&gt;
</div>