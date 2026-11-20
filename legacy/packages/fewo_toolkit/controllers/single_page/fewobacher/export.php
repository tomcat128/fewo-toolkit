<?php
namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Fewobacher;

use \Concrete\Core\Page\Controller\PageController;
use Loader;

class Export extends PageController
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

    }

    public function on_before_render()
    {
        parent::on_before_render();

        // Ohne vernünftigen Befehl geht's zurück zur Startseite
        if ($this->getTask() === "view") {
            $this->redirect("/");
            exit;
        }

        // Sag dem Browser, hier kommt eine XML-Datei!
        header("Content-type: text/xml");

    }

    public function pricelist($id = 0, $password = "")
    {
        try {
            if ($id > 0) {
                $db = Loader::db();

                $q = "SELECT * FROM `btFwbPreislisteConfig` WHERE `listID` = ?";
                $v = array($id);
                $r = $db->query($q, $v);

                if ($row = $r->fetch()) {
                    $settings = unserialize($row['settings']);

                    if (isset($settings['export']) && $settings['export']['active'] === "yes") {

                        if (empty($settings['export']['password']) || (!empty($settings['export']['password']) && $settings['export']['password'] === $password)) {
                            
                            $data['pricelist'] = array( "name" => $row['name'],
                                                        "cleaning" => $row['gebuehr_endreinigung'],
                                                        "min_stay" => $row['mindestaufenthaltsdauer'],
                                                        "max_guests" => $row['anzahl_gaeste'],
                                                        "commission" => $row['anzahlung'] );

                            $q = "SELECT * FROM `btFwbPreislisteMatrix` WHERE `listID` = ? ORDER BY `datum_von`";
                            $v = array($id);
                            $r = $db->query($q, $v);

                            while ($row = $r->fetch()) {
                                $prices = array();

                                foreach (unserialize($row['preis_pro_nacht']) as $persons => $value) {
                                    $prices[] = array("persons" => $persons, "value" => $value);
                                }

                                $data['seasons']['season'][] = array(  "name" => $row['bezeichnung'],
                                                                       "date_start" => $row['datum_von'],
                                                                       "date_end" => $row['datum_bis'],
                                                                       "prices" => $prices );
                            }

                            $this->set("data", $data);
                        } else {
                            // Falsches Passwort
                            throw new \Exception(t("Wrong Password"));
                        }
                    } else {
                        // Export ist nicht aktiv
                        // throw new \Exception(t("Export is not active"));
                        $this->redirect("/");
                        exit;
                    }
                } else {
                    // Export ist nicht aktiv
                    // throw new \Exception(t("Export is not active"));
                    $this->redirect("/");
                    exit;

                }
            } else {
                throw new \Exception(t("No list specified / Invalid id"));
            }
            
            $this->set("status", array("type" => "OK", "message" => t("Okay!")));
        } catch (\Exception $e) {
            $this->set("status", array("type" => "ERROR", "message" => $e->getMessage()));
        }
    }

    public function reservations($id = 0)
    {
        // Export reservations
        echo "rand(0, 1000000) = ".rand(0, 1000000);
        echo "<br /><br />";
    }
}
