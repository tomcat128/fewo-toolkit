<?php
if ($controller->getTopBr() === "Y") {
    echo "<br />";

}
?>
<div class="preisliste">
    <?php
    if (false)
    {
        // Einige Saisonzeiten/Labels zum übersetzen... Block wird nicht ausgeführt
        t("Preisuebersicht.Nachsaison");
        t("Preisuebersicht.Wintersaison");
        t("Preisuebersicht.Nebensaison");
        t("Preisuebersicht.Vorsaison");
        t("Preisuebersicht.Hauptsaison");
        t("Preisuebersicht.Hochsaison");
        t("Preisuebersicht.Ostern");
        t("Preisuebersicht.Pfingsten");
        t("Preisuebersicht.Weihnachten");
    }

    $pl = $controller->getPricelist();
    
    if (!isset($pl) && !is_array($pl)) {
        echo "<em style=\"padding-top: 5px; display: block;\">";
        echo "Es ist noch keine Preisliste verknüpft mit diesem Block verknüpft...";
        echo "</em>";
    } else {
        if (is_array($pl['matrix']) && count($pl['matrix']) > 0) {
            foreach ($pl['matrix'] as $period) {
                $label_period = "Preisuebersicht.".$period['bezeichnung'];
                echo "<div class=\"period\">";
                echo "<div class=\"title\"><strong>".t($label_period)."</strong></div>";
                echo "<div class=\"date\">".(new \DateTime($period['datum_von']))->format("d.m.Y")." &ndash; ".(new \DateTime($period['datum_bis']))->format("d.m.Y").":</div>";
                foreach ($period['preise'] as $price => $persons) {
                    if ($price > 0)
                    {
                        echo "<div class=\"row\">";
                        echo "<div class=\"persons\">".($persons['min'] < $persons['max'] ? $persons['min']."-" : "").t2("%d ".t("Preisuebersicht.Person"), "%d ".t("Preisuebersicht.Personen"), $persons['max'])."</div>";
                        echo "<div class=\"price\">".number_format($price, 0)." &euro;</div>";
                        echo "</div>";
                    }
                }
                echo "</div>";
            }
        }
    }
    ?>
    <div class="annotation">
        <div class="title"><?php echo t("Preisuebersicht.KeinePreisliste")?></div>
    </div>
</div>
<div style="clear: both;"></div>