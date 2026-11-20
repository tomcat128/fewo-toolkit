<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Preisuebersicht;

use Loader;
use Page;
use BlockType;
use \Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    const DB_TABLE_MAIN = "btFwbPreisliste";
    const DB_TABLE_CONFIG = "btFwbPreislisteConfig";
    const DB_TABLE_MATRIX = "btFwbPreislisteMatrix";

    protected $btTable = self::DB_TABLE_MAIN; // Die Haupttabelle
    protected $btInterfaceWidth = "400";
    protected $btInterfaceHeight = "250";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(self::DB_TABLE_MAIN, self::DB_TABLE_CONFIG, self::DB_TABLE_MATRIX);

    public function __construct($obj = null)
    {
        parent::__construct($obj);
    }

    public function getListID()
    {
        return $this->liste;
    }

    /**
     * Returns a valid list of available prices
     */
    public static function getAvailableLists()
    {
        $list = array();

        $db = Loader::db();

        $q = "SELECT".
             " ".
             "*".
             " ".
             "FROM `".self::DB_TABLE_CONFIG."`".
             " ".
             "ORDER BY `name`".
             " ".
             "ASC";

        $r = $db->query($q);

        while ($row = $r->fetch()) {
            
            $list[] = $row;
        }

        return $list;
    }

    public static function getPricelistById($id)
    {
        $pricelist = array();

        if ($id > 0) {
            // Lade nun eine Liste der vorhandenen Preislisten
            $db = Loader::db();

            $q = "SELECT * FROM `".self::DB_TABLE_CONFIG."` WHERE `listID` = ".$id;

            $r = $db->query($q);

            if ($row = $r->fetch()) {
                // Die Konfiguration
                $pricelist['config'] = $row;

                $q = "SELECT * FROM `".self::DB_TABLE_MATRIX."`".
                     " WHERE `listID` = ".$id." AND `datum_bis` >= '".date("Y-m-d", time())."'".
                     " ORDER BY `datum_von`";

                $r = $db->query($q);

                // Die einzelnen Saisonpreise
                while ($row = $r->fetch()) {
                    // Preis pro Nacht je Anzahl Reisender
                    $temp = unserialize($row['preis_pro_nacht']);
                    
                    $temp_prices = array();

                    foreach ($temp as $persons => $price) {
                        $persons = (float)$persons;

                        if (!isset($temp_prices[$price])) {
                            $temp_prices[$price]['min'] = $persons;
                            $temp_prices[$price]['max'] = $persons;
                        } else {
                            $temp_prices[$price]['min'] = min($temp_prices[$price]['min'], $persons);
                            $temp_prices[$price]['max'] = max($temp_prices[$price]['max'], $persons);
                        }
                    }

                    unset($row['preis_pro_nacht']);

                    $row['preise'] = $temp_prices;

                    $pricelist['matrix'][] = $row;
                }

            }
        }

        return $pricelist;
    }

    public function getPricelist($id = null)
    {
        if ($id === null) {
            $id = (int)$this->liste;
        } elseif (!isset($this->liste)) {
            return array();
        }

        return self::getPricelistById($id);
    }
    
    public function getTopBr()
    {
        return $this->top_br;
    }

    public function getBlockTypeName()
    {
        return "Preisübersicht";
    }

    public function getBlockTypeDescription()
    {
        return "Eine Übersichtstabelle über die Saisonpreise";
    }

    public function save($args)
    {
        if (!isset($args['top_br'])) {
            $args['top_br'] = "N";
        }
        
        parent::save($args);
    }

    /**
     * Static Import-Funktion fuer XMLs von anderen Stellen
     */
    public static function importById($id)
    {
        $pl = self::getPricelistById($id);
        
        if (isset($pl['config']['settings'])) {
            $settings = unserialize($pl['config']['settings']);

            $url = "";

            if (isset($settings['import']['url'])) {
                $url .= $settings['import']['url'];
            }

            // Falls vorhanden, fuege das Passwort an
            if (!empty($settings['import']['password'])) {
                $url .= "/";
                $url .= $settings['import']['password'];
            }

            // http://www.ferienwohnung-hegne.de/pricelist.xml
            if ($data = file_get_contents($url)) {
                $db = Loader::db();
                
                $xml = new \SimpleXMLElement($data);

                if (isset($xml['data']) && $xml['data'] == "pricelist") {

                    // Query delete from
                    $q = "DELETE FROM `".self::DB_TABLE_MATRIX."` WHERE `listID` = ".$id;
                    // Exec query
                    $r = $db->query($q);

                    // Meta-Daten der Preisliste
                    $q = "UPDATE `".self::DB_TABLE_CONFIG."` SET `gebuehr_endreinigung` = ?, `mindestaufenthaltsdauer` = ?, `anzahl_gaeste` = ?, `anzahlung` = ?  WHERE `listID` = ?";
                    $v = array((float)$xml->pricelist->cleaning, (int)$xml->pricelist->min_stay, (int)$xml->pricelist->max_guests, (int)$xml->pricelist->commission, (int)$id);

                    // Exec query
                    $r = $db->query($q, $v);

                    foreach ($xml->seasons->season as $season) {
                        
                        $prices = array();

                        foreach ($season->prices as $price) {
                            $prices[sprintf("%s", $price->persons)] = sprintf("%s", $price->value);
                        }

                        // Preisliste schreiben
                        $q = "INSERT INTO `".self::DB_TABLE_MATRIX."` (`listID`, `datum_von`, `datum_bis`, `bezeichnung`, `preis_pro_nacht`) VALUES (?, ?, ?, ?, ?)";
                        $v = array((int)$id, trim($season->date_start), trim($season->date_end), trim($season->name), serialize($prices));

                        // Exec query
                        $r = $db->query($q, $v);
                    }
                }
            }
        }
    }

    /**
     * Static Import-Funktion fuer XMLs von anderen Stellen
     */
    public static function importFromUrl($url, $password)
    {
        echo $url;
        

    }

    /**
     * Beschreibung: Wichtige und komplexe Static-Function zur Berechnung des Mietpreises anhand gegebener Parameter.
     *
     * @param string liste_id
     * @param string date_start
     * @param string date_end
     * @param int personen
     * @param char modus
     * 
     * @return mixed $results
     */
    public static function calculate($iListe, $sStart, $sEnde, $iPersonen, $cModus, $bLimits = true, $bCleaning = true, $arDiscount = null, $fDeposit = 0, $arFreeform = null)
    {
        $iMaxGuests = $iMinNights = 0;

        try {
            if (count($arDiscount) == 0) {
                $arDiscount = array("per_rental" => 0, "per_night" => 0);
            }

            if (empty($sStart)) {
                throw new \Exception(t("Preisuebersicht.StartdatumUngueltig"));
            }

            if (empty($sEnde)) {
                throw new \Exception(t("Preisuebersicht.EnddatumUngueltig"));
            }

            if (empty($iPersonen) || !($iPersonen > 0)) {
                throw new \Exception(t("Preisuebersicht.AnzahlReisende"));
            }

            if (empty($cModus) || ($cModus != "T" && $cModus != "N")) {
                throw new \Exception("Es wurde kein gültiger Berechnungsmodus (T/N) angegeben.");
            }

            // Vorabüberprüfungen abgeschlossen
            $dStart = new \DateTime($sStart);
            $dEnde  = new \DateTime($sEnde);

            if ($bLimits == true) {
                if ($dStart->format("Y-m-d") < date("Y-m-d", time())) {
                    throw new \Exception(t("Preisuebersicht.AnreiseVergangen"));
                }

                if ($dStart->format("U") >= $dEnde->format("U")) {
                    throw new \Exception(t("Preisuebersicht.AbreiseVorAnreise"));
                }
            }

            $preisliste = self::getPricelistById($iListe);

            if (count($preisliste) === 0) {
                throw new \Exception("Die ID ist fehlerhaft / Keine gültige Preisliste gefunden.");
            }

            $iNights = (int) (( (int)$dEnde->format("U") - (int)$dStart->format("U") ) / (24*60*60));

            if ($iNights < (int)$preisliste["config"]["mindestaufenthaltsdauer"] && $bLimits == true) {
                throw new \Exception(t("Preisuebersicht.Mindestaufenthaltsdauer", (int)$preisliste["config"]["mindestaufenthaltsdauer"]));
            }

            $iMaxGuests = (int)$preisliste["config"]["anzahl_gaeste"];

            $iCommission = (int)$preisliste["config"]["anzahlung"];

            if (!($iPersonen <= $iMaxGuests)) {
                throw new \Exception(t("Preisuebersicht.GaesteMaximal"));
            }

            // Erstelle jetzt ein Preisarray nach Datum
            $ar_mapping_dates = array();

            foreach ($preisliste["matrix"] as $index => $matrix) {

                $pStart = new \DateTime($matrix["datum_von"]);
                $pEnde  = new \DateTime($matrix["datum_bis"]);

                // Prüfung: Noch gültiger Zeitraum?
                if ($pEnde->format("Y-m-d") >= date("Y-m-d")) {

                    while ($pStart->format("Y-m-d") <= $pEnde->format("Y-m-d")) {
                        $ar_mapping_dates[$pStart->format("Y-m-d")] = $index;

                        $pStart->add(new \DateInterval("P1D"));
                    }
                }
            }

            // Jetzt noch prüfen ob gültige Zeiträume vorliegen
            if (!isset($ar_mapping_dates[$dStart->format("Y-m-d")])) {
                throw new \Exception(t("Preisuebersicht.AnreisedatumPreisliste"));
            }

            if (!isset($ar_mapping_dates[$dEnde->format("Y-m-d")])) {
                throw new \Exception(t("Preisuebersicht.AbreisedatumPreisliste"));
            }

        } catch (\Exception $e) {
            // Fehler ist aufgetreten
            echo json_encode(array("status" => "error", "message" => $e->getMessage()));
            die();
        }

        $arResult = array("status" => "ok", "amount" => 0.0, "nights" => $iNights, "guests" => $iPersonen, "commission" => $iCommission, "pricelist" => array());

        $nc = 1; // -> NightCounter

        // Jetzt den endültigen Preis berechnen
        while ($dStart->format("Y-m-d") < $dEnde->format("Y-m-d")) {

            $dMapping = clone $dStart;

            // Wenn Modus = N dann ist der darauffolgende Tag relevant für die Abrechnung
            if ($cModus == "N") {
                $dMapping->add(new \DateInterval("P1D"));
            }

            $i = $ar_mapping_dates[$dMapping->format("Y-m-d")];

            $discount_added = false;

            foreach ($preisliste["matrix"][$i]["preise"] as $fPrice => $data) {

                $dTomorrow = clone $dStart;
                $dTomorrow->add(new \DateInterval("P1D"));

                $fPrice = (float) $fPrice;

                if ($arDiscount['per_night'] > 0 && $nc >= $arDiscount['options']['per_night_start']) {
                    if ($arDiscount['options']['per_night_type'] === "night") {
                        $fPrice -= (float)$arDiscount['per_night'];
                    } else
                    if ($arDiscount['options']['per_night_type'] === "total" && $discount_added === false) {
                        if (!is_float($arDiscount['per_rental'])) {
                            $arDiscount['per_rental'] = (float) $arDiscount['per_rental'];
                        }
                        $arDiscount['per_rental'] += (float)$arDiscount['per_night'];

                        $discount_added = true;
                    }
                }
                
                if ($iPersonen >= $data["min"] && $iPersonen <= $data["max"]) {

                    $arResult["amount"] += $fPrice;

                    $arResult["pricelist"][] = array(   "type" => "season",
                                                        "name" => $preisliste["matrix"][$i]["bezeichnung"],
                                                        "date_from" => $dStart->format("d.m.Y"),
                                                        "date_to" => $dTomorrow->format("d.m.Y"),
                                                        "price" => $fPrice);
                }

            }

            // Ansonsten ist der Tag der Anreise ausschlaggebend, daher erst am Ende einen Tag addieren
            $dStart->add(new \DateInterval("P1D"));
            
            $nc++;
        }

        // Jetzt noch Gebühren oder ähnliches dazu
        $fCleaning = (float)$preisliste["config"]["gebuehr_endreinigung"];

        if ($fCleaning > 0.0 && $bCleaning) {
            $arResult["amount"] += $fCleaning;

            $arResult["pricelist"][] = array("type" => "cleaning", "name" => t("Preisuebersicht.Endreinigung"), "date" => $dStart->format("Y-m-d"), "price" => $fCleaning);
        }

        if ($arDiscount['per_rental'] > 0) {
            $arResult["amount"] -= (float)$arDiscount['per_rental'];

            $arResult["pricelist"][] = array("type" => "discount", "name" => t("Preisuebersicht.Rabatt"), "date" => $dStart->format("Y-m-d"), "price" => $arDiscount['per_rental']);
        }

        if (isset($arFreeform['label']) && !empty($arFreeform['label']) && isset($arFreeform['value']) && !empty($arFreeform['value']) && $arFreeform['value'] != 0) {
            $arResult["amount"] += (float)$arFreeform['value'];

            $arResult["pricelist"][] = array("type" => "freeform", "name" => $arFreeform['label'], "date" => $dStart->format("Y-m-d"), "price" => $arFreeform['value']);
        }

        if ($fDeposit > 0) {
            $arResult["amount"] += (float)$fDeposit;

            $arResult["pricelist"][] = array("type" => "deposit", "name" => t("Preisuebersicht.Kaution"), "date" => $dStart->format("Y-m-d"), "price" => $fDeposit);
        }

        return $arResult;
    }
}
