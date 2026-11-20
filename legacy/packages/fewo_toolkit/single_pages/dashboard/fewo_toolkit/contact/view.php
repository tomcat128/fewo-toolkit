<style type="text/css">
.fwb-contact {
    margin-right: 20px;
}
.fwb-contact .add_row input {
    width: 250px;
    float: left;
}
.fwb-contact .add_row a {
    float: left;
}
.item-select-list li:hover {
    /*background-color: yellow;*/
    /*cursor: pointer;*/
}

ul.item-select-list i.ccm-item-select-list-remove{position:absolute; font-size: 20px; top:10px;right:10px;font-style:normal;margin:0px;padding-left:5px;padding-right:5px;margin-right: 30px;}
ul.item-select-list i.ccm-item-select-list-remove:after{font-family:FontAwesome;content:"\f00d"}
ul.item-select-list i.ccm-item-select-list-remove:hover{cursor:pointer;opacity: 0.5}
ul.item-select-list i.ccm-item-select-list-sort{font-size: 20px; }
</style>
<script type="text/javascript">
var cf_fields = new Array();
<?php
    $types = array("text", "select", "checkbox", "radio");
    for ($i = 1; $i < 5; $i++) {
        ?>
        cf_fields.push(
            {
                "name" : "<?php echo substr(md5(rand(0, 9999999999999)), 0, 16);?>",
                "type" : "<?php echo $types[$i - 1];?>",
                "settings" : "<?php echo md5(rand(0, 9999999999999));?>",
                "mandatory" : "<?php echo ($i % 2 == 0 ? "yes" : "no");?>"
            }
            );
        <?php
    }
?>

</script>
<div id="fwb-contact" class="fwb-contact">
    <div class="ccm-dashboard-header-buttons">
    </div>
    <?php
    $fh = Loader::helper('form');
    ?>
    <form method="post" class="form-horizontal" id="contact_edit" action="<?php echo $view->url('/dashboard/fewo_toolkit/contact/update/')?>">
        <h3>Eingabefelder des Kontaktformulars bearbeiten</h3>
        <ul class="item-select-list ccm-attribute-sortable-set-list ui-sortable" id="field_list">
            <?php
            
            ?>
            <div style="display: none;" id="li_template">
                <li id="row_#" class="ccm-item-select-list-sort" style="height: 44px;">
                    <div style="padding: 8px 8px 8px 0;">
                        <div style="width: 200px; background-color: pink; clear: left; float: left;">
                            <input row="name" type="text" value="" name="input[name][]" style="width: 100%;" />
                        </div>
                        <div style="width: 120px; background-color: green; float: left; margin-left: 10px;">
                            <select row="type" name="input[type][]" style="padding: 4px; width: 100%;">
                                <option value=""></option>
                                <option value="text">Text</option>
                                <option value="select">Select</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="radio">Radio</option>
                            </select>
                        </div>
                        <div style="width: 400px; background-color: ; float: left; margin-left: 10px;">
                            <input row="settings" type="text" value="" name="input[settings][]" style="width: 100%;" placeholder="Konfiguration hier eintragen..." />
                        </div>
                        <div style="width: 100px; background-color: ; float: left; margin-left: 10px;">
                            <select row="mandatory" name="input[mandatory][]" style="padding: 4px; width: 100%;">
                                <option value="no"></option>
                                <option value="yes">Pflichtfeld</option>
                            </select>
                        </div>
                    </div>
                    <i class="ccm-item-select-list-remove"></i>
                    <i class="ccm-item-select-list-sort"></i>
                </li>
            </div>
            <?php

            ?>
        </ul>

        <div class="add_row">
            <a class="btn btn-default" type="button" action="add_row">Neue Zeile einfügen</a>
            <br /><br /><br />
            <em>Konfiguration: Wenn als Typ TEXT gewählt wurde, hier dann einen regulären Ausdruck eintragen. Bei CHECKBOX einen einzelnen Wert ohne Sonderzeichen. Bei SELECT und RADIO einzelne Werte mit dem Pipe-Symbol [ | ] trennen.</em>
        </div>




        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <a href="<?php echo View::url('/dashboard/fewo_toolkit')?>" class="btn btn-default pull-left">
                    <?php echo ($this->controller->getTask() == 'add') ? t('Cancel') : t('Zurück zur Übersicht')?>
                </a>
                <?php
                $submitName = ($this->controller->getTask() == 'add') ? t('Add') : t('Save');
                echo $fh->submit('add', $submitName, array('class' => 'btn btn-primary pull-right'));?>
            </div>
        </div>
    </form>
</div>
<script type="text/javascript">
var row_count = 0;

function AddRowList(index, name, type, settings, mandatory) {
    $("#field_list").append( $("#li_template").html().replace("row_#", "row_"+index) );

    $("#field_list #row_"+index+" input[row=name]").val(name);
    $("#field_list #row_"+index+" select[row=type]").val(type);
    $("#field_list #row_"+index+" input[row=settings]").val(settings);
    $("#field_list #row_"+index+" select[row=mandatory]").val(mandatory);

    $(".ui-sortable li#row_"+index+" i.ccm-item-select-list-remove").on("click", function() {
        if (confirm("<?php echo t("Soll diese Zeile wirklich gelöscht werden?");?>")) {
            $(this).closest("li").remove();
        }
    });
}

$("a[action=add_row]").on("click", function(event) {
    event.preventDefault();

    AddRowList(row_count);

    row_count++;
});

$(document).ready(function() {
    for (var i = 0; i < cf_fields.length; i++) {
        AddRowList(i, cf_fields[i].name, cf_fields[i].type, cf_fields[i].settings, cf_fields[i].mandatory);
    }

    row_count = (i + 1);
});
</script>