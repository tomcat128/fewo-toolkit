<hr />
<h4><?php echo t("Preismatrix");?></h4>

<div class="add_season">
    <input type="text" class="span4 form-control ccm-input-text" name="season_new" size="25" />
    <a class="btn btn-default" type="button" action="add_season">Neue Saisonzeit hinzufügen</a>
</div>
<div class="clearfix"></div>
<?php
// var_dump($pricelist_data);
?>
<div class="list_season">
    <div class="title row">
        <div class="name">Saison</div>
        <div class="date">Datum von</div>
        <div class="date">Datum bis</div>
        <?php
        for ($i = 1; $i <= $pricelist_guests; $i++) {
            ?>
            <div class="price"><?php echo t2("%d Gast", "%d Gäste", $i);?></div>
            <?php
        }
        ?>
    </div>
    <?php
    $parse = array("data" => $this->controller->getPricelistById($list_id), "template" => array(""));

    $today = date("Y-m-d", time());

    $showPastOption = "n";

    /*
    foreach ($parse as $key => $values) {
        for ($j = 0; $j < count($values); $j++) {
            $data = $values[$j];

            if (!isset($data["bezeichnung"])) {
                $data["bezeichnung"] = "";
            }

            $prices = array();

            if (isset($data['preis_pro_nacht'])) {
                $prices = unserialize($data['preis_pro_nacht']);
            }

            $class = $key;

            if ($today > $data["datum_bis"] && $key !== "template") {
                $class .= " past";

                if ($showPastOption === "n") {
                    $showPastOption = "y";
                }
            }

            // Wenn Zeiten in der Vergangenheit sind, dann erstelle die Möglichkeit zum einblenden
            if ($showPastOption === "y") {
                echo "<div class=\"row\">";
                echo "<input type=\"checkbox\" id=\"show_past_seasons\" style=\"float: left;\" />&nbsp;<label for=\"show_past_seasons\">Zeige Saisonzeiten in der Vergangenheit</label>";
                echo "</div>";
                
                $showPastOption = "d";
            }
            ?>

            <div class="row <?php echo $class;?>">
                <div class="name"><input type="text" class="span4 form-control ccm-input-text" name="season[]" value="<?php echo $data["bezeichnung"];?>" /></div>
                <div class="date"><input type="text" class="span4 form-control ccm-input-text" type="c_arrival" name="date_from[]" value="<?php echo (new \DateTime($data['datum_von']))->format("d.m.Y");?>" /></div>
                <div class="date"><input type="text" class="span4 form-control ccm-input-text" type="c_depart" name="date_to[]" value="<?php echo (new \DateTime($data['datum_bis']))->format("d.m.Y");?>" /></div>
                <?php
                for ($i = 1; $i <= $pricelist_guests; $i++) {
                    ?>
                    <div class="price"><input type="text" class="span4 form-control ccm-input-text" name="price[<?php echo $i;?>][]" value="<?php echo ($prices[$i] > 0 ? number_format($prices[$i], 2, ",", "") : "")?>" /></div>
                    <?php
                }
                ?>
                <div class="action"><a href="" class="btn btn-danger" action="delete_season">Löschen</a></div>
            </div>
        <?php
        }
    }
    */
    ?>
    <br />
    <strong style="color: red;">MATRIX IST AKTUELL DEAKTIVIERT! NUR IMPORT DER PREISLISTE MÖGLICH!</strong>
    <br /><br /><br />
</div>

<script type="text/javascript">
    function SetPickers() {
        $(".list_season .data .date > input").datepicker();

        $(".list_season .data .date > input[name*=date_from]").on("change", function() {
            $(this).parent().next().find("input[name*=date_to]").datepicker( "option", "minDate", $( this ).datepicker( "getDate" ) );
        });
    }

    var max_guests = <?php echo $pricelist_guests;?>;

    var delete_action = (function(event) {
        event.preventDefault();
        // Suche das Eltern-Data-Div und entferne es
        $(this).parents(".data").remove();
    });

    $("a[action=add_season]").on("click", function(event) {
        event.preventDefault();

        var season = $("input[name=season_new]");

        if (season && season.val())
        {
            var tmpl = $(".list_season .template");

            var new_item = $("<div class=\"row data\">"+$(tmpl).html()+"</div>").insertAfter(".list_season .title");

            $(new_item).find(".name input").val(season.val());

            // $(new_item).find(".date > input").datepicker();
            SetPickers();

            // Eingabe löschen
            season.val("");

            $("a[action=delete_season]", new_item).on("click", delete_action);
        }
    });


    $(document).ready(function() {
        // Init Datepicker
        
        SetPickers();

        $("a[action=delete_season]").on("click", delete_action);

        $("#show_past_seasons").on("change", function() {
            if ($(this).prop("checked") === true) {
                $(".list_season .past").show("fast");
            } else {
                $(".list_season .past").hide("fast");
            }
        });
    });
</script>