<?php
namespace Concrete\Vendors\TecnickCom\TCPDF;

class FEWOPDF extends \TCPDF
{
    private $settings;

    public function __construct()
    {
        
        parent::__construct();
        
        unset($this->settings);
    }

    public function setName($name)
    {
        $this->settings['name'] = $name;
    }

    public function setOwner($owner)
    {
        $this->settings['owner'] = $owner;
    }

    public function setAddress($address)
    {
        $this->settings['address'] = $address;
    }

    public function setPhone($phone)
    {
        $this->settings['phone'] = $phone;
    }

    public function setHomepage($homepage)
    {
        $this->settings['homepage'] = $homepage;
    }

    public function setEmail($email)
    {
        $this->settings['email'] = $email;
    }

    public function setLogo($logo)
    {
        $this->settings['logo'] = $logo;
    }

    //Page header
    public function header()
    {
        
        /**
         * Alternative: Infos aus der Datenbank auslesen...
         * 
         * - Image/Logo
         * - Titel
         * - Inhaber
         * - Strasse, Ort
         * - Telefon
         * - Internet
         * - Email
         */

        // Logo
        if (isset($this->settings['logo'])) {
            $image_file = $this->settings['logo'];
            $this->Image(
                $image_file,
                0,
                10,
                40,
                '',
                '', // Dateityp / Endung, e.g. 'PNG'
                '',
                'T',
                false,
                300,
                'R',
                false,
                false,
                0,
                false,
                false,
                false
            );
        }

        $this->SetX(0);
        $this->SetY(12);
        // Set font
        $this->SetFont('helvetica', 'UB', 17);
        // Title
        if (isset($this->settings['name'])) {
            $this->Cell(0, 14, $this->settings['name'], 0, 2, 'L', 0, '', 0, false, 'M', 'M');
        }
        // Set font
        $this->SetFont('helvetica', 'N', 11);
        // Inhaber
        if (isset($this->settings['owner'])) {
            $this->Cell(0, 11, $this->settings['owner'], 0, 2, 'L', 0, '', 0, false, 'M', 'M');
        }
        // Anschrift
        if (isset($this->settings['address'])) {
            $this->Cell(0, 11, $this->settings['address'], 0, 2, 'L', 0, '', 0, false, 'M', 'M');
        }
        // Telefon
        if (isset($this->settings['phone'])) {
            $this->Cell(0, 13, t("Telefon").": ".$this->settings['phone'], 0, 2, 'L', 0, '', 0, false, 'M', 'M');
        }

        $html = @file_get_contents(dirname(__FILE__)."/html/header.html");

        if (!isset($this->settings['homepage'])) {
            $this->settings['homepage'] = BASE_URL;
        }

        $html = str_replace("{WEBSITE}", str_replace("http://", "", $this->settings['homepage']), $html);

        if (isset($this->settings['email'])) {
            $html = str_replace("{EMAIL}", $this->settings['email'], $html);
        }

        $this->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    }

    // Page footer
    public function footer()
    {
        $time = time();

        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 10);
        // Page number
        $this->Cell(0, 10, t("Seite")." ".$this->getAliasNumPage().'/'.$this->getAliasNbPages()." - ".t("Erstellungszeitpunkt").": ".date("d.m.Y", $time).", um ".date("H:i:s", $time)." ".t("Uhr"), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
