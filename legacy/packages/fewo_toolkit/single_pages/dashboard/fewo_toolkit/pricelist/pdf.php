<hr />
<h3><?php echo t("Einstellungen für PDF-Export");?></h3>

<div class="control-group">
    <?php echo $fh->label("pl_pdf_name", t("Name"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_name", $pl_pdf_name)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_owner", t("Inhaber"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_owner", $pl_pdf_owner)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_address", t("Strasse, Ort"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_address", $pl_pdf_address)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_phone", t("Telefon"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_phone", $pl_pdf_phone)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_homepage", t("Internet"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_homepage", $pl_pdf_homepage)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_email", t("E-Mail"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_email", $pl_pdf_email)?>
    </div>
</div>

<br />

<div class="control-group">
    <?php echo $fh->label("pl_pdf_inclusive", t("Inklusivleistungen"))?>
    <div class="controls">
        <?php echo $fh->text("pl_pdf_inclusive", $pl_pdf_inclusive)?>
    </div>
</div>

<br />

<form method="post" class="form-horizontal" id="favicon-form" action="<?php echo $view->action('update_logo')?>" >
    <?php echo $fh->label("pricelist_pdf_logo", t("Icon / Logo"))?>*
    <fieldset>
            <?php
            if (!empty($pl_pdf_logo)) {
                $f = File::getByID($pl_pdf_logo);
            }
            ?>
            <div class="form-group">
                <div class="col-md-12">
                    <?php 
                        echo $concrete_asset_library->file('ccm-favicon-file', 'pl_pdf_logo', t('Choose File'), $f);
                    ?>
                </div>
            </div>
            <div class="help-block">*<?php echo t("Die Datei muss im GIF-, PNG- oder JPG-Format vorliegen und idealerweise quardratisch (256x256 Pixel).")?></div>
    </fieldset>
</form>