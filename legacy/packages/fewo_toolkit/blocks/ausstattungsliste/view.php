<?php
    if ($controller->getTopBr() === "Y") {
        echo "<br />";
    }
?>
<div class="ausstattungsliste details">
    <h3><?=$liste;?></h3>
    <div class="toogle">
        <span><?php echo t("Ausstattungsliste.Liste");?>: [&nbsp;</span>
        <a type="show" onclick="return false;" href="#" class="show-item"><?php echo t("Ausstattungsliste.Anzeigen");?></a>
        <a type="hide" onclick="return false;" href="#" class="hide-item"><?php echo t("Ausstattungsliste.Ausblenden");?></a>
        <span>&nbsp;]</span>
    </div>
    <?php
        // echo "<pre>";
        // var_dump($controller->ausstattung);
        // echo "</pre>";

        $list_ul = $list_ol = array(); $more = 1;

        foreach ($controller->getAusstattungsliste() AS $item) {
            if (isset($item->kommentar))
            {
                $list_ul[] = "<li>".$item->getName()." <span class=\"more\">(".($more++).")</span></li>";
                $list_ol[] = "<li>".$item->kommentar."</li>";
            } else {
                $list_ul[] = "<li>".$item->getName()."</li>";
            }
        }
    ?>
    <div class="infos">
        <?php
        if (count($list_ul) > 0) {
        ?>
        <ul>
            <?=implode("", $list_ul);?>
        </ul>
        <?php
        if (count($list_ol) > 0)
        {
        ?>
        <ol>
            <?=implode("", $list_ol);?>
        </ol>
        <?php
        }
        }
        ?>
    </div>
</div>    