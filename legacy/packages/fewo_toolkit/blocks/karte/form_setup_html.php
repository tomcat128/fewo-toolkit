<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-kontaktformular input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-kontaktformular">
    
    <?php echo $form->label("js_points", "JS Koordinaten");?>
    <?php echo $form->textarea("js_points", $js_points, array("rows" => 2));?>
    
    <br />

    <?php echo $form->label("js_init", "JS Init");?>
    <?php echo $form->textarea("js_init", $js_init, array("rows" => 2));?>
    
    <br />

    <?php echo $form->label("html_controls", "HTML Controller");?>
    <?php echo $form->textarea("html_controls", $html_controls, array("rows" => 2));?>
    
    <br />

</div>

<script type="text/javascript">
    
    $("textarea").on("focus", function() {
        // $(this).prop("rows", 5);
        $(this).animate({"height": "155px"}, 500);
    });

    $("textarea").on("blur", function() {
        // $(this).prop("rows", 2);
        $(this).animate({"height": "55px"}, 500);
    });
</script>