<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Kontaktformular;

use \Concrete\Core\Block\BlockController;
use Loader;
use Page;
use BlockType;

define("FWB_TABLE_KONTAKTFORMULAR", "btFwbKontaktformular");
define("FWB_TABLE_KONTAKTFORMULAR_KONFIGURATION", "btFwbKontaktformularKonfiguration");

class Controller extends BlockController
{
    const HONEYPOT = "Firma";

    protected $btTable = FWB_TABLE_KONTAKTFORMULAR; // Die Haupttabelle
    protected $btInterfaceWidth = "420";
    protected $btInterfaceHeight = "350";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(FWB_TABLE_KONTAKTFORMULAR, FWB_TABLE_KONTAKTFORMULAR_KONFIGURATION);

    private $isEditMode;
    private $currentPath;
    private $currentUrl;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $c = Page::getCurrentPage();
        
        if (is_object($c)) {
            $this->currentPath = $c->getCollectionPath();
            
            $this->set("current_path", $this->currentPath);

            $this->isEditMode = $c->isEditMode();

            $nh = Loader::helper('navigation');
            
            $this->currentUrl = $nh->getCollectionURL($c);

            $this->set("is_editmode", $this->isEditMode);

            $temp = unserialize($this->smtp_auth);

            $this->set("smtp_host", $temp['smtp_host']);
            $this->set("smtp_security", $temp['smtp_security']);
            $this->set("smtp_username", $temp['smtp_username']);
            $this->set("smtp_password", $temp['smtp_password']);
        }
    }

    /* Fuer C5-Backend */
    public function getBlockTypeName()
    {
        return "Kontaktformular";
    }

    /* Fuer C5-Backend */
    public function getBlockTypeDescription()
    {
        return "Kontaktformular";
    }

    public function action_captcha($image)
    {
        if ($image === "secure.png") {
            $img = new \Securimage();

            //$img->ttf_file        = './Quiff.ttf';
            //$img->captcha_type    = Securimage::SI_CAPTCHA_MATHEMATIC; // show a simple math problem instead of text
            //$img->case_sensitive  = true;                              // true to use case sensitve codes - not recommended
            $img->image_height    = 70;                                // height in pixels of the image
            $img->image_width     = $img->image_height * M_E;          // a good formula for image size based on the height
            $img->perturbation    = 0.5;                               // 1.0 = high distortion, higher numbers = more distortion
            //$img->image_bg_color  = new Securimage_Color("#0099CC");   // image background color
            $img->text_color      = new \Securimage_Color("#333333");   // captcha text color
            $img->num_lines       = 2;                                 // how many lines to draw over the image
            //$img->line_color      = new Securimage_Color("#0000CC");   // color of lines over the image
            //$img->image_type      = SI_IMAGE_JPEG;                     // render as a jpeg image
            //$img->signature_color = new Securimage_Color(rand(0, 64),
            
            header("Content-type: image/png");

            $img->show();
        }

        die();
    }

    public function action_send()
    {
        // Daten testen und ggf. eine Fehlermeldung ausgeben
        $mailDebugDir = $_SERVER['DOCUMENT_ROOT']."/application/files/blocks/contactform";

        if (!is_dir($mailDebugDir))
        {
            mkdir($mailDebugDir, 0777, true);
        }

        // Testing auf Formular-Daten
        $securimage = new \Securimage();

        unset($is_error); 
        // unset($error_list); 
        $error_list = array();

        $checks = array("Anrede", "Name", "Email", "Erwachsene", "Anreise", "Abreise", "Nachricht");

        if ($this->captcha === "Y") {
            $checks[] = "Sicherheitscode";
        }

        // Check mandatory
        foreach ($checks as $key) {
            
            if (!isset($_REQUEST[$key]) || empty($_REQUEST[$key])) {
                $is_error[$key] = true;
                $error_list[$key] = t("Kontaktformular.FehlendesFeld", $key);
            }
        }

        if (!empty($_REQUEST['Anrede']) && !preg_match("/^Herr|Frau|Firma$/", $_REQUEST['Anrede']))
        {
            $is_error['Anrede'] = true;
            $error_list['Anrede'] = t("Die übermittelte Anrede ist inkorrekt.");
        }

        // Check other
/*
        $regex_email = '/^[^0-9]\.[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        # $regex_email =  "/^[\w][\w-.]+@[\w-.]+\.[a-z]{2,4}$/U"

        if (!preg_match($regex_email, $_REQUEST['Email'])) {
            $is_error['Email'] = true;
            $error_list['Email'] = t("Kontaktformular.UngueltigeEmail");
        }
*/
	// Trim Email...
        $_REQUEST['Email'] = trim($_REQUEST['Email']);
        if (!filter_var($_REQUEST['Email'], FILTER_VALIDATE_EMAIL)) {
            // invalid emailaddress
            $is_error['Email'] = true;
	    $error_list['Email'] = t("Kontaktformular.UngueltigeEmail");
        }
        
        if (isset($_REQUEST['Anreise'])) {
            if (strtotime($_REQUEST['Anreise']." 23:59:59") < time()) {
                $is_error['Anreise'] = true;
                $error_list['Anreise'] = t("Kontaktformular.AnreiseVergangenheit");
            }

            // Datum Anreise
            if (!empty($_REQUEST['Anreise']) && !preg_match("/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/", $_REQUEST['Anreise']))
            {
                $is_error['Anreise'] = true;
                $error_list['Anreise'] = t("Das übermittelte Datumsformat (Anreise) stimmt nicht.");
            }
        }

        if (isset($_REQUEST['Anreise']) && isset($_REQUEST['Abreise'])) {
            if (strtotime($_REQUEST['Anreise']) >= strtotime($_REQUEST['Abreise'])) {
                $is_error['Abreise'] = true;
                $error_list['Anreise'] = t("Kontaktformular.AbreiseVorAnreise");
            }

            // Datum Anreise
            if (!empty($_REQUEST['Abreise']) && !preg_match("/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/", $_REQUEST['Abreise']))
            {
                $is_error['Abreise'] = true;
                $error_list['Abreise'] = t("Das übermittelte Datumsformat (Abreise) stimmt nicht.");
            }
        }

        if (!empty($_REQUEST['Hausnummer']) && strlen($_REQUEST['Hausnummer']) > 5)
        {
            $is_error['Hausnummer'] = true;
            $error_list['Hausnummer'] = t("Die übermittelte Hausnummer ist zu lang.");
        }

	if (!empty($_REQUEST['Nachricht']) && (substr_count($_REQUEST['Nachricht'], "http:") || substr_count($_REQUEST['Nachricht'], "https:")))
	{
		$is_error['Nachricht'] = true;
		$error_list['Nachricht'] = "Ihre Nachricht darf keine Links enthalten!";
	}

        // Captcha
        if ($this->captcha === "Y" && isset($_REQUEST['Sicherheitscode']) && $securimage->check($_REQUEST['Sicherheitscode']) !== true) {
            $is_error['Sicherheitscode'] = true;
            $error_list['Sicherheitscode'] = t("Kontaktformular.SicherheitscodeFalsch");
        }

        // Honeypot
        if ($this->honeypot === "Y" && isset($_REQUEST[self::HONEYPOT]) && (empty($_REQUEST[self::HONEYPOT]) != true)) {
            $is_error[self::HONEYPOT] = true;
            $error_list[self::HONEYPOT] = t("Kontaktformular.Honeypot");
        }

        // Preislisten
        if ($this->pricelist === "Y" && isset($_REQUEST['Ferienwohnung']) && empty($_REQUEST['Ferienwohnung'])) {
            $is_error['Ferienwohnung'] = true;
            $error_list['Ferienwohnung'] = t("Kontaktformular.Ferienwohnung");
        }

        $form_data = $_REQUEST;

        foreach ($_REQUEST as $key => $value) {
            $this->set($key, $value);
        }
        
        $this->set("form_data", $_REQUEST);

	$BLOCKED_MAILS = array("testing@example.com");
	
        if (count($error_list) === 0)
        {
       	    // !isset($error_list) && count($error_list) == 0

            // Nachricht vorbereiten
            $Message = array();

            $Email_Mode = "html"; // html / text

            if ($Email_Mode == "html")
                $email_eol = "<br />";
            else
                $email_eol = "\n";

            $Message[] = sprintf("Folgende Email-Anfrage ist soeben (%s, %s Uhr) vom Kontaktformular gesendet worden:", date("d.m.Y", time()), date("H:i:s", time()));
            
            if ($Email_Mode == "html")
                $Message[] = $email_eol.$email_eol;
            else
                $Message[] = "";

            if ($Email_Mode == "html")
                $Message[] = "<table border=\"1\" cellpadding=\"2\">";

            // Wenn Text-Modus, dann vorab noch die längste Zeichenkette finden.
            if ($Email_Mode != "html") {
                $max_length = 0;

                foreach ($_REQUEST as $key => $value) {
                    if (mb_strlen($key) > $max_length)
                    {
                        $max_length = mb_strlen($key);
                    }
                }
            }

            $VarData = array();

            $does_not_start_with = function($input) {
				foreach (array("_pk_", "FEWO_", "CONCRETE5_") as $key) {
					if (strcasecmp($key, substr($input, 0, strlen($key))) == 0)
				 	{
				 		return false;
				 	}
				}
				return true;
			};

            foreach ($_REQUEST as $key => $value) {
                if (!empty($value) && $key != "Sicherheitscode" && $key != "CONCRETE5" && $does_not_start_with($key)) {
                	if ($key == "Email") {
                        $value = "<a href=\"mailto:{$value}\">{$value}</a>";
                    }
                    
                    if ($Email_Mode == "html") {
                        $VarData[] = "<tr><td valign=\"top\">".urldecode($key).":</td><td>".str_replace("\n", $email_eol, $value)."</td>";
                    } else {
                        $r = 0;
                        foreach (explode("\n", $value) as $row) {
                            $VarData[] = str_pad( ($r == 0 ? urldecode($key).":" : ""), $max_length + 1, " ", STR_PAD_RIGHT).strip_tags($row);    
                            $r++;
                        }
                    }
                }
            }

            if ($Email_Mode != "html") {
                $Message = array_merge(
                    array("--- --- --- --- --- --- --- --- --- ---"),
                    $VarData,
                    array("--- --- --- --- --- --- --- --- --- ---")
                );
            }
            
            $Message = array_merge($Message, $VarData);
            
            if ($Email_Mode == "html") {
                $Message[] = "</table>";
                $Message[] = $email_eol;
            } else {
                $Message[] = "";
            }
    
            $Message[] = "Ende der Anfrage.";

            // Versand der Email

            // START -> BLOCK ENTFERNEN!
            // echo "Email wird versendet (@".$this->email_empfaenger.")!";
            // echo "<hr />";
            // if ($Email_Mode == "html")
            //     echo implode("\n", $Message);
            // else {
            //     echo "<pre>";
            //     echo implode("\n", $Message);
            //     echo "</pre>";
            // }
            // echo "<hr />";
            // ENDE -> BLOCK ENTFERNEN!

            $swift_rel = "vendor/swiftmailer/swiftmailer/lib/swift_required.php";
            $swift_lib = FWB_BASE_DIR."/".$swift_rel;

            if (!file_exists($swift_lib)) {
                throw new \Exception("Klasse (Swiftmailer) konnte nicht gefunden werden. Ist die Library installiert und wurde der Befehl \"composer install/update\" im Package-Root ausgeführt?");
            }

            // Einbinden...
            require_once $swift_lib;

            try {
                $smtp_auth = unserialize($this->smtp_auth);
                
                $txt_mail_subject = "Neue Buchungsanfrage";
                $txt_mail_sender  = "Kontaktformular";

                if (isset($this->formular_name) && !empty($this->formular_name)) {
                    $txt_mail_subject .= " (".$this->formular_name.")";
                    $txt_mail_sender  .= " (".$this->formular_name.")";
                }

                // Add Unique ID to subject
                $txt_mail_subject .= " [".rand(1,9).time()."]";

                // Kommt aus dem lokalen Vendor
                $message = \Swift_Message::newInstance()
                    ->setSubject($txt_mail_subject)
                    ->setTo(array($this->email_empfaenger))
                    // ->setFrom(array($_REQUEST['Email']))
                    // ->setFrom(array($_REQUEST['Email'] => (!empty($_REQUEST['Vorname']) ? $_REQUEST['Vorname']." " : "").$_REQUEST['Name']))
                    // ->setFrom(array($smtp_auth['smtp_username'] => "Buchungsformular ".$this->formular_name))
                    ->setFrom(array($smtp_auth['smtp_username'] => "Buchungsformular"))
                    //->setFrom(array($smtp_auth['smtp_username'] => $txt_mail_sender))
                    ->setBody(implode("\n", $Message))
                    ->setContentType("text/html")
                    ->setReplyTo(array($_REQUEST['Email'] => (isset($_REQUEST['Vorname']) ? $_REQUEST['Vorname']." " : "").$_REQUEST['Name'])) // Email-Adresse des Absenders (ohne Namen)
                    ;

                list($smtp_auth['smtp_host'], $smtp_auth['smtp_port']) = explode(":", $smtp_auth['smtp_host']);
                
                $transport = \Swift_SmtpTransport::newInstance(
                    $smtp_auth['smtp_host'],
                    (empty($smtp_auth['smtp_port']) ? 25 : $smtp_auth['smtp_port'])
                )
                ->setUsername($smtp_auth['smtp_username'])
                ->setPassword($smtp_auth['smtp_password']);

                if ($smtp_auth['smtp_security'] == "SSL") {
                    $transport->setEncryption("ssl");
                } elseif ($smtp_auth['smtp_security'] == "STARTTLS") {
                    $transport->setEncryption("tls");
                }

                $mailer = \Swift_Mailer::newInstance($transport);
                
		if (!in_array($_REQUEST['Email'], $BLOCKED_MAILS))
		{
                	$result = $mailer->send($message);
		}
		
                // var_dump($result);

                $this->set("sendmail_success", true);

                $this->set("form_data", null);
            } catch (\Exception $e) {
                $error_list[$key] = "Die Email konnte nicht versendet werden: ".$e->getMessage();
            }
            
        }

        ob_start();
        echo "Contents of '\$_SERVER':\n";
        echo "=======================\n";
        var_dump($_SERVER);
        echo "\n";
        echo "Contents of '\$_REQUEST':\n";
        echo "========================\n";
        var_dump($_REQUEST);
        if (count($error_list) > 0)
        {
	        echo "\n";
	        echo "Errorlist:\n";
	        echo "==========\n";
	        var_dump($error_list);
        }
        $result_txt = ob_get_clean();
        ob_end_clean();
        #
        if (!in_array($_REQUEST['Email'], $BLOCKED_MAILS))
	{
        	file_put_contents(sprintf("%s/mailsend_%s.txt", $mailDebugDir, date("Ymd_His")), $result_txt);
	}

        if (isset($error_list) && count($error_list) > 0) {
            $this->set("is_error", $is_error);
            $this->set("error_list", $error_list);
        }
    }

    public function on_before_render()
    {
        // Inputs vorbereiten
        $inputs = array();

        if ($this->honeypot === "Y") {
            $inputs[] = array("format" => "full", "label" => "Firma", "type" => "text", "name" => "Firma");
            $this->set("honeypot_input", self::HONEYPOT);
        }

        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Anrede"), "type" => "select", "width" => "50", "mandatory" => true, "name" => "Anrede", "options" => array("Herr" => t("Kontaktformular.Herr"), "Frau" => t("Kontaktformular.Frau"), "Firma" => t("Kontaktformular.Firma")));
        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Vorname"), "type" => "text", "name" => "Vorname");
        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Name"), "type" => "text", "name" => "Name", "mandatory" => true);
        $inputs[] = array("format" => "split", "inputs" => array(   array("width" => "80", "label" => t("Kontaktformular.Strasse"), "type" => "text", "name" => "Strasse"),
                                                                    array("width" => "20", "label" => t("Kontaktformular.Nr"), "type" => "text", "name" => "Hausnummer") ));
        $inputs[] = array("format" => "split", "inputs" => array(   array("width" => "20", "label" => t("Kontaktformular.PLZ"), "type" => "text", "name" => "Postleitzahl"),
                                                                    array("width" => "80", "label" => t("Kontaktformular.Stadt"), "type" => "text", "name" => "Stadt") ));
        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Land"), "type" => "text", "name" => "Land");
        $inputs[] = array("type" => "blank");
        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Email"), "type" => "text", "name" => "Email", "mandatory" => true);
        $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Telefon"), "type" => "text", "name" => "Telefon");
        $inputs[] = array("type" => "blank");
        /**
         * TODO: Anzahl Personen aus der DB holen. Kinder = (Max. Erwachsene - 1)
         */
        if ($this->pricelist === "Y") {
            // Da aktuell hier Seeherz+Seeherzchen => Bis zu 6 Personen!
            $inputs[] = array("format" => "split", "inputs" => array(   array("width" => "50", "label" => t("Kontaktformular.Reisende"), "type" => "select", "name" => "Erwachsene", "mandatory" => true, "options" => array("1 Erwachsener" => "1 ".t("Kontaktformular.Erwachsener"), "2 Erwachsene" => "2 ".t("Kontaktformular.Erwachsene"), "3 Erwachsene" => "3 ".t("Kontaktformular.Erwachsene"), "4 Erwachsene" => "4 ".t("Kontaktformular.Erwachsene"), "5 Erwachsene" => "5 ".t("Kontaktformular.Erwachsene"), "6 Erwachsene" => "6 ".t("Kontaktformular.Erwachsene"))),
                                                                        array("width" => "50", "label" => null, "type" => "select", "name" => "Kinder", "options" => array("1 Kind" => "1 ".t("Kontaktformular.Kind"), "2 Kinder" => "2 ".t("Kontaktformular.Kinder"), "3 Kinder" => "3 ".t("Kontaktformular.Kinder"), "4 Kinder" => "4 ".t("Kontaktformular.Kinder"))) ));
        } else {
            // Normaler Standard-Input
            $inputs[] = array("format" => "split", "inputs" => array(   array("width" => "50", "label" => t("Kontaktformular.Reisende"), "type" => "select", "name" => "Erwachsene", "mandatory" => true, "options" => array("1 Erwachsener" => "1 Erwachsener", "2 Erwachsene" => "2 Erwachsene", "3 Erwachsene" => "3 Erwachsene", "4 Erwachsene" => "4 Erwachsene")),
                                                                        array("width" => "50", "label" => null, "type" => "select", "name" => "Kinder", "options" => array("1 Kind" => "1 ".t("Kontaktformular.Kind"), "2 Kinder" => "2 ".t("Kontaktformular.Kinder"), "3 Kinder" => "3 ".t("Kontaktformular.Kinder"))) ));
        }
        $inputs[] = array("type" => "blank");
        $inputs[] = array("format" => "split", "inputs" => array(   array("width" => "50", "label" => t("Kontaktformular.Reisezeitraum"), "type" => "text", "name" => "Anreise", "mandatory" => true, "attributes" => array("autocomplete" => "off")),
                                                                    array("width" => "50", "label" => null, "type" => "text", "name" => "Abreise", "mandatory" => true, "attributes" => array("autocomplete" => "off")) ));
        
        if ($this->pricelist === "Y") {
            $inputs[] = array("type" => "blank");
                $inputs[] = array("format" => "split", "label" => t("Kontaktformular.Apartment"), "type" => "select", "width" => "100", "mandatory" => true, "name" => "Ferienwohnung",
                                "options" =>
                                    // Diese Daten sollten langfristig aus der Datenbank kommen
                                    array(  "Seeherz" => "Seeherz, 42m², max. 4 ".t("Kontaktformular.Personen"),
                                            "Seeherzchen" => "Seeherzchen, 24m², max. 2 ".t("Kontaktformular.Personen"),
                                            "Seeherz+Seeherzchen" => "Seeherz + Seeherzchen, max. 6 ".t("Kontaktformular.Personen")));
        }

        $inputs[] = array("type" => "blank");
        $inputs[] = array("format" => "full", "label" => t("Kontaktformular.Nachricht"), "type" => "textarea", "name" => "Nachricht", "mandatory" => true, "attributes" => array("rows" => "5"));
        $inputs[] = array("type" => "blank");

        $arOptions = array( "Suchmaschine (Google, Bing, etc.)" => t("Kontaktformular.GefundenSuchmaschine"),
                            "Fewo-Direkt.de (HomeAway)" => t("Kontaktformular.GefundenHomeaway"),
                            "Airbnb" => "Airbnb",
                            "Empfehlung durch Freunde oder Bekannte" => t("Kontaktformular.GefundenEmfehlung"),
                            "Tourist-Information, Kurverwaltung" => t("Kontaktformular.GefundenTouristinfo"));

        $inputs[] = array("format" => "full", "label" => t("Kontaktformular.WoGefunden"), "type" => "select", "name" => "Wo gefunden", "options" => $arOptions);

        unset($arOptions);

        if ($this->captcha === "Y") {
            $inputs[] = array("type" => "blank");
            $inputs[] = array("format" => "full", "inputs" => array(   array("format" => "full", "type" => "html", "code" => "<img src=\"".$this->currentUrl."/captcha/secure.png\" class=\"secure\" />"),
                                                                        array("format" => "full", "label" => t("Kontaktformular.Sicherheitscode"), "type" => "text", "clear" => true, "name" => "Sicherheitscode", "mandatory" => true) ));

        }

        $this->set("inputs", $inputs);
    }

    public function registerViewAssets($outputContent = '')
    {
        $html = Loader::helper('html');

        $this->addHeaderItem($html->javascript("jquery.js"));
        $this->addHeaderItem($html->css("jquery-ui.css"));
        // $this->addHeaderItem($html->javascript("i18n/ui.datepicker-de.js"));
        $this->addFooterItem($html->javascript("i18n/jquery-ui/datepicker-de.js"));
    }

    public function save($args)
    {
        $args['smtp_auth'] = serialize(array(   "smtp_host" => $args['smtp_host'],
                                                "smtp_security" => $args['smtp_security'],
                                                "smtp_username" => $args['smtp_username'],
                                                "smtp_password" => $args['smtp_password'] ) );

        unset($args['smtp_host']);
        unset($args['smtp_security']);
        unset($args['smtp_username']);
        unset($args['smtp_password']);

        parent::save($args);
    }
}

