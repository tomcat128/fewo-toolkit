<div id="bildergalerie" class="bildergalerie fancybox">
    <?php
    if (!isset($bildergalerie))
    {
        echo "<em><strong>Fehler Bildergalerie:</strong> Es wurde noch kein Dateialbum ausgewählt.</em>";
    } elseif (isset($bildergalerie) && is_array($bildergalerie)) {
        $i = 1;
        
        foreach ($bildergalerie as $image) {
            $src_path_thumb = str_replace("/application/files/", "/application/files/thumbnails/file_manager_detail/", $image['rel_path']);

            // $image['thumb'] = "<img src=\"".$src_path_thumb."\" width=\"234\" height=\"151\" />";
            $image['thumb'] = "<img src=\"".$image['thumb_2x']."\"  />";

            echo "<a href=\"".$image['rel_path']."\" ".
                 // "style=\"background-image: url(".$src_path_thumb.");\" ".
                 "data-caption=\"".htmlentities(!empty($image['description']) ? $image['description'] : $image['title'])."\" ".
                 "alt=\"".htmlentities(!empty($image['description']) ? $image['description'] : $image['title'])."\" ".
                 "rel=\"gallery\" ".
                 "id=\"bild_".($i++)."\" ".
                 "class=\"thumbnail bild\" ".
                 "data-fancybox=\"gallery\"".
                 ">".
                 // "<img src=\"".$this->getBlockURL()."/img/spacer.png\" />".
                 $image['thumb'].
                 "</a>";
        }
    }
    ?>
</div>
<div style="clear: both;"></div>