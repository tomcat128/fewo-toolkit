<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<div class="ccm-ui">
    <strong>Preisliste auswählen</strong><br/>
    <?php
    $pl = $controller->getAvailableLists();

    if (count($pl) >= 1) {
        ?>
        <select name="liste">
            <option value="0"></option>
        <?php
        foreach ($pl as $item) {
            echo "<option";
            echo " ";
            echo " value=\"".$item['listID']."\"";
            echo ($controller->liste == $item['listID'] ? " selected=\"SELECTED\"" : "");
            echo ">";
            echo $item['name'];
            echo "</option>";
        }
        ?>
        </select>
    <?php
    } else {
        echo "<em>";
        echo "Aktuell sind noch keine Preislisten verfügbar. Diese kann hier erstellt und bearbeitet werden:";
        echo " ";
        echo "<a href=\"".URL::to("/dashboard/fewo_toolkit/pricelist")."\">";
        echo "Preisliste erstellen";
        echo "</a>";
        echo "</em>";
    }
    ?>
    <br /><br />
    
    <strong>Abstand am Anfang einfügen</strong><br/>
    <input type="checkbox" id="top_br" name="top_br" value="Y" <?php echo $controller->getTopBr() === "Y" ? "checked=\"checked\"" : "";?> /> <label for="top_br" style="font-weight: normal;">Fügt &lt;br /&gt; an den Anfang des Blocks ein</label>
    <br />
    
    <hr />
    Quick-Nav: <a href="<?php echo URL::to("/dashboard/fewo_toolkit/pricelist");?>">Preisliste bearbeiten</a>
</div>