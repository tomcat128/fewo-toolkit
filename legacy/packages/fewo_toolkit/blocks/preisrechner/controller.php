<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Preisrechner;

use \Concrete\Core\Block\BlockController;
use \Concrete\Package\FewoToolkit\Controller as FewoToolkit;
use \Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller as Preisuebersicht;
use Loader;

class Controller extends BlockController
{
    /* DB-Tabelle(n) deklarieren */
    const DB_TABLE_MAIN = "btFwbPreisrechner";

    protected $btTable = self::DB_TABLE_MAIN; // Die Haupttabelle
    protected $btInterfaceWidth = "420";
    protected $btInterfaceHeight = "350";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(self::DB_TABLE_MAIN);
    
    public $ftSystemDir = FewoToolkit::SYSTEM_DIRECTORY;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

    }

    public function on_before_render()
    {
        $oPreisliste = array("0" => "");
        $oMaxGuests = array();

        $db = Loader::db();
        
        $q = "SELECT * FROM `".( Preisuebersicht::DB_TABLE_CONFIG )."` ORDER BY `name`";

        $r = $db->query($q);
        
        while ($row = $r->fetch()) {
            $oPreisliste[$row['listID']] = $row['name'];

            if (isset($this->preisliste) && $this->preisliste == $row['listID']) {

                for ($g = 1; $g <= (int)$row['anzahl_gaeste']; $g++) {
                    $oMaxGuests[] = $g;
                }
            }
        }

        $this->set("oPreisliste", $oPreisliste);
        $this->set("oMaxGuests", $oMaxGuests);

        // var_dump($oPreisliste); die();

        $oModus = array("T" => "Am Tag der Anreise", "N" => "Nächster Tag (nach der Anreise)");

        $this->set("oModus", $oModus);
    }

    public static function getTable()
    {
        return DB_TABLE_MAIN;
    }

    /* Fuer C5-Backend */
    public function getBlockTypeName()
    {
        return "Preisrechner";
    }

    /* Fuer C5-Backend */
    public function getBlockTypeDescription()
    {
        return "Preisrechner mit einer Preisliste verknüpfen.";
    }

    public function registerViewAssets($outputContent = '') {
        $html = Loader::helper('html');
        
        // Standard CSS/JS
        $this->addHeaderItem($html->javascript("jquery.js"));
        $this->addHeaderItem($html->css("jquery-ui.css"));
        $this->addHeaderItem($html->javascript("jquery-ui.js"));
        // $this->addHeaderItem($html->javascript("i18n/ui.datepicker-de.js"));
        $this->addFooterItem($html->javascript("i18n/jquery-ui/datepicker-de.js"));
    }
}
