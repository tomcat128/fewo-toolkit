<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
#austattungsListe {
    list-style-type: none;
    margin: 0;
    padding: 0;    
}

#austattungsListe li {
    background-color: white;
}

#austattungsListe li:hover {
    background-color: #FCEAD1;
}

#austattungsListe li span:hover {
    cursor: move;
}

li.ausstattung-block-option {
    position: relative;
    border-bottom: 1px solid #ddd;
    padding-bottom: 3px;
    padding-top: 3px;
}

li.ausstattung-block-option img {
    top: 3px; right: 0; float: right;
}

li.ausstattung-block-option img:first-child {
    margin-left: 10px;
}

li.ausstattung-block-option input {
    width: 75%;
}

li.ausstattung-block-option input.comment {
    padding: 0 4px;
    font-size: 10px;
    margin-top: 3px;
}

li.ausstattung-block-option span.comment {
    font-size: 10px;
    font-style: italic;
    display: none;
}
</style>
<script type="text/javascript">
    <?php
    if ($controller->getAusstattungsliste() == null)
    {
    ?>    
    var currentOption = 0;
    <?php
    } else {
    ?>    
    var currentOption = parseInt("<?php echo count($controller->getAusstattungsliste())?>");
    <?php
    }
    ?>
</script>
<div class="ccm-ui">
    <div class="alert-message block-message info">
        Hier können der Ausstattungsliste einzelne Elemente hinzugefügt werden.
    </div>
    <br />

    <strong>Bezeichnung</strong><br/>
    <input type="text" style="width: 320px" name="liste" value="<?php echo $controller->getBezeichnung()?>" />
    <br /><br />

    <strong>Abstand am Anfang einfügen</strong><br/>
    <input type="checkbox" name="top_br" value="Y" <?php echo $controller->getTopBr() == "Y" ? "checked=\"checked\"" : "";?> /> Fügt &lt;br /&gt; an den Anfang des Blocks ein
    <br /><br />
    
    <strong>Ausstattungselement hinzufügen</strong><br/>
    <input type="text" name="optionValue" id="ccm-ausstattung-optionValue" style="width: 320px" />
    <input type="button" onclick="addOption()" value="Einfügen" class="btn small" />
    <br /><br />
    
    <?php
        $php_item_list = array();

        $id = 1;

        $options = $controller->getAusstattungsliste();

        if (is_array($options)) {
            foreach ($options as $item) {
                $php_item_list[] = array(   "id" => $id,
                                            "name" => htmlspecialchars($item->getName()),
                                            "comment" => htmlspecialchars($item->getKommentar()),
                                            "new" => false
                                        );

                $id ++;
            }
        }
    ?>

    <strong>Ausstattungsliste</strong><br/>
    <script type="text/javascript">
        <?php
        // Liste aus der DB hier initialisieren
        if (count($php_item_list) > 0)
        {
            ?>
            var item_list = new Object(<?php echo json_encode($php_item_list);?>);
            <?php
        } else {
            ?>
            var item_list = new Object(<?php echo json_encode(array());?>);
            <?php
        }
        ?>
    </script>
    <?php

    ?>
    <ul id="austattungsListe"> </ul>
    <br /><br />
