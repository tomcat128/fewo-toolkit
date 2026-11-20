<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\BelegungsplanHomeaway;

use Loader;
use Page;
use BlockType;
use \Concrete\Core\Block\BlockController;

define("FWB_TABLE_BELEGUNGSPLAN", "btFwbBelegungsplanHomeaway");

class Controller extends BlockController
{
    protected $btTable = FWB_TABLE_BELEGUNGSPLAN; // Die Haupttabelle
    protected $btInterfaceWidth = "420";
    protected $btInterfaceHeight = "400";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(FWB_TABLE_BELEGUNGSPLAN);

    // Sammlung der Elemente
    protected $ics_feed;
    protected $time_refresh;

    // Pfad zur aktuellen Daten-Datei
    private $ics_file;

    // Enthält die Belegungsdaten des Kalenders
    private $cal_data;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $c = Page::getCurrentPage();
        
        if (is_object($c)) {
            $this->cID = $c->getCollectionID();
        }

        $tmp_dir = self::getTempPath();

        $tmp_file = $tmp_dir."/".basename($this->getFeed());

        // Pfad zur aktuellen Datei
        $this->ics_file = $tmp_file;
    }

    public static function getTempPath()
    {
        if (isset($_SERVER['DOCUMENT_ROOT'])) {
            $tmp_dir .= $_SERVER['DOCUMENT_ROOT']."/application/files/blocks/belegungsplan/homeaway";
        } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
            $tmp_dir .= dirname($_SERVER['SCRIPT_FILENAME'])."/application/files/blocks/belegungsplan/homeaway";
        } else {
            $tmp_dir  = dirname(__FILE__)."/tmp_data";
        }

        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0777, true);
        }

        return $tmp_dir;
    }

    public static function getTable()
    {
        return FWB_TABLE_BELEGUNGSPLAN;
    }

    /**
     * Lädt die ICS-Daten von der aktuellen Url
     */
    public function loadData()
    {
        $remote_file = $this->getFeed();

        if (empty($remote_file)) {
            return;
        }

        $tmp_file = $this->ics_file;
        $tmp_dir  = dirname($tmp_file);

        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0777, true);
        }

        // Manuelles Update des Kalenders
        if (is_dir($tmp_dir)) {
            if (!file_exists($this->ics_file)
                ||
                (file_exists($this->ics_file)
                &&
                ((time() - filemtime($this->ics_file)) > ($this->getRefresh() * 60)))) {
                $feed_data = @file_get_contents($remote_file);

                file_put_contents($tmp_file, $feed_data);
            } elseif (file_exists($this->ics_file)) {
                $feed_data = @file_get_contents($this->ics_file);
            }

            if (!empty($feed_data)) {
                $this->parseData($feed_data);
            }
        } else {
            $error_file_io = true;
        }

        if ($error_file_io) {
            echo "<div><span style='color: red'>Fehler bei Vorgang mit Belegungsplan ";
            echo "im Dateisystem (konnte Verzeichnis/Datei nicht erstellen)</span></div>";
        }
    }

    /**
     * Die ICS-Daten wird hier nun aufbereitet und im Erfolgsfall wird true erwartet
     *
     * @param  string ics_data
     * @return bool
     */
    private function parseData($ics_data)
    {
        if (preg_match_all("/BEGIN:VEVENT(.*?)END:VEVENT/s", $ics_data, $matches)) {
            // print_r($matches); die();
            $sToday = date("Ymd", time());

            for ($i = 0; $i < count($matches[1]); $i++) {
                $row = $matches[1][$i];
                
                if (preg_match_all("/SUMMARY:([a-z0-9 ]{1,})/i", $row, $match)) {
                    $information = $match[1][0];
                } else {
                    $information = "";
                }

                if ($information == "Auf Anfrage")
                {
                    $onRequest = true;
                } else {
                    $onRequest = false;
                }

                if (preg_match_all("/(DTSTART|DTEND):([0-9]{8})/", $row, $day)) {
                    if ($day[1][0] == "DTSTART") {
                        $pStart = $day[2][0];#."T120000";
                        $pEnd   = $day[2][1];#."T120000";
                    } else {
                        $pStart = $day[2][1];#."T120000";
                        $pEnd   = $day[2][0];#."T120000";
                    }

                    // Ist das aktuelle Intervall aktuell? (liegt als nicht in der Vergangenheit)
                    if ($pEnd >= $sToday) {
                        $d = 0;
                        $dtp = new \DateTime($pStart);
                        
                        while ($dtp->format("Ymd") <= $pEnd) {
                            $cToday = $dtp->format("Ymd");

                            if (!isset($this->cal_data[$cToday])) {
                                $this->cal_data[$cToday][0] =    // Start des Tages
                                $this->cal_data[$cToday][1] = 0; // Ende vom Tag
                            }

                            if ($dtp->format("Ymd") === $pStart) {
                                if ($onRequest)
                                {
                                    $this->cal_data[$cToday][1] = 11;
                                } else {
                                    $this->cal_data[$cToday][1] = 1;
                                }
                            } elseif ($dtp->format("Ymd") === $pEnd) {
                                if ($onRequest)
                                {
                                    $this->cal_data[$cToday][0] = 11;
                                } else {
                                    $this->cal_data[$cToday][0] = 1;
                                }
                            } else {
                                if ($onRequest) {
                                    $this->cal_data[$cToday][0] = 12;
                                } else {
                                    $this->cal_data[$cToday][0] = 2;
                                }
                            }

                            $dtp->add(new \DateInterval("P01D"));

                            $d++;
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }

    public function getStatusDay($day)
    {
        if (isset($this->cal_data[$day])) {
            $data = $this->cal_data[$day];
            
            if ($data[0] === 2) {
                return "occupied";
            } elseif ($data[0] === 0 && $data[1] === 1) {
                return "free_arrival";
            } elseif ($data[0] === 1 && $data[1] === 0) {
                return "departure_free";
            } elseif ($data[0] === 1 && $data[1] === 1) {
                return "departure_arrival";
            } elseif ($data[0] === 0 && $data[1] === 0) {
                return "free";
            } elseif ($data[0] === 12) {
                return "on_request";
            } elseif ($data[0] === 0 && $data[1] === 11) {
                return "free_on_request";
            } elseif ($data[0] === 11 && $data[1] === 0) {
                return "on_request_free";
            } elseif ($data[0] === 1 && $data[1] === 11) {
                return "departure_on_request";
            } elseif ($data[0] === 11 && $data[1] === 1) {
                return "on_request_arrival";
            }
        }

        return "";
    }

    public function resetData()
    {
        if (file_exists($this->ics_file)) {
            // Daten-Datei löschen
            unlink($this->ics_file);

            // Und neu laden
            $this->loadData();
        }
    }

    public function pathToFeed()
    {
        return $this->ics_file;
    }

    /* Fuer C5-Backend */
    public function getBlockTypeName()
    {
        return "Belegungsplan (Homeaway)";
    }

    /* Fuer C5-Backend */
    public function getBlockTypeDescription()
    {
        return "Belegungsplan mit Bezug der Daten von Homeaway";
    }

    public function getFeed()
    {
        return (isset($this->ics_feed) ? $this->ics_feed : "http://");
    }

    public function getRefresh()
    {
        return (isset($this->time_refresh) ? $this->time_refresh : 15);
    }

    public function getMonthsStart()
    {
        return (isset($this->display_start) ? $this->display_start : 4);
    }

    public function getMonthsMax()
    {
        return (isset($this->display_max) ? $this->display_max : 12);
    }

    public function getTopBr()
    {
        return $this->top_br;
    }

    public function save($args)
    {
        if (!isset($args['top_br'])) {
            $args['top_br'] = "N";
        }
        
        parent::save($args);

        if (isset($args) && isset($args['force_refresh'])) {
            $this->resetData();
        }
    }
}
