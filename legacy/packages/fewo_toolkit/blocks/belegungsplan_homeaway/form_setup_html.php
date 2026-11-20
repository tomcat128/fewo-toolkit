<?php defined('C5_EXECUTE') or die(_("Access Denied.")) ?>
<style type="text/css">
.edit-belegungsplan input[type=text] {
    width: 100%;
}
</style>
<div class="ccm-ui edit-belegungsplan">
    <div class="alert-message block-message info">
        Bitte geben Sie hier die URL zum ICS-Feed von Fewo-Direkt und die Zeit bis zur nächsten Aktualisierung ein.
    </div>
    <br />

    <strong>ICS-Feed</strong><br/>
    <input type="text" name="ics_feed" value="<?php echo $controller->getFeed()?>" />
    <br />
    <em style="font-size: 9px;">Beispiel: http://www.fewo-direkt.de/icalendar/9440d7eb261a48359d8ac8f7fef8c8ab.ics</em>
    <br /><br />
    
    <strong>Zeit zur nächsten Aktualisierung (in Minuten)</strong><br/>
    <input type="text" name="time_refresh" value="<?php echo $controller->getRefresh()?>" />
    <br />
    <em style="font-size: 9px;">Tipp: Es sollte zusätzlich der entsprechende Cronjob aktiviert werden. Die Aktualisierung dieses Cronjobs sollte ein kürzeres Interval haben als hier angegeben (am besten nur die Hälfte). Also wenn der Cronjob alle 15 Minuten startet, dann sollte man hier 30 Minuten eintragen.</em>
    <br /><br />
    
    <strong>Anzahl Monate welche anfänglich dargestellt werden</strong><br/>
    <input type="text" name="display_start" value="<?php echo $controller->getMonthsStart()?>" />
    <br />
    <em style="font-size: 9px;">Tipp: Es sollte zusätzlich der entsprechende Cronjob aktiviert werden. Die Aktualisierung dieses Cronjobs sollte ein kürzeres Interval haben als hier angegeben (am besten nur die Hälfte). Also wenn der Cronjob alle 15 Minuten startet, dann sollte man hier 30 Minuten eintragen.</em>
    <br /><br />
    
    <strong>Anzahl Monate die maximal dargestellt werden</strong><br/>
    <input type="text" name="display_max" value="<?php echo $controller->getMonthsMax()?>" />
    <br />
    <em style="font-size: 9px;">Tipp: Es sollte zusätzlich der entsprechende Cronjob aktiviert werden. Die Aktualisierung dieses Cronjobs sollte ein kürzeres Interval haben als hier angegeben (am besten nur die Hälfte). Also wenn der Cronjob alle 15 Minuten startet, dann sollte man hier 30 Minuten eintragen.</em>
    <br /><br />

    <strong>Abstand am Anfang einfügen</strong><br/>
    <input type="checkbox" name="top_br" value="Y" <?php echo $controller->getTopBr() == "Y" ? "checked=\"checked\"" : "";?> /> Fügt &lt;br /&gt; an den Anfang des Blocks ein
    <br /><br />
    
    <?php
    if (file_exists($controller->pathToFeed()))
    {
        $time = (time() - filemtime($controller->pathToFeed()));
    ?>
    <strong>Aktuelle Daten</strong><br/>
    <input type="checkbox" name="force_refresh" value="true" />&nbsp;Belegungsplan jetzt aktualisieren (aktualisiert vor <?php echo floor($time / 60);?>m <?php echo str_pad($time % 60, 2, "0", STR_PAD_LEFT);?>s)
    <br /><br />
    <?php            
    }
    ?>
</div>