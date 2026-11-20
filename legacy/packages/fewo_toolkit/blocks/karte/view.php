<script type="text/javascript">
    var GMAP_BASE_URL_PATH = "<?php echo $block_image_dir;?>/";

    <?php if (isset($js_points) && strlen($js_points) > 0) echo $js_points; ?>
        
    <?php if (isset($js_init) && strlen($js_init) > 0 && $is_editmode == false) echo $js_init; ?>

</script>
<div class="karte">
    <div class="googlemap" id="googlemap">
        <div class="canvasmap" id="canvasmap"></div>
    </div>

    <?php if (isset($html_controls) && strlen($html_controls) > 0) echo $html_controls; ?>
</div>