<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Fewobacher;

use \Concrete\Core\Page\Controller\PageController;
use \Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller as Preisuebersicht;
use \Concrete\Vendors\TecnickCom\TCPDF\FEWOPDF as FEWOPDF;
use Loader;
use FileImporter;
use FileVersion;
use File;

class Pdf extends PageController
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

        // echo "123";

    }

    public function on_before_render()
    {
        parent::on_before_render();

        // echo "abc";
    }

    public function rechnung($iListe)
    {
        $this->preisliste($iListe, true);
    }

    public function preisliste($iListe, $bRechnung = false)
    {
        $tcpdf_rel = "vendor/tecnick.com/tcpdf/tcpdf.php";
        $tcpdf_lib = FWB_BASE_DIR."/".$tcpdf_rel;

        // Erstelle hier das PDF...
        if (!file_exists($tcpdf_lib)) {
            throw new \Exception(t("Klasse %s (%s) konnte nicht gefunden werden. Ist die Library installiert und wurde der Befehl \"composer install/update\" im Package-Root ausgeführt?", "TCPDF", $tcpdf_lib));
        }

        require_once $tcpdf_lib;
        require_once dirname(__FILE__)."/pdf/fewo_pdf.php";

        $pdf = new FEWOPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pricelist = Preisuebersicht::getPricelistById($iListe);

        $arSettings = unserialize($pricelist['config']['settings']);

        // var_dump($arSettings);

        if (isset($arSettings['pdf'])) {
            $pdf->setName(str_replace("{PRICELIST}", $pricelist['config']['name'], $arSettings['pdf']['name']));
            $pdf->setOwner($arSettings['pdf']['owner']);
            $pdf->setAddress($arSettings['pdf']['address']);
            $pdf->setHomepage($arSettings['pdf']['homepage']);
            $pdf->setEmail($arSettings['pdf']['email']);
            $pdf->setPhone($arSettings['pdf']['phone']);

            $pdf->setLogo(BASE_URL.File::getRelativePathFromID($arSettings['pdf']['logo']));

        }

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor("Fewobacher");
        $pdf->SetTitle(t("Preisanfrage")." Fewobacher");
        $pdf->SetSubject(t("Angebot / Preisanfrage"));

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, 58, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // ---------------------------------------------------------

        // set default font subsetting mode
        $pdf->setFontSubsetting(true);

        // Set font
        // dejavusans is a UTF-8 Unicode font, if you only need to
        // print standard ASCII chars, you can use core fonts like
        // helvetica or times to reduce file size.
        $pdf->SetFont('helvetica', '', 12, '', true);

        // http://localhost:90/index.php/fewobacher/ajax/preisrechner/1?start=20150710&ende=20150715&personen=3&modus=N
        // Anfrage zusammenbauen und abesenden...
        $sStart      = sprintf("%s", $this->get("start"));
        $sEnde       = sprintf("%s", $this->get("ende"));
        $iPersonen   = (int)$this->get("personen");
        $cModus      = sprintf("%s", $this->get("modus"));
        $bLimits     = sprintf("%s", $this->get("limits"));
        $bCleaning   = sprintf("%s", $this->get("cleaning"));
        $fDiscount   = sprintf("%s", $this->get("discount"));
        $fDiscountPn = sprintf("%s", $this->get("discount_pn"));
        $fDiscountPnType  = sprintf("%s", $this->get("discount_pn_type"));
        $fDiscountPnStart = sprintf("%s", $this->get("discount_pn_start"));
        $fDeposit    = sprintf("%s", $this->get("deposit"));
        $ff_label    = sprintf("%s", urlencode($this->get("freeform_label")));
        $ff_value    = sprintf("%s", $this->get("freeform_value"));

        if ($bRechnung) {
            // Keine Kaution in der Rechnung ausweisen.
            $fDeposit = 0;
        }

        $rqUrl = BASE_URL."/index.php/fewobacher/ajax/preisrechner/{$iListe}"
               . "?start={$sStart}"
               . "&ende={$sEnde}"
               . "&personen={$iPersonen}"
               . "&modus={$cModus}"
               . "&limits={$bLimits}"
               . "&cleaning={$bCleaning}"
               . "&discount={$fDiscount}"
               . "&discount_pn={$fDiscountPn}"
               . "&discount_pn_type={$fDiscountPnType}"
               . "&discount_pn_start={$fDiscountPnStart}"
               . "&freeform_label={$ff_label}"
               . "&freeform_value={$ff_value}"
               . "&deposit={$fDeposit}";

         // Test: http://localhost:90/index.php/fewobacher/pdf/preisliste/1?start=20150710&ende=20150715&personen=3&modus=N
        
        // Ist die aktuelle Anfrage per HTTP Basic Auth geschützt, die Zugangsdaten
        // des Aufrufers an den internen Ajax-Request weiterreichen (keine
        // Zugangsdaten im Code hinterlegen!).
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            $authHeader = "Basic " . base64_encode($_SERVER['PHP_AUTH_USER'] . ":" . $_SERVER['PHP_AUTH_PW']);
        }

        if (isset($authHeader)) {
            $context = stream_context_create(array(
                'http' => array(
                    'header'  => "Authorization: " . $authHeader
                )
            ));
        } else {
            unset($context);
        }

        if (isset($context)) {
            $res = file_get_contents($rqUrl, false, $context);
        } else {
            $res = file_get_contents($rqUrl);
        }

        $json = json_decode($res);

        if ($json->status !== "ok") {
            throw new \Exception(t("Fehler beim erstellen des Angebots-PDF: %s", $json->message));
        }

        // Add a page
        // This method has several options, check the source code documentation for more information.
        $pdf->AddPage();

        $iCommission = $json->commission; // Prozentual

        // Berechnung der Anzahlung und des Restbetrages. Anzahlung zur Sicherheit immer abrunden!
        $fPartial = (($json->amount - $fDeposit) / 100 * $iCommission);

        $tblRows = ""; $n = 0; $price_all = 0;

        $dateStart = $dateEnde = "";

        foreach ($json->pricelist as $row)
        {
            if ($row->discount > 0)
            {
                $price = ($row->price - $row->discount)." EUR <span style=\"color: green;\">(- ".$row->discount." EUR)</span>";
            } else {
                $price = $row->price." EUR";
            }

            $price_all += ($row->price - $row->discount);

            $s_price = number_format($row->price, 2, ",", ".");

            if ($row->type == "cleaning")
            {
                $tblRows .= "<tr>";
                $tblRows .= "<td colspan=\"3\">".$row->name."</td>";
                $tblRows .= "<td align=\"center\">".$s_price." EUR</td>";
                $tblRows .= "</tr>";
            } else
            if ($row->type == "discount")
            {
                $tblRows .= "<tr>";
                $tblRows .= "<td colspan=\"3\">".$row->name."</td>";
                $tblRows .= "<td align=\"center\">-".$s_price." EUR</td>";
                $tblRows .= "</tr>";
            } else
            if ($row->type == "freeform")
            {
                $tblRows .= "<tr>";
                $tblRows .= "<td colspan=\"3\">".$row->name."</td>";
                $tblRows .= "<td align=\"center\">".$s_price." EUR</td>";
                $tblRows .= "</tr>";
            } else
            if ($row->type == "deposit")
            {
                $tblRows .= "<tr>";
                $tblRows .= "<td colspan=\"3\">Kaution (wird nach der Abreise erstattet)</td>";
                $tblRows .= "<td align=\"center\">".$s_price." EUR</td>";
                $tblRows .= "</tr>";
            } else {
                // Nur einmal aktualisieren
                if (empty($dateStart))
                {
                    $dateStart = $row->date_from;
                }

                // Immer aktualisieren
                $dateEnde = $row->date_to;

                $tblRows .= "<tr>";
                $tblRows .= "<td>".(++$n).". ".t("Nacht")."</td>";
                $tblRows .= "<td align=\"center\">".$row->date_from." - ".$row->date_to."</td>";
                $tblRows .= "<td align=\"center\">".addslashes($row->name)."</td>";
                $tblRows .= "<td align=\"center\">".number_format($price, 2, ",", ".")."</td>";
                $tblRows .= "</tr>";
            }
        }

        $plc['lblDateRequest'] = date("d.m.Y, H:i", time());
        $plc['lblDateStart'] = $dateStart;
        $plc['lblDateEnd'] = $dateEnde;
        $plc['lblDuration'] = $n;
        $plc['lblPersons'] = $json->guests." Person".($json->guests > 1 ? "en" : "");
        if ($json->amount < 1000)
            $plc['lblPrice'] = number_format($json->amount, 2, ",", ".")." EUR";
        else
            $plc['lblPrice'] = "<span style=\"font-size: 20px;\">".number_format($json->amount, 2, ",", ".")." EUR</span>";
        $plc['lblCommission'] = $iCommission;
        $plc['lblPricelistName'] = $pricelist['config']['name'];
        $plc['lblPricelistAdress'] = $arSettings['pdf']['address'];
        if (!($fDeposit > 0)) {
            $plc['lblDeposit'] = t("inkl. der Kaution in Höhe von %s EUR", "100,00");
        } else {
            $plc['lblDeposit'] = "";
        }
        $plc['tblRows'] = $tblRows;
        // Jetzt die Labels
        $plc['lblPartial'] = number_format($fPartial, 2, ",", ".")." EUR";
        $plc['lblRest'] = number_format(($json->amount - $fPartial), 2, ",", ".")." EUR";

        if (!empty($pricelist['config']['inclusive'])) {
            $plc['lblInclusive'] = " (".$pricelist['config']['inclusive'].")";
        } else {
            $plc['lblInclusive'] = "";
        }

        if ($bRechnung) {
            $fsContent = "invoice.html";

            $plc['lblInvoiceDate'] = date("d.m.Y", time());
            $sInvoice = utf8_encode($this->get("invoice"));

            if (!empty($sInvoice)) {
                list($plc['lblInvoiceName1'], $plc['lblInvoiceName2'], $plc['lblInvoiceStreet'], $plc['lblInvoiceCity']) = explode("|", $sInvoice);

                if (!isset($plc['lblInvoiceName1']))
                    $plc['lblInvoiceName1'] = "";

                if (!isset($plc['lblInvoiceName2']))
                    $plc['lblInvoiceName2'] = "";

                // Leerzeile vermeiden
                if (!empty($plc['lblInvoiceName1']) && empty($plc['lblInvoiceName2'])) {
                    $plc['lblInvoiceName2'] = $plc['lblInvoiceName1'];
                    $plc['lblInvoiceName1'] = "";
                }

                $plc['lblInvoiceName'] = implode("<br />", array($plc['lblInvoiceName1'], $plc['lblInvoiceName2']));
            } else {
                $plc['lblInvoiceName'] = $plc['lblInvoiceStreet'] = $plc['lblInvoiceCity'] = "";
            }
        } else {
            $fsContent = "content.html";
        }

        $html = @file_get_contents(dirname(__FILE__)."/pdf/html/{$fsContent}");

        foreach ($plc as $key => $value) {
            $html = str_replace("{".$key."}", $value, $html);
        }

        // Print text using writeHTMLCell()
        $pdf->writeHTMLCell(0, 0, 13.8, '', $html, 0, 1, 0, true, '', true);

        // Ausgabe des PDFs!
        $pdf->Output(($bRechnung ? "rechnung-" : "").mb_strtolower($pricelist['config']['name'])."-".date("ymd", time()).".pdf", "D");
    }
}
