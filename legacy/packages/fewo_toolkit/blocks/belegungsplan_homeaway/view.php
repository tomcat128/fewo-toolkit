<?php
    $feed = $controller->loadData();

    $txtMonths = array( "01" => t("Belegungsplan.Januar"),
                        "02" => t("Belegungsplan.Februar"),
                        "03" => t("Belegungsplan.Maerz"),
                        "04" => t("Belegungsplan.April"),
                        "05" => t("Belegungsplan.Mai"),
                        "06" => t("Belegungsplan.Juni"),
                        "07" => t("Belegungsplan.Juli"),
                        "08" => t("Belegungsplan.August"),
                        "09" => t("Belegungsplan.September"),
                        "10" => t("Belegungsplan.Oktober"),
                        "11" => t("Belegungsplan.November"),
                        "12" => t("Belegungsplan.Dezember"));
    
    if ($controller->getTopBr() === "Y") {
        echo "<br />";
    }
?>
<a name="belegungsplan"></a>
<div class="belegungsplan">
    <?php
    // Initial-Werte fuer Monat und Jahr
    $time = time();

    $iMonth = (int) date("m", $time);
    $iYear  = (int) date("Y", $time);

    // Erstelle einen Kalender für 12 Monate ab dem heutigen Datum
    for ($i = 0; $i < $controller->getMonthsMax(); $i++) {
        // Darstellung eines Kalender-Monat
        $sMonth = str_pad($iMonth, 2, "0", STR_PAD_LEFT);

        echo "<div class=\"month".($i >= $controller->getMonthsStart() ? " month-hidden" : "")."\">";
        echo "<div class=\"description\">".$txtMonths[$sMonth]." ".$iYear."</div>";

        // 1. Zeile: Wochentage
        echo "<div class=\"weekdays title\"><div>".t("Belegungsplan.Mo")."</div><div>".t("Belegungsplan.Di")."</div><div>".t("Belegungsplan.Mi")."</div><div>".t("Belegungsplan.Do")."</div><div>".t("Belegungsplan.Fr")."</div><div>".t("Belegungsplan.Sa")."</div><div>".t("Belegungsplan.So")."</div></div>";

        // Jetzt die Zeilen mit den einzelnen Tagen

        // Aktuelle UNIX-Time für den ersten des Monats
        $month_time = mktime("12", "00", "00", $sMonth, "01", $iYear);

        // Ermittle den Starttag: 1 (für Montag) bis 7 (für Sonntag) -> ISO-8601
        $starting_day = ((int)date("N", $month_time) - 1); # Start beginnt mit 1, wir benoetigen aber eine 0
        
        // Start des Tages-Blocks
        echo "<div class=\"weekdays days\">";

        $cell = 0; // Der aktuelle Container in einem Monat (max. (6*7) = 42 Zellen)

        // Falls notwendig fuelle nun am Anfang leere Bloecke/Zellen ein
        while ($cell < $starting_day)
        {
            echo "<div class=\"leer\"></div>";

            $cell++;
        }

        $current_day = 1; $s_date_today = date("Ymd", time());

        // Jetzt die "echten" Tage, also existierende Kalendereintraege
        while ($current_day <= cal_days_in_month(CAL_GREGORIAN, $iMonth, $iYear))
        {
            $s_day = str_pad($current_day, 2, "0", STR_PAD_LEFT);

            $s_current_date = $iYear.$sMonth.$s_day;

            if ($s_current_date >= $s_date_today)
            {
                switch ($controller->getStatusDay($s_current_date))
                {
                    case 'occupied':
                        $cell_status = "belegt";
                        break;
                    case 'departure_free':
                        $cell_status = "belegt_frei";
                        break;
                    case 'free_arrival':
                        $cell_status = "frei_belegt";
                        break;
                    case 'departure_arrival':
                        // $cell_status = "belegt_belegt";
                        $cell_status = "belegt";
                        break;
                    case 'free':
                        $cell_status = "frei";
                        break;
                    case 'on_request':
                        $cell_status = "auf_anfrage";
                        break;
                    case 'free_on_request':
                        $cell_status = "frei_auf_anfrage";
                        break;
                    case 'on_request_free':
                        $cell_status = "auf_anfrage_frei";
                        break;
                    case 'departure_on_request':
                        $cell_status = "belegt_auf_anfrage";
                        break;
                    case 'on_request_arrival':
                        $cell_status = "auf_anfrage_belegt";
                        break;
                    default:
                        $cell_status = "frei";
                        break;
                }
            } else {
                $cell_status = "nicht_buchbar";
            }

            echo "<div class=\"{$cell_status}\">{$s_day}</div>";
            
            $current_day++;

            $cell++;
        }

        // Am Ende noch die restliche Zellen auffuellen, damit dies 6 Zellen ergeben
        while ($cell < (6*7))
        {
            echo "<div class=\"leer\"></div>";

            $cell++;
        }

        // Ende eines Tages-Blocks
        echo "</div>";

        // Ende eines Monats-Blocks
        echo "</div>";

        if ($i === 3) // Nach 4 Monaten
        {
            echo "<div class=\"show_all_row\">";
            echo "<a href=\"\" id=\"cal_show_all\">Jahresansicht zeigen</a>";
            echo "</div>";
        }

        $iMonth++;

        if ($iMonth > 12)
        {
            $iMonth = 1;
            $iYear ++;
        }
    }
    ?>
</div>
<div class="legend">
    <h3>Legende:</h3>
    <ul>
        <li><div class="frei"></div>&nbsp;Frei / Buchbar</li>
        <li><div class="belegt"></div>&nbsp;Belegt</li>
        <li><div class="auf_anfrage"></div>&nbsp;Auf Anfrage (bitte kontaktieren Sie uns)</li>
    </ul>
</div>
<div class="clearfix"></div>
<script type="text/javascript">
    $("#cal_show_all").on("click", function(e) {
        e.preventDefault();

        $("#cal_show_all").parent().remove();
        $(".month-hidden").toggleClass("month-hidden");
    });
</script>