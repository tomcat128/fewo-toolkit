<?php
namespace Concrete\Package\FewoToolkit\Job;

use Loader;
use \Job as AbstractJob;
use Concrete\Package\FewoToolkit\Block\BelegungsplanHomeaway\Controller as BpHaCtrl;

class BelegungsplanHomeaway extends AbstractJob
{
    public function getJobName()
    {
        return t("Belegungsplan (Homeaway)");
    }

    /** Returns the job description.
    * @return string
    */
    public function getJobDescription()
    {
        return t("Regelmaessige Updates des Belegungskalenders");
    }

    /** Executes the job.
    * @return string Returns a string describing the job result in case of success.
    * @throws Exception Throws an exception in case of errors.
    */
    public function run()
    {
        try {
            $db = Loader::db();

            // Hole die DB-Tabelle
            $db_table = BpHaCtrl::getTable();

            // Hole den Temp-Pfad zu den ICS-Dateien
            $tmp_path = BpHaCtrl::getTempPath();

            // Finde nur genau die Blöcke, welche momentan aktiv sind
            $q = "SELECT DISTINCT a.`ics_feed` ".
                 "FROM `".$db_table."` AS a, ".
                 "     `Blocks` AS b ".
                 "WHERE a.`bID` = b.`bID` AND ".
                 "      b.`bIsActive` = 1";

            $r = $db->query($q);

            $i = 0;

            if ($r) {
                while ($row = $r->fetch()) {
                    $arrContextOptions = array(
                        "ssl"=>array(
                            "verify_peer"=>false,
                            "verify_peer_name"=>false,
                        ),
                    );
                    $download_data = file_get_contents($row['ics_feed'], false, stream_context_create($arrContextOptions));

                    // Download der aktuellen ICS-Datei
                    if ((strlen($download_data) > 0) && (@file_put_contents($tmp_path."/".basename($row['ics_feed']), $download_data) == true)) {
                        $i++;
                    }
                }
            }

            return t("Anzahl der aktualisierten Homeaway-Kalender: %s", $i);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
