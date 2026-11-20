<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Fewobacher;

use \Concrete\Core\Page\Controller\PageController;
use \Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller as Preisuebersicht;
use Loader;

class Ajax extends PageController
{
    /**
     * c5 method on_start
     * fill the default values
     *
     * @return null
     */
    public function on_start()
    {
        parent::on_start();

        // Prüfe ob tatsächlich ein Ajax-Call
        // if (!(isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest")) {
        //     header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
        //     die();
        // }
    }

    public function on_before_render()
    {
        parent::on_before_render();

    }

    public function preisrechner($iListe)
    {
        if (is_string($iListe) && $iListe == "extern") {
            // Hole die Ergebnisse von einer externen Quelle
            $arResult = json_decode(file_get_contents(trim($this->get("url")."?start=".$this->get("start")."&ende=".$this->get("ende")."&personen=".$this->get("personen")."&deposit=0")));

            if ($arResult->status == true) {
                $arResult->status = "ok";
            } else {
                $arResult->status = "error";
            }

            header("Content-type: application/json");
            echo json_encode($arResult);
            die();
        }

        $sStart     = sprintf("%s", $this->get("start"));
        $sEnde      = sprintf("%s", $this->get("ende"));
        $iPersonen  = (int)$this->get("personen");
        $cModus     = sprintf("%s", $this->get("modus"));
        $bLimits    = (isset($_REQUEST['limits']) && $_REQUEST['limits'] == "no" ? false : true);
        $bCleaning  = (isset($_REQUEST['cleaning']) ? ($_REQUEST['cleaning'] == "yes" ? true : false) : true);
        $fDiscount  = array("per_rental" => (isset($_REQUEST['discount'])    && $_REQUEST['discount']    > 0 ? $_REQUEST['discount']    : 0),
                            "per_night"  => (isset($_REQUEST['discount_pn']) && $_REQUEST['discount_pn'] > 0 ? $_REQUEST['discount_pn'] : 0),
                            "options"    => array(
                                    "per_night_type" => (isset($_REQUEST['discount_pn_type']) && !empty($_REQUEST['discount_pn_type']) ? $_REQUEST['discount_pn_type'] : 'total'),
                                    "per_night_start" => (isset($_REQUEST['discount_pn_start']) && !empty($_REQUEST['discount_pn_start']) ? $_REQUEST['discount_pn_start'] : 'total')
                                )
                            );
        $fDeposit   = (isset($_REQUEST['deposit']) && $_REQUEST['deposit'] > 0 ? $_REQUEST['deposit'] : 0);
        $arFreeform = (isset($_REQUEST['freeform_label']) && !empty($_REQUEST['freeform_label']) && isset($_REQUEST['freeform_value']) ? array("label" => $_REQUEST['freeform_label'], "value" => $_REQUEST['freeform_value']) : array());

        $arResult = Preisuebersicht::calculate($iListe, $sStart, $sEnde, $iPersonen, $cModus, $bLimits, $bCleaning, $fDiscount, $fDeposit, $arFreeform);

        // Auslesen der Preisliste und Zusammenstellung der Preismatrix
        header("Content-type: application/json");

        echo json_encode($arResult);
        die();
    }
}