</div>
<script type="text/javascript">

    // Erstelle die Liste initial und beim einfügen
    function BuildItemlist() {
        $("#austattungsListe").empty();

        var ul = null;

        currentOption = 1;
        
        $.each(item_list, function() {
            // console.log(this);
            var li = "<li class=\"ausstattung-block-option\" id=\"option"+currentOption+"\" row=\""+currentOption+"\">";
            // Toolbar-Buttons
            
            li += "<a href=\"\" type=\"button\" action=\"delete\"><img src=\"<?php echo ASSETS_URL_IMAGES?>/icons/delete_small.png\" title=\"Löschen\"/></a>";
            li += "<a href=\"\" type=\"button\" action=\"update\"><img src=\"<?php echo ASSETS_URL_IMAGES?>/icons/edit_small.png\" align=\"right\" title=\"Aktualisieren\"/></a>";

            // Labels
            li += "<span class=\"infotext handle\">"+this.name+"</span>";
            li += "<div><span class=\"comment\"></span></div>";

            // Hidden-Data
            if (this.new == true) {
                li += "<input type=\"hidden\" name=\"optionName[]\" value=\""+this.name+"\" />";
                li += "<input type=\"hidden\" name=\"optionUpdate[]\" value=\""+this.name+"\" />";
                li += "<input type=\"hidden\" name=\"optionNew[]\" value=\"1\" />";
            } else {
                li += "<input type=\"hidden\" name=\"optionName[]\" value=\""+this.name+"\" />";
                li += "<input type=\"hidden\" name=\"optionUpdate[]\" value=\""+this.name+"\" />";
                li += "<input type=\"hidden\" name=\"optionNew[]\" value=\"0\" />";
            }

            li += "<input type=\"hidden\" name=\"optionComment["+this.name+"]\" value=\""+this.comment+"\" class=\"comment\" placeholder=\"Hier einen Kommentar eintragen\" />";
            li += "<input type=\"hidden\" name=\"optionOrder["+this.name+"]\" value=\"\" />";
            li += "</li>";

            $("#austattungsListe").append( li );

            currentOption++;
        });

        bindLinkButtons();

        reorderNumbers();

        // Kommentare in der Liste ausgeben
        DiCoComments();
    }

    // Liste initial erstellen

    $(document).ready(function() {
        BuildItemlist();
    });
    
    // Auslesen der Kommentare und Darstellung innerhalb der Liste
    function DiCoComments() { // dc == displaycopy
        $("#austattungsListe li").each(function() {
            var comment = $(this).find("input[name*=optionComment]");

            if (comment && $(comment).val()) {
                $(this).find("span.comment").html($(comment).val());
                $(this).find("span.comment").show();
            } else {
                $(this).find("span.comment").hide();
            }
        });
    }

    function reorderNumbers() {
        var i = 0;
        $("input[name*=optionOrder]").each(function() {
            $(this).val(i++);
        });
    }

    // Cursor an das Ende den Inputs setzen
    (function($) {
        $.fn.focusLast = function() {
            this.focus();
            var $thisVal = this.val();
            this.val('').val($thisVal);
            return this;
        };
    }(jQuery));

    function bindLinkButtons() {
        var input = null;
        var comment = null;

        // Hier werden die Clicks abgefangen
        $(document).on("click", function(event) {
            // Falls es sich um KEIN Text-Feld handelt, dann schliesse die Editier-Ansicht mit der mitgelieferten
            // Close-Methode. Das beendet die Ansicht aber auch dann nicht, wenn der Fokus in andere offene Inputs
            // gelangt (wie z.B. Bezeichnung). Dies kann man verfeinern indem man an die Inputs noch ein weiteres
            // exklusives Attribut anhängt... für die jetzigen Zwecke ist dies aber so in Ordnung
            if ($(event.target).attr("type") !== "text" && input !== null)
            {
                input.close();
                
                input = null;
            }

        });

        $("a[type=button]").on("click", function(event) {
            // Sortable deaktivieren wenn im Edit-Mode
            $( "#austattungsListe" ).sortable("disable");

            // Falls bereits geöffnet, dann schliesse
            if (input !== null)
            {
                input.close();
            }

            // Verhindert Auslösung des Standard-Events (Link folgen)
            event.preventDefault();

            // Verhindert die weitere Abarbeitung des Events
            event.stopPropagation();
            
            if ($(this).attr("action") === "delete")
            {
                var temp = Array();

                for (i = 0; i < item_list.length; i++) {
                    console.log(item_list[i]);
                    if (item_list[i].id != parseInt($(this).parent().attr("row"))) {
                        temp.push(item_list[i]);
                    }
                }

                item_list = temp;

                temp = null;

                removeOption($(this).parent().attr("row"));

                // Auch beim loeschen soll die Reihenfolge neu erstellt werden
                reorderNumbers();
            } else
            if ($(this).attr("action") === "update")
            {
                var p = $(this).parent();

                $.each(["optionUpdate"], function(index, value) {
                    // Finde das Input-Element
                    input = $(p).find("input[name*="+value+"]");
                    // Kommentar-Feld
                    comment = $(p).find("input[name*=optionComment]");
                    
                    if (input)
                    {
                        p.find("span.infotext").hide();
                        p.find("span.comment").hide();

                        $(input  ).prop("type", "text");

                        $(input).focusLast();

                        // Kommentar-Input zeigen
                        $(comment).prop("type", "text");

                        // Dies definiert eine für das Element einzigartige CLose-Methode, so dass man 
                        input.close = (function() {
                            // Sonst kann kein leeres Input gesendet werden... es bleibt immer der zuletzt
                            // eingetragene Wert gespeichert
                            var val_comment = $(comment).val();
                            var val_input   = $(input).val();

                            $(comment).prop("type", "hidden");
                            $(input).prop("type", "hidden");

                            $(comment).val(val_comment);
                            $(input).val(val_input);

                            p.find("span.infotext").html($(this).val());
                            p.find("span.infotext").show();

                            p.find("span.comment").html($(comment).val());
                            p.find("span.comment").show();
                            
                            $(p).find("input[name*=optionOrder]").attr("name", "optionOrder["+$(this).val()+"]");
                            $(p).find("input[name*=optionComment]").attr("name", "optionComment["+$(this).val()+"]");

                            // Sortable jetzt wieder aktivieren
                            $("#austattungsListe").sortable("enable");
                        });
                    }
                });
            }
        });
    }
    
    $(function() {
        $( "#austattungsListe" ).sortable({handle: '.handle',update: function() {reorderNumbers();}});
        // $( "#austattungsListe" ).disableSelection(); // AUSSCHALTEN, DA SONST ONBLUR NICHT FUNKTIONIERT
    });

    // Neu initialisieren der dazugehörigen Liste...
    pi = $("#austattungsListe").get(0);


</script>