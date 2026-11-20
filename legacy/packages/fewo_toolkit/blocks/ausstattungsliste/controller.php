<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Ausstattungsliste;

use Loader;
use Page;
use BlockType;
use \Concrete\Core\Block\BlockController;

require_once dirname(__FILE__)."/ausstattung.php";

define("FWB_TABLE_AUSSTATTUNG", "btFwbAusstattung");
define("FWB_TABLE_AUSSTATTUNG_LISTE", "btFwbAusstattungsliste");
define("FWB_TABLE_AUSSTATTUNG_KOMMENTARE", "btFwbAusstattungslisteKommentare");

class Controller extends BlockController
{
    protected $btTable = FWB_TABLE_AUSSTATTUNG; // Die Haupttabelle
    protected $btInterfaceWidth = "500";
    protected $btInterfaceHeight = "500";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(  FWB_TABLE_AUSSTATTUNG,
                                        FWB_TABLE_AUSSTATTUNG_LISTE,
                                        FWB_TABLE_AUSSTATTUNG_KOMMENTARE);

    // Sammlung der Elemente
    private $ausstattung;

    /**
     * Class Constructor: Loads all data
     */
    public function __construct($obj = null)
    {
        parent::__construct($obj);
        
        $c = Page::getCurrentPage();
        
        if (is_object($c)) {
            $this->cID = $c->getCollectionID();
        }

        if ($this->bID) {
            $db = Loader::db();
            $v = array($this->bID);
            $q = "SELECT a.aID, a.ausstattung, b.kommentar, a.sortierung".
                 " ".
                 "FROM `".FWB_TABLE_AUSSTATTUNG_LISTE."` AS a".
                 " ".
                 "LEFT JOIN `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` AS b ON a.aID = b.aID".
                 " ".
                 "WHERE a.bID = ?".
                 " ".
                 "ORDER BY a.sortierung".
                 " ".
                 "asc";

            $r = $db->query($q, $v);
            $this->ausstattung = array();
            if ($r) {
                while ($row = $r->fetch()) {
                    
                    $this->ausstattung[] = new Gegenstand(
                        (int)$row['aID'],
                        $row['ausstattung'],
                        (int)$row['sortierung'],
                        $row['kommentar']
                    );
                }
            }
        }
    }

    /**
     * C5-Standard method
     */
    public function getBlockTypeName()
    {
        return 'Ausstattungsliste';
    }

    /**
     * C5-Standard method
     */
    public function getBlockTypeDescription()
    {
        return 'Eine einfache Ausstattungsliste';
    }

    /**
     * Returns name of the current list
     */
    public function getBezeichnung()
    {
        return $this->liste;
    }

    /**
     * Returns a list of items
     */

    public function getAusstattungsliste()
    {
        return $this->ausstattung;
    }

    /**
     * Checks if option is activated
     */
    public function getTopBr()
    {
        return $this->top_br;
    }

    /**
     * C5-Standard method: Wird aufgerufen wenn der Block gespeichert wird und zuvor dupliziert werden muss (Version!)
     */
    public function duplicate($newBID)
    {
        $db = Loader::db();
        
        if (is_array($this->ausstattung)) {
            foreach ($this->ausstattung as $item) {
                $v1 = array($newBID, $item->getName(), $item->getSortierung());
                $q1 = "INSERT INTO `".FWB_TABLE_AUSSTATTUNG_LISTE."` (bID, ausstattung, sortierung) VALUES (?, ?, ?)";
                $db->query($q1, $v1);
                
                if ($item->getKommentar()) {
                    $newOptionID = $db->Insert_ID(); // In diesem Fall die aID

                    // Kommentar einfügen
                    $v2 = array($newOptionID, $newBID, $item->getKommentar());
                    $q2 = "INSERT INTO `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` (aID, bID, kommentar) VALUES (?, ?, ?)";

                    $db->query($q2, $v2);
                }
            }
        }
        
        return parent::duplicate($newBID);
    }

    /**
     * C5-Standard method: Wird aufgerufen wenn der Block gespeichert wird
     */
    public function save($args)
    {
        if (!isset($args['top_br'])) {
            $args['top_br'] = "N";
        }

        parent::save($args);

        $db = Loader::db();

        if (!is_array($args['optionName'])) {
            $args['optionName'] = array();
        }
        
        $c = 0;

        $slashedArgs = $slashedUpdates = $removeComments = array();
        // echo "<pre>";
        // var_dump($args);
        // echo "</pre>";
        // die();

        for ($i = 0; $i < count($args['optionName']); $i++) {
            $arg    = $args['optionName'][$i];
            $update = $args['optionUpdate'][$i];

            $slashedArgs[] = addslashes($update);

            if ($update !== $arg) {
                $slashedUpdates[addslashes($arg)] = addslashes($update);
            }

            if (isset($args['optionNew'][$i]) && $args['optionNew'][$i] == 1) {
                // echo "!";
                // Insert into DB...
                $v1 = array($this->bID, addslashes(!empty($update) ? $update : $arg), $i);
                $q1 = "INSERT INTO `".FWB_TABLE_AUSSTATTUNG_LISTE."` (bID, ausstattung, sortierung) VALUES (?, ?, ?)";
                $db->query($q1, $v1);
            }
        }

        // Update der Bezeichnungen, falls vorhanden
        if (is_array($slashedUpdates)) {
            foreach ($slashedUpdates as $key => $value) {
                $v = array($this->bID);
                $q = "UPDATE `".FWB_TABLE_AUSSTATTUNG_LISTE."`".
                     " ".
                     "SET `ausstattung` = '{$value}'".
                     " ".
                     "WHERE (`ausstattung` = '{$key}' AND `bID` = ?)";

                $db->query($q, $v);
            }
        }
        
        // die();
        // Alle loeschen und dann neu einfuegen!
        $db->query(
            "DELETE FROM `".FWB_TABLE_AUSSTATTUNG_LISTE."`".
            " ".
            "WHERE `ausstattung` NOT IN ('".implode("','", $slashedArgs)."') AND bID = ".intval($this->bID)
        );

        // if (is_array($args['optionNew'])) {
        //     $displayOrder = count($slashedArgs);
        //     $i = 0;
        //     foreach ($args['optionNew'] as $optionName) {
        //         $v1 = array($this->bID, $optionName, $displayOrder + $i);
        //         $q1 = "INSERT INTO `".FWB_TABLE_AUSSTATTUNG_LISTE."` (bID, ausstattung, sortierung) VALUES (?, ?, ?)";
        //         $db->query($q1, $v1);
        //         $displayOrder++;
        //     }
        // }

        // Jetzt noch die Sortierung einfügen
        if (is_array($args['optionOrder'])) {
            foreach ($args['optionOrder'] as $key => $value) {
                $v = array($this->bID);
                $q = "UPDATE `".FWB_TABLE_AUSSTATTUNG_LISTE."`".
                     " ".
                     " SET `sortierung` = ".$value.
                     " ".
                     "WHERE (`ausstattung` = '{$key}' AND `bID` = ?)";
                
                $db->query($q, $v);
            }
        }

        // Jetzt die Kommentare... lese dazu die vorhandenen / aktuellen Kommentare ein (auch nachdem alle Datensätze
        // aktualisiert und neue eingefügt wurden)
        $v = array($this->bID);
        $q = "SELECT a.aID, a.ausstattung, b.cID, b.kommentar".
             " ".
             "FROM `".FWB_TABLE_AUSSTATTUNG_LISTE."` AS a".
             " ".
             "LEFT JOIN `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` AS b ON a.aID = b.aID".
             " ".
             "WHERE a.bID = ?";
        $r = $db->query($q, $v);

        if ($r) {
            while ($row = $r->fetch()) {
                $new_comment = $args['optionComment'][$row['ausstattung']];

                if (empty($new_comment) && !empty($row['kommentar'])) {
                    // Dann lösche den Kommentar
                    $v1 = array($this->bID, $row['cID']);
                    $q1 = "DELETE FROM `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` WHERE bID = ? AND cID = ?";
                    $db->query($q1, $v1);
                } elseif (!empty($new_comment) && !empty($row['kommentar']) && $new_comment != $row['kommentar']) {
                    // Dann aktualisere den Kommantar
                    $v1 = array(addslashes($new_comment), $this->bID, $row['cID']);
                    $q1 = "UPDATE `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` SET `kommentar` = ? WHERE bID = ? AND cID = ?";
                    $db->query($q1, $v1);
                } elseif (!empty($new_comment) && empty($row['kommentar'])) {
                    // Einen neuen Kommentar einfügen!
                    $v1 = array($row['aID'], $this->bID, addslashes($new_comment));
                    $q1 = "INSERT INTO `".FWB_TABLE_AUSSTATTUNG_KOMMENTARE."` (aID, bID, kommentar) VALUES (?, ?, ?)";
                    $db->query($q1, $v1);
                }
            }
        }
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset("javascript", "jquery");
    }
}
