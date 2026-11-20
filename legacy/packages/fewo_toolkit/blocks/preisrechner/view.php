<div class="preisrechner">
    <?php
    if (isset($preisliste) && $preisliste > 0) {
        
        ?>
        <div class="left">
            <div class="row">
                <div class="title"><?php echo t("Preisrechner.Reisezeit");?>:</div>
                <div class="inputs">
                    <input type="text" id="Anreise" name="Anreise" value="" placeholder="tt.mm.jjjj" size="12" readonly />
                    <input type="text" id="Abreise" name="Abreise" value="" placeholder="tt.mm.jjjj" size="12" readonly />
                </div>
            </div>
            <div class="row">
                <div class="title"><?php echo t("Preisrechner.Personen");?>:</div>
                <div class="inputs">
                    <select id="Personen" name="Personen">
                        <option value="0"></option>
                        <?php
                        foreach ($oMaxGuests as $number) {
                            echo "<option value=\"".$number."\">".$number."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <input type="button" value="<?php echo t("Preisrechner.Berechnen");?>" id="btnCalculate">
                <!--
                <input type="button" value="<?php echo t("Preisrechner.Speichern");?>" id="btnSave" disabled="disabled">
                <form method="POST" id="save_offer" action="<?php echo BASE_URL."/index.php/".$controller->ftSystemDir."/pdf/preisliste/".$preisliste;?>">
                    <input type="hidden" name="start" value="20150912" />
                    <input type="hidden" name="ende" value="20150912" />
                    <input type="hidden" name="personen" value="20150912" />
                    <input type="hidden" name="modus" value="<?php echo $controller->modus;?>" />
                </form>
                -->
            </div>
        </div>
        <div class="right">
            <div class="result">
                <img src="<?php echo $this->getThemePath()?>/../../assets/images/preloader.png" id="preloader" class="preloader" />
                <span class="price"></span>
                <br />
                <span class="nights"></span>
            </div>
        </div>
        <div class="row details">
            <div id="pricelist" class="pricelist">
                <em class="error">This is an error.</em>
                <div class="details">
                    <span>[ <a href="">Details zeigen</a> ]</span>
                    <div id="psublist">
                        
                    </div>
                </div>
            </div>
        </div>
    <?php
    } else {
        ?>
        <em>Es ist noch keine Preisliste verknüpft.</em>
    <?php
    }
    ?>
</div>
<!-- Bleibt innerhalb des Templates wg. den Platzhaltern -->
<script type="text/javascript">
    $(function() {
        $( "#Anreise" ).datepicker({minDate: new Date(<?php echo (time() * 1000);?>), maxDate: "+18M", showOtherMonths: true, selectOtherMonths: true});
        $( "#Anreise" ).datepicker( "option", "dateFormat", "dd.mm.yy" );
        $( "#Anreise" ).datepicker( "option", "showAnim", "fadeIn" );
        $( "#Anreise" ).datepicker( $.datepicker.regional[ "de" ] );
    });

    $(function() {
        $( "#Abreise" ).datepicker({minDate: new Date(<?php echo (time() * 1000);?>), maxDate: "+18M", showOtherMonths: true, selectOtherMonths: true});
        $( "#Abreise" ).datepicker( "option", "dateFormat", "dd.mm.yy" );
        $( "#Anreise" ).datepicker( "option", "showAnim", "fadeIn" );
        $( "#Abreise" ).datepicker( $.datepicker.regional[ "de" ] );
    });

    $("#Anreise").on("change", function() {
        $( "#Abreise" ).datepicker( "option", "minDate", $( this ).datepicker( "getDate" ) );
    });

    $("#pricelist .details a").on("click", function(event) {
        event.preventDefault();
        $("#pricelist .details > span").hide(); // Versteckt den Link
        $("#psublist").show("fast");
    });

    $("#btnSave").on("click", function() {
        $("#save_offer input[name=start]").val($("#Anreise").val());
        $("#save_offer input[name=ende]").val($("#Abreise").val());
        $("#save_offer input[name=personen]").val($("#Personen").val());

        $("#save_offer").submit();
    });

    $("#btnCalculate").on("click", function() {
        
        $(this).blur();

        $.ajax("<?php echo BASE_URL."/index.php/".$controller->ftSystemDir."/ajax/preisrechner/".($type == "EX" ? "extern" : $preisliste)?>", {
            type: "POST",
            data: {"start": $("#Anreise").val(), "ende": $("#Abreise").val(), "personen": $("#Personen").val(), "modus": "<?php echo $controller->modus;?>", "url": "<?php echo $controller->url_extern;?>"},
            // data: {"start": "20.06.2015","ende": "25.06.2015", "personen": 2, "modus": "<?php echo $controller->modus;?>"},
            dataType: "json",
            beforeSend: function() {
                $(".pricelist .error").hide();
                $(".pricelist").hide();
                $(".result span").hide();
                $(".preloader").show();
                $(".pricelist #psublist").hide();
                $(".preisrechner .result .price").hide();
                $(".preisrechner .result .nights").hide();
                $(".preisrechner #btnSave").prop("disabled", true);
            },
            success: function(response) {
                $(".pricelist").show();
                $(".result span").show();

                if (response.status == "ok") {
                    $(".pricelist .details > span").show();
                    $(".preisrechner .result .price").html(response.amount+",00 EUR");
                    $(".preisrechner .result .nights").html("("+response.nights+" &Uuml;bernachtungen)");
                    $(".preisrechner #btnSave").prop("disabled", false);
                    
                    // Alles ok!
                    $(".pricelist .details").show();

                    var pl_html = "";

                    var n = 1;

                    $.each(response.pricelist, function() {

                        pl_html += "<div class=\"item-row\">";
                        if (this.type == "season")
                        {
                            pl_html += "<div class=\"night\">"+(n++)+". Nacht</div>";
                            pl_html += "<div class=\"date\">"+this.date_from+" &ndash; "+this.date_to+"</div>";
                            pl_html += "<div class=\"season\">"+this.name+"</div>";
                            pl_html += "<div class=\"persons\">"+response.guests+" Person"+(response.guests > 1 ? "en" : "")+"</div>";
                        } else
                        if (this.type == "cleaning") {
                            // Endreinigung?
                            pl_html += "<div class=\"extra\">Endreinigung</div>";
                        } else
                        if (this.type == "deposit") {
                            pl_html += "<div class=\"extra\">Kaution</div>";
                        }
                        pl_html += "<div class=\"price\">"+this.price+" EUR</div>";
                        pl_html += "</div>";
                    })
                    
                    // Endergebnis
                    pl_html += "<div class=\"result-row\">";
                    pl_html += "<div class=\"price\">= "+response.amount+" EUR</div>";
                    pl_html += "</div>";

                    $("#psublist").html(pl_html);

                    // $("#psublist").show();


                } else
                if (response.status == "error") {
                    // Fehler
                    $(".pricelist .error").html("<?php echo t("Preisrechner.Fehler");?>: " + response.message);
                    $(".pricelist .error").show();
                }
            },
            error: function() {
                $(".pricelist .error").html("<?php echo t("Preisrechner.FehlerUnerwartet");?>");
                $(".pricelist .error").show();
            },
            complete: function() {
                
                $(".preloader").hide();
            }
        })

    });

</script>