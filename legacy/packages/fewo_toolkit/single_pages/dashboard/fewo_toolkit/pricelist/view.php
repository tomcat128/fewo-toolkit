<div id="fwb-pricelist" class="fwb-pricelist">
    <div class="ccm-dashboard-header-buttons">
    <?php if ($showOptionButton && isset($list_id)) { ?>
        <a href="<?php echo View::url('/dashboard/fewo_toolkit/pricelist/settings', $list_id)?>" class="btn btn-primary"><?php echo t('Einstellungen bearbeiten')?></a>
    <?php } ?>

    <?php if ($showDeleteButton && isset($list_id)) { ?>
        <a href="<?php echo View::url('/dashboard/fewo_toolkit/pricelist/delete', $list_id)?>" class="btn btn-danger"><?php echo t('Delete')?></a>
    <?php } ?>

    <?php if ($showAddButton) { ?>
        <a href="<?php echo View::url('/dashboard/fewo_toolkit/pricelist/add')?>" class="btn btn-primary"><?php echo t('Neue Preisliste hinzufügen')?></a>
    <?php } ?>
    </div>

    <?php if ($showAdd || $showEdit) { ?>
    <!-- Liste hinzufügen oder bearbeiten -->
    <?php
    $fh = Loader::helper('form');
    ?>
    <form method="post" class="form-horizontal" id="pricelist_edit" action="<?php echo $view->url('/dashboard/fewo_toolkit/pricelist/edit/', $list_id)?>">
        <div class="control-group">
            <?php echo $fh->label('pricelist_set_name', t('Ferienwohnung'));?>
            <div class="controls">
                <?php echo $fh->text('pricelist_set_name', $pricelist_name, array('class' => 'span4'))?>
            </div>
        </div>

        <br />

        <?php if ($pricelist_import === true) { ?>
        
        <em>Diese Preisliste befindet sich im Import-Modus. Die Daten werden von folgender Quelle bezogen: <a href="<?php echo $pricelist_import_url;?>" target="_blank"><?php echo $pricelist_import_url;?></a></em>
        
        <?php echo $fh->hidden("import_mode", true); ?>

        <?php } else { ?>
        
        <div class="control-group">
            <?php echo $fh->label('pricelist_set_cleaning', t('Gebühr für Endreinigung'))?>
            <div class="controls">
                <?php echo $fh->text('pricelist_set_cleaning', $pricelist_cleaning, array('class' => 'span4'))?>
            </div>
        </div>
        
        <br />

        <div class="control-group">
            <?php echo $fh->label('pricelist_set_nights', t('Mindestaufenthaltsdauer'))?>
            <div class="controls">
                <?php echo $fh->select('pricelist_set_nights', $pricelist_nights_items, $pricelist_nights)?>
            </div>
        </div>
        
        <br />

        <div class="control-group">
            <?php echo $fh->label('pricelist_set_guests', t('Anzahl Gäste (maximal)'))?>
            <div class="controls">
                <?php echo $fh->select('pricelist_set_guests', $pricelist_guests_items, $pricelist_guests)?>
            </div>
        </div>
        
        <br />

        <div class="control-group">
            <?php echo $fh->label('pricelist_set_commission', t('Anzahlung (in %)'))?>
            <div class="controls">
                <?php echo $fh->select('pricelist_set_commission', $pricelist_commission_items, $pricelist_commission)?>
            </div>
        </div>
        
        <br />
        <?php echo $fh->hidden('pl_id', $list_id); ?>

        <?php
        if ((isset($pricelist_guests) && $pricelist_guests > 0) || $list_id > 0) {
            include dirname(__FILE__)."/matrix.php";
        }
        ?>
        
        <?php } ?>
        
        <div style="clear: both;"></div>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <a href="<?php echo View::url('/dashboard/fewo_toolkit/pricelist')?>" class="btn btn-default pull-left">
                    <?php echo ($this->controller->getTask() == 'add') ? t('Cancel') : t('Zurück zur Übersicht')?>
                </a>
                <?php
                $submitName = ($this->controller->getTask() == 'add') ? t('Add') : t('Save');
                echo $fh->submit('add', $submitName, array('class' => 'btn btn-primary pull-right'))?>
            </div>
        </div>
        
    </form>

    <?php } ?>

    <?php if ($showSettings) { ?>
    <!-- Einstellungen bearbeiten -->
    <?php
        $fh = Loader::helper('form');
    ?>
    <h2>Einstellungen (&quot;<?php echo $pricelist_name;?>&quot;)</h2>
    
    <form method="post" class="form-horizontal" id="pricelist_edit" action="<?php echo $view->url('/dashboard/fewo_toolkit/pricelist/settings/', $list_id)?>">

        <div class="control-group">
            <div class="controls">
                <?php echo $fh->select('pricelist_set_export_import', array("export" => t("Preisliste exportieren"), "import" => t('Preisliste importieren')), $pricelist_set_export_import)?>
            </div>
        </div>

        <hr />

        <div class="pl_settings_export">
            <h3>Preisliste <u>exportieren</u></h3>
            <em><?php echo t('Nehmen Sie hier die Einstellungen zum Export der Preisliste vor.');?></em>
            <br /><br />

            <?php
                $url = View::url('/fewobacher/export/pricelist/', $list_id);
            ?>

            <div class="control-group">
                <?php echo $fh->label('pricelist_set_export_active', t('Export der Preisliste erlauben'))?>
                <div class="controls">
                    <?php echo $fh->select('pricelist_set_export_active', array("no" => t("Nein"), "yes" => t("Ja")), $pricelist_set_export_active)?>
                </div>
            </div>
            
            <br />

            <div class="control-group">
                <?php echo $fh->label('', t('Export-URL für diese Preisliste'))?>
                <br />
                <a href="<?php echo $url;?>" target="_blank"><?php echo $url;?></a>
            </div>
            
            <br />
            
            <div class="control-group">
                <?php echo $fh->label('pricelist_set_export_pass', t('Passwortschutz'))." ".t('Bitte geben Sie ein Passwort an (wird empfohlen)');?>
                <div class="controls">
                    <?php echo $fh->text('pricelist_set_export_pass', $pricelist_set_export_pass, array('class' => 'span4'))?>
                </div>
                <em style="font-size: 14px; margin-top: 5px; display: block;">Hinweis: Das Passwort sollte zwischen 6 und 32 Zeichen lang sein und darf nur aus Buchstaben (Gross- und Kleinschreibung ohne Umlaute) und Zahlen bestehen.</em>
            </div>

        </div>

        <div class="pl_settings_import">
            <h3>Preisliste <u>importieren</u></h3>
            <em><?php echo t('Wenn Sie die Preisliste von einem anderen Server importieren möchten, können Sie dies hier eintragen.');?></em>
            <br /><br />

            <div class="control-group">
                <?php echo $fh->label('pricelist_set_import_url', t('URL bei welcher sich die zu importierenden Daten befinden'));?>
                <div class="controls">
                    <?php echo $fh->text('pricelist_set_import_url', $pricelist_set_import_url, array('class' => 'span4'))?>
                </div>
            </div>
            
            <br />

            <div class="control-group">
                <?php echo $fh->label('pricelist_set_import_pass', t('Passwortschutz'))." ".t('Bitte geben Sie ein Passwort ein (falls gefordert)');?>
                <div class="controls">
                    <?php echo $fh->text('pricelist_set_import_pass', $pricelist_set_import_pass, array('class' => 'span4'))?>
                </div>
            </div>
        </div>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <a href="<?php echo View::url('/dashboard/fewo_toolkit/pricelist/edit', $list_id)?>" class="btn btn-default pull-left">
                    <?php echo t('Back');?>
                </a>
                <?php
                $submitName = t('Save');
                echo $fh->submit('settings', $submitName, array('class' => 'btn btn-primary pull-right'))
                ?>
            </div>
        </div>
        
        <br />
        
        <?php
            include dirname(__FILE__)."/pdf.php";
        ?>

    </form>
    <script type="text/javascript">
    var change_view = (function() {
        var type = $('select#pricelist_set_export_import').val();

        if (type === "export") {
            $('div.pl_settings_export').show();
            $('div.pl_settings_import').hide();
        } else
        if (type === "import") {
            $('div.pl_settings_export').hide();
            $('div.pl_settings_import').show();
        }
    });

    $(document).ready(change_view);

    $("select#pricelist_set_export_import").on("change", change_view);

    </script>
    <?php } ?>    

    <?php if ($showDelete) { ?>
    <!-- Löschen der Liste bestätigen -->
    <?php echo t("Soll die Preisliste %s wirklich gelöscht werden?", "<strong>".$pricelist_name."</strong>");?>
    <hr />
    <a class="btn btn-default pull-left" href="<?php echo View::url("/dashboard/fewo_toolkit/pricelist/edit/", $list_id);?>"><?php echo t("Cancel");?></a>
    <div class="btn-group pull-right">
       <a href="<?php echo View::url("/dashboard/fewo_toolkit/pricelist/delete/", $list_id."/confirm");?>" class="btn btn-danger" href="javascript:void(0)"><?php echo t("Preisliste loeschen");?></a>
    </div>
    <?php } ?>

    <?php if ($showOverview) { ?>
    <!-- Übersicht über alle vorhandenen Preislisten -->
    <ul class="item-select-list">
    <?php
        foreach ($this->controller->getAvailableLists() as $list) {
    ?>
    <li list-id="pl-<?php echo $list['listID'];?>">
            <a href="<?php echo View::url("/dashboard/fewo_toolkit/pricelist/edit/", $list['listID']);?>" class="ccm-group-inner" style="padding-left: 5px;">
                <i class="fa fa-cubes"></i> <?php echo $list['name'];?>
                /
                Endreinigung: <?php echo $list['gebuehr_endreinigung'];?> &euro;
                /
                Mindestaufenthaltsdauer: <?php echo $list['mindestaufenthaltsdauer'];?> Tag(e)
                /
                Gäste: <?php echo $list['anzahl_gaeste'];?>
            </a>
        </li>
        <?php } ?>
    </ul>
    <?php } ?>
</div>