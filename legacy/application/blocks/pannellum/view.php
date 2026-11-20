<div id="panorama_<?php echo $panorama_id;?>" class="<?php echo $css_class;?>"></div>
<!-- <p>Is mobile: <?php echo $is_mobile;?></p> -->
<script type="text/javascript">
$(document).ready(function() {
    var panorama_viewer_<?php echo $panorama_id;?>_size = "";

    // Initial resize
    resizeContainer("panorama_<?php echo $panorama_id;?>", <?php echo $resize_ratio;?>);

    var panorama_viewer_<?php echo $panorama_id;?> = pannellum.viewer("panorama_<?php echo $panorama_id;?>", {
        <?php echo $viewer_code_parsed; ?>
    });

    $(window).on("resize", function() {
        // current_container_size = $("#panorama_<?php echo $panorama_id;?>").width() + "x" + $("#panorama_<?php echo $panorama_id;?>").height();
        current_container_size = $(window).width() + "x" + $(window).height();
        if (panorama_viewer_<?php echo $panorama_id;?>_size != current_container_size)
        {
            panorama_viewer_<?php echo $panorama_id;?>_size = current_container_size;   
            
            resizeContainer("panorama_<?php echo $panorama_id;?>", <?php echo $resize_ratio;?>);
        }
    });
});
</script>