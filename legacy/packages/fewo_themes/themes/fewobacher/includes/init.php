<?php
	switch ($_ENV['APP_ID'])
    {
        case 'SONNENSEELE':
            define("WEBSITE_ID", "sonnenseele");
            $type = "Ferienapartment";
            $fewo = "Sonnenseele";
            $travelers = 3;
            $location = "Allensbach-Hegne";
            break;
        case 'SEEHERZ':
            define("WEBSITE_ID", "seeherz");
            $type = "Ferienapartment";
            $fewo = "Seeherz";
            $travelers = 4;
            $location = "Reichenau";
            break;
        case 'SEEHERZCHEN':
            define("WEBSITE_ID", "seeherzchen");
            $type = "Ferienapartment";
            $fewo = "Seeherzchen";
            $travelers = 2;
            $location = "Reichenau";
            break;
        case 'SEESCHATZ':
            define("WEBSITE_ID", "seeschatz");
            $type = "Ferienwohnung";
            $fewo = "Seeschatz";
            $travelers = 4;
            $location = "Reichenau";
            break;
        default:
            $type = "undefined";
            $fewo = "undefined";
            break;
    }

    $path_to_tracking_code = dirname(__FILE__)."/tracking.".strtolower($fewo).".inc.php";

    if (file_exists($path_to_tracking_code))
    {
        $matomo_tracking_code = file_get_contents($path_to_tracking_code);
    }

