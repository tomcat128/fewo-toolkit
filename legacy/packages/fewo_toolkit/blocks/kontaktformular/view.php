<?php if (isset($error_list) && count($error_list) > 0) { ?>
<div class="status error-list">
    <h2><?php echo t("Kontaktformular.FehlerTitel");?>:</h2>
    <ul>
        <?php foreach ($error_list AS $type => $text) { ?>
        <li type="<?php echo $type;?>"><?php echo $text;?></li>
        <?php } ?>
    </ul>
</div>
<?php } ?>
<?php if (isset($sendmail_success)) { ?>
<div id="email_success" class="status success">
    <h2><?php echo t("Kontaktformular.EmailVersendet");?></h2>
</div>
<?php } ?>
<?php echo t("Kontaktformular.Pflichtangaben");?>
<form method="POST" action="<?php echo $this->url($current_path, "send");?>">
    <div class="contact-table">
        <?php $i = 0; ?>

        <!-- ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### ##### -->

        <?php foreach ($inputs as $row) { ?>
        <div class="row<?php echo (($row['format'] == "full" ? " "."single" : "").(!empty($row['name']) && $row['name'] == $honeypot_input ? " cfhpir" : ""));?>">
        <?php
            if (isset($row['type']) && $row['type'] == "blank") {
            ?>&nbsp;<?php
            } else {

                if (isset($row['inputs']) && is_array($inputs)) {
                    $elements = $row['inputs'];
                } else {
                    $elements = array($row);  
                }
            ?>
            <div class="label">
                <span class="text">
                <?php $i = 0; foreach ($elements as $input) { ?>
                <?php if (isset($input['label'])) { ?>
                <?php echo ($i > 0 ? " / " : ""); ?>
                <?php echo t($input['label']);?><?php echo (isset($input['mandatory']) && $input['mandatory'] == true ? "*" : ""); ?>
                <?php $i++; ?>
                <?php } ?>
                <?php } ?>
                </span>
            </div>
            <div class="inputs">
                <?php foreach ($elements as $input) { ?>
                <?php $input['name'] = urlencode($input['name']); ?>
                <?php
                    if ($input['clear']) {
                        $form_data[$input['name']] = "";
                    }

                    $str_attributes = "";

                    if (isset($input['attributes'])) {
                        // echo implode(" ", array_map(function ($v, $k) { return ($k . "=" . $v); }, $input), array_keys($input));
                        $str_attributes = implode(" ", array_map(function ($v, $k) { return "{$k}=\"{$v}\""; }, $input['attributes'], array_keys($input['attributes'])));
                    }

                ?>
                <div class="input-<?php echo (isset($input['width']) && !empty($input['width']) ? $input['width'] : "100");?>">
                    <?php if ($input['type'] == "select") { ?>
                    <select type="text" name="<?php echo $input['name'];?>" id="<?php echo $input['name'];?>" class="<?php echo (isset($is_error[$input['name']]) && $is_error[$input['name']] === true ? "error" : "");?>" <?php echo $str_attributes;?>>
                        <option value=""></option>
                        <?php if (isset($input['options'])) { ?>
                        <?php foreach ($input['options'] as $key => $val) { ?>
                        <option value="<?php echo $key;?>"<?php echo ($form_data[$input['name']] == $key ? " SELECTED" : "");?>><?php echo $val;?></option>
                        <?php } ?>
                        <?php } ?>
                    </select>
                    <?php } elseif ($input['type'] == "textarea") { ?>
                    <textarea name="<?php echo $input['name'];?>" id="<?php echo $input['name'];?>" class="<?php echo (isset($is_error[$input['name']]) && $is_error[$input['name']] === true ? "error" : "");?>" <?php echo $str_attributes;?>><?php echo $form_data[$input['name']];?></textarea>
                    <?php } elseif ($input['type'] == "text" || $input['type'] == "hidden") { ?>
                    <input type="<?php echo $input['type'];?>" value="<?php echo $form_data[$input['name']];?>" name="<?php echo $input['name'];?>" id="<?php echo $input['name'];?>" class="<?php echo (isset($is_error[$input['name']]) && $is_error[$input['name']] === true ? "error" : "");?>"  <?php echo $str_attributes;?>/>
                    <?php } elseif ($input['type'] == "html") { ?>
                    <?php echo (isset($input['code']) ? $input['code'] : "");?>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
        <div class="row">&nbsp;</div>
        <div class="row single">
            <input type="submit" value="<?php echo t("Kontaktformular.Senden");?>" class="submit" />
        </div>
    </div>
</form>
<div style="clear: both;"></div>
<script type="text/javascript">
    $(function() {
        $( "#Anreise" ).datepicker({minDate: new Date(<?php echo (time() * 1000);?>), dateFormat: "dd.mm.yy"});
    });

    $(function() {
        $( "#Abreise" ).datepicker({minDate: new Date(<?php echo (time() * 1000);?>), dateFormat: "dd.mm.yy"});
    });

    $(document).ready(function() {
        $("#Anreise").on("change", function() {
            // console.log($( this ).datepicker( "getDate" ));

            $( "#Abreise" ).datepicker( "option", "minDate", $( this ).datepicker( "getDate" ) );
        });
    });
</script>