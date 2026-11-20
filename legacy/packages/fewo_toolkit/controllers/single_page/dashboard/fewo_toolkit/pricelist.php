<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard\FewoToolkit;

use \Concrete\Core\Page\Controller\DashboardPageController;
use BlockTypeSet;
use BlockTypeList;
use BlockType;
use Loader;

class Pricelist extends DashboardPageController
{
    protected $error;

    /**
     * c5 method on_start
     * fill the default values
     *
     * @return null
     */
    public function on_start()
    {
        parent::on_start();

        $html = \Core::make('helper/html');
        
        $this->addHeaderItem($html->css(BASE_URL . '/packages/fewo_toolkit/single_pages/dashboard/fewo_toolkit/pricelist/view.css'));
    }

    /**
     * c5 method before render
     * configure the buttons in layout
     *
     * @return null
     */
    public function on_before_render()
    {
        

        parent::on_before_render();

        $task = $this->getTask();
        
        if ($task === "add") {
            $this->set("showAdd", true);
        } elseif ($task === "edit") {
            $this->set('showDeleteButton', true);
            $this->set('showOptionButton', true);
            $this->set("showEdit", true);
        } elseif ($task === "delete") {
            $this->set("showDelete", true);
        } elseif ($task === "settings") {
            $this->set("showSettings", true);
        } else {
            // === "view"
            $this->set('showAddButton', true);
            $this->set("showOverview", true);
        }
        
        // Bereite im folgenden die Select-Felder vor wenn die entsprechende Aktion verlangt wird.
        if (in_array($task, array("add", "edit"))) {
            $pricelist_guests_items = $pricelist_minimum_nights = $pricelist_commission_items = array();

            for ($i = 1; $i <= 10; $i++) {
                $pricelist_minimum_nights[$i] = $i." Tag".($i > 1 ? "e" : "");
            }

            $this->set("pricelist_nights_items", $pricelist_minimum_nights);

            for ($i = 1; $i <= 6; $i++) {
                $pricelist_guests_items[$i] = $i;
            }

            $this->set("pricelist_guests_items", $pricelist_guests_items);

            for ($i = 0; $i <= 50; $i+=5) {
                
                $pricelist_commission_items[$i] = $i.",0 %";
            }

            $this->set("pricelist_commission_items", $pricelist_commission_items);
        }

        $html = Loader::helper('html');

        $this->addHeaderItem($html->javascript('jquery.js'));

        // Für den FileSelector
        $concrete_asset_library = Loader::helper('concrete/asset_library');

        $this->set("concrete_asset_library", $concrete_asset_library);
    }

    public function confirm()
    {
        $this->set("success", t("Löschvorgang war erfolgreich."));
    }

    public function add()
    {
        // Tue hier nichts !
    }

    public function settings($list_id)
    {
        if ($this->isPost()) {
            
            $this->validateFormSettings();

            if (!$this->error->has()) {
                $settings = array();

                $db = Loader::db();
                
                if ($this->post("pricelist_set_export_import") === "export") {
                    $settings['export'] = array("active" => $this->post("pricelist_set_export_active"),
                                                "password" => $this->post("pricelist_set_export_pass"));

                } elseif ($this->post("pricelist_set_export_import") === "import") {
                    $settings['import'] = array("url" => $this->post("pricelist_set_import_url"),
                                                "password" => $this->post("pricelist_set_import_pass"));
                }

                // PDF-Export!
                $settings['pdf'] = array();

                $settings['pdf']['name'] = $_POST['pl_pdf_name'];
                $settings['pdf']['owner'] = $_POST['pl_pdf_owner'];
                $settings['pdf']['address'] = $_POST['pl_pdf_address'];
                $settings['pdf']['phone'] = $_POST['pl_pdf_phone'];
                $settings['pdf']['homepage'] = $_POST['pl_pdf_homepage'];
                $settings['pdf']['email'] = $_POST['pl_pdf_email'];
                $settings['pdf']['logo'] = $_POST['pl_pdf_logo'];

                $srl_settings = serialize($settings);

                if (strlen($srl_settings) > 1024) {
                    throw new \Exception(t("DB-Problem entdeckt: Zeichen fuer Settings übersteigen die erlaubte Größe von 1024 Zeichen... Einstellungen können nicht gespeichert werden!"));
                }

                $q = "UPDATE `btFwbPreislisteConfig` SET `settings` = ?, `inclusive` = ? WHERE `listID` = ?";
                $v = array($srl_settings, $_POST['pl_pdf_inclusive'], $list_id);
                $r = $db->query($q, $v);

                $this->set("success", t("Einstellungen wurden gespeichert."));
            }

            // $this->redirect("/dashboard/fewo_toolkit/pricelist/edit/", $list_id);
        }

        $this->set("list_id", $list_id);

        $pl = $this->getListById($list_id);

        if ($pl) {
            $settings = unserialize($pl['settings']);
            
            $this->set("pricelist_name", $pl['name']);
            $this->set("pl_pdf_inclusive", $pl['inclusive']);

            if (isset($settings['export'])) {
                $this->set("pricelist_set_export_import", "export");
                $this->set("pricelist_set_export_active", $settings['export']['active']);
                $this->set("pricelist_set_export_pass", $settings['export']['password']);
            } elseif (isset($settings['import'])) {
                // Wenn im Import-Modus, versuche hier das Update!

                // Updater laden
                Loader::packageElement("updater", "fewo_toolkit");

                // Update durchführen
                \FewoToolkit\Updater::pricelist(1);

                // Weiter im Text...
                $this->set("pricelist_set_export_import", "import");
                $this->set("pricelist_set_import_url", $settings['import']['url']);
                $this->set("pricelist_set_import_pass", $settings['import']['password']);
            }

            // PDF-Export!
            if (isset($settings['pdf'])) {
                if (isset($settings['pdf']['name'])) {
                    $this->set("pl_pdf_name", $settings['pdf']['name']);
                }
                if (isset($settings['pdf']['owner'])) {
                    $this->set("pl_pdf_owner", $settings['pdf']['owner']);
                }
                if (isset($settings['pdf']['address'])) {
                    $this->set("pl_pdf_address", $settings['pdf']['address']);
                }
                if (isset($settings['pdf']['phone'])) {
                    $this->set("pl_pdf_phone", $settings['pdf']['phone']);
                }
                if (isset($settings['pdf']['homepage'])) {
                    $this->set("pl_pdf_homepage", $settings['pdf']['homepage']);
                }
                if (isset($settings['pdf']['email'])) {
                    $this->set("pl_pdf_email", $settings['pdf']['email']);
                }
                if (isset($settings['pdf']['logo'])) {
                    $this->set("pl_pdf_logo", $settings['pdf']['logo']);
                }
            }
        }
    }

    /**
     * minimum form validation
     *
     * @return null
     */
    private function validateFormSettings()
    {
        $validation = Loader::helper('validation/form');

        $validation->setData($this->post());
        
        /**
         * Teste 2 Dinge:
         * - Wenn Export, ob das Passwort in Ordnung ist
         * - Wenn Import, ob das XML abgerufen werden konnte
         */
        if ($this->post("pricelist_set_export_import") === "export") {

        } elseif ($this->post("pricelist_set_export_import") === "import") {

            $validation->addRequired("pricelist_set_import_url", t("Für den Import-Modus muss unbedingt eine Quelle angegeben werden."));

            $validation->test();

            $this->error = $validation->getError();

            $url = $this->post("pricelist_set_import_url");

            if (!empty($url)) {

                $data = @file_get_contents($this->post("pricelist_set_import_url"));

                if (substr_count(@implode("\n", $http_response_header), "200 OK") === 0) {
                    $this->error->add(t("An der angegebenen URL konnte keine Datei gefunden werden."));
                } else {
                    try {
                        $xml = new \SimpleXMLElement($data);

                        if (trim($xml->status->type) != "OK") {
                            $this->error->add(t("Die Daten der Preisliste konnten nicht abgerufen werden: &quot;%s&quot;", trim($xml->status->message)));
                        }
                    } catch (\Exception $e) {
                        $this->error->add(t("Fehler beim verarbeiten der Preisliste: &quot;%s&quot;", trim($e->getMessage()))." (Keine gültige XML-Datei)");
                    }
                }
            }
        }

        $this->set('errors', $validation->getError());
    }

    public function delete($list_id, $confirmation = false)
    {
        if ($confirmation == "confirm") {
            $db = Loader::db();
            
            // Lösche die Konfiguration
            $q = "DELETE FROM `btFwbPreislisteConfig` WHERE `listID` = ?";
            $v = array($list_id);
            $r = $db->query($q, $v);

            // Lösche ggf. Preismatrix
            $q = "DELETE FROM `btFwbPreislisteMatrix` WHERE `listID` = ?";
            $v = array($list_id);
            $r = $db->query($q, $v);

            $this->redirect('/dashboard/fewo_toolkit/pricelist/confirm');
        } else {
            $pl = $this->getListById($list_id);

            $this->set("list_id", $pl['listID']);
            $this->set("pricelist_name", stripslashes($pl['name']));
        }
    }

    public function edit($list_id = null)
    {
        if ($this->isPost()) {

            $this->validateFormEdit();

            if (!$this->error->has()) {
                // Jetzt den Datensatz in der Datenbank sichern
                $db = Loader::db();

                if ($this->post("import_mode")) {
                    $q = "UPDATE `btFwbPreislisteConfig`".
                             " ".
                             "SET".
                             " ".
                             "`name` = ?".
                             " ".
                             "WHERE `listID` = ?";

                    $v = array( $this->post("pricelist_set_name"), $list_id);
                } else {
                    $v = array( $this->post("pricelist_set_name"),
                                str_replace(",", ".", $this->post("pricelist_set_cleaning")),
                                $this->post("pricelist_set_nights"),
                                $this->post("pricelist_set_guests"),
                                $this->post("pricelist_set_commission"));

                    if ($list_id === null) {
                        // Neuer Datensatz
                        $q = "INSERT INTO `btFwbPreislisteConfig`".
                             " ".
                             "(`name`, `gebuehr_endreinigung`, `mindestaufenthaltsdauer`, `anzahl_gaeste`, `anzahlung`)".
                             " ".
                             "VALUES (?, ?, ?, ?, ?)";
                    } else {
                        $q = "UPDATE `btFwbPreislisteConfig`".
                             " ".
                             "SET".
                             " ".
                             "`name` = ?, `gebuehr_endreinigung` = ?, `mindestaufenthaltsdauer` = ?, `anzahl_gaeste` = ?, `anzahlung` = ?".
                             " ".
                             "WHERE `listID` = ?";
                        
                        $v[] = $list_id;
                    }
                }

                $r = $db->query($q, $v);

                if ($list_id === null) {

                    $list_id = (int)$db->Insert_ID();
                    
                    $this->set("success", t("Eine neue Preislist wurde erstellt."));
                } else {
                    $this->set("success", t("Preisliste wurde aktualisiert."));
                }

                if (!$this->post("import_mode")) {
                    // Lösche zuerst alle Daten aus den Preismatrix für diese Preisliste
                    $q = "DELETE FROM `btFwbPreislisteMatrix` WHERE `listID` = ?";
                    $v = array($list_id);
                    $r = $db->query($q, $v);
                    // Aktualisiere jetzt die Preismatrix, sofern Daten gesendet wurden
                    if (isset($_REQUEST['season'])) {
                        
                        for ($i = 0; $i < count($_REQUEST['season']); $i++) {
                            $_REQUEST['season'][$i] = trim($_REQUEST['season'][$i]);

                            // Nur wenn ein Name definiert wurde... (falls leer, löscht dies ebenfalls diese Zeile)
                            if (!empty( $_REQUEST['season'][$i])) {
                                // Query zusammenstellen
                                
                                // Datum "von" konvertieren
                                if (!empty($_REQUEST['date_from'][$i])) {
                                    $tmp = new \DateTime($_REQUEST['date_from'][$i]);
                                    $date_from = $tmp->format("Y-m-d");
                                } else {
                                    $date_from = "0000-00-00";
                                }
                                
                                // Datum "bis" konvertieren
                                if (!empty($_REQUEST['date_to'][$i])) {
                                    $tmp = new \DateTime($_REQUEST['date_to'][$i]);
                                    $date_to = $tmp->format("Y-m-d");
                                } else {
                                    $date_to = "0000-00-00";
                                }

                                $prices = array();

                                if (isset($_REQUEST['pricelist_set_guests'])) {
                                    for ($p = 1; $p <= (int)$_REQUEST['pricelist_set_guests']; $p++) {
                                        if (isset($_REQUEST['price'][$p][$i])) {
                                            $prices[$p] = sprintf("%.2f", str_replace(",", ".", trim($_REQUEST['price'][$p][$i])));
                                        }
                                    }
                                }

                                $q = "INSERT INTO `btFwbPreislisteMatrix`".
                                     " ".
                                     "(`listID`, `bezeichnung`, `datum_von`, `datum_bis`, `preis_pro_nacht`)".
                                     " ".
                                     "VALUES".
                                     " ".
                                     "(?, ?, ?, ?, ?)";

                                $v = array( $list_id,
                                            $_REQUEST['season'][$i],
                                            $date_from,
                                            $date_to,
                                            serialize($prices)
                                            );

                                // Und an die DB senden
                                $r = $db->query($q, $v);
                            }
                        }
                    }
                } elseif (isset($list_id)) {
                    // Nach dem speichern die Liste hier einmalig importieren
                    echo "<center>";
                    echo \Concrete\Package\FewoToolkit\Block\Preisuebersicht\Controller::importById($list_id);
                    echo "</center>";
                }
            }
        }

        if ($list_id > 0) {
            $pl = $this->getListById($list_id);

            if ($pl) {
                $settings = unserialize($pl['settings']);

                // Einfache Daten
                $this->set("list_id", $pl['listID']);
                $this->set("pricelist_name", stripslashes($pl['name']));
                $this->set("pricelist_cleaning", number_format($pl['gebuehr_endreinigung'], 2, ",", ""));

                // Daten für Select "Nächte"
                $this->set("pricelist_nights", stripslashes($pl['mindestaufenthaltsdauer']));

                // Daten für Select "Gäste"
                $this->set("pricelist_guests", $pl['anzahl_gaeste']);

                // Daten für Select "Gäste"
                $this->set("pricelist_commission", $pl['anzahlung']);

                if (isset($settings['import'])) {
                    $this->set("pricelist_import", true);
                    $this->set("pricelist_import_url", $settings['import']['url']);
                } else {
                    $this->set("pricelist_import", false);
                }
            }
        }
    }

    public function getPricelistById($id)
    {
        $pl_data = array();

        $db = Loader::db();

        // Sammle die Daten ein
        $q = "SELECT *".
             " ".
             "FROM `btFwbPreislisteMatrix`".
             " ".
             "WHERE `listID` = ?".
             " ".
             "ORDER BY `datum_von`".
             " ".
             "ASC";

        $r = $db->query($q, array($id));

        while ($row = $r->fetch()) {
            
            $pl_data[] = $row;
        }

        return $pl_data;
    }

    /**
     * minimum form validation
     *
     * @return null
     */
    private function validateFormEdit()
    {
        // concrete\src\Form\Service\Validation.php
        $validation = Loader::helper('validation/form');

        $validation->setData($this->post());
        
        $validation->addRequired("pricelist_set_name", t('Das Feld "Ferienwohnung" muss befüllt werden'));

        if (!isset($_REQUEST['import_mode'])) {
            $validation->addRequired("pricelist_set_cleaning", t('Das Feld "Gebühr Endreinigung" muss befüllt werden'));
            $validation->addRequired("pricelist_set_nights", t('Das Feld "Mindestaufenthaltsdauer" muss befüllt werden'));
            $validation->addRequired("pricelist_set_guests", t('Das Feld "Anzahl Gäste" muss befüllt werden'));
        }

        $validation->test();

        $this->error = $validation->getError();
        
        // Am Ende noch der Test ob es sich um eine Zahl/Dezimalzahl handelt
        if ($this->post("pricelist_set_cleaning")) {
            $val = str_replace(",", ".", $this->post("pricelist_set_cleaning"));

            if (!preg_match("/^[0-9]{1,}(\.[0-9]{0,})?$/", $val)) {
                $this->error->add(t("Dezimalzahl bitte angeben"));
            }
            
        }

        $this->set('errors', $validation->getError());
    }

    /**
     * Returns a valid list of available prices
     */
    public function getAvailableLists()
    {
        $list = array();

        $db = Loader::db();

        $q = "SELECT *".
             " ".
             "FROM `btFwbPreislisteConfig`".
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

    /**
     * Returns one single Pricelist
     */
    public function getListById($list_id)
    {
        $db = Loader::db();

        $q = "SELECT *".
             " ".
             "FROM `btFwbPreislisteConfig`".
             " ".
             "WHERE `listID` = ".$list_id;

        $r = $db->query($q);

        if ($row = $r->fetch()) {
            
            return $row;
        }
    }
}
