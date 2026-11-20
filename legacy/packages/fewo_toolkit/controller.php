<?php
namespace Concrete\Package\FewoToolkit;

error_reporting (E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);

use Package;
use Loader;
use BlockType;
use BlockTypeSet;
use SinglePage;
use Job;

// use Config;
// use Concrete\Flysystem\Config;

/**
 * FeWo Toolkit
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 */

define("FWB_BASE_DIR", dirname(__FILE__));

class Controller extends Package
{
    /* Definiere einige wichtige Klassenkonstanten */
    const SYSTEM_DIRECTORY = "fewobacher";
    const TOOLKIT_DIRECTORY = "fewo_toolkit";
    const BLOCK_TYPE_SET = "fewobacher";
    const PACKAGE_HANDLE = "fewo_toolkit";

    protected $pkgHandle = self::PACKAGE_HANDLE;
    protected $appVersionRequired = "5.7.0";
    protected $pkgVersion = "0.12.0-DEV001";
    protected $blockHandles = array(
        "ausstattungsliste",        // Ausstattungsliste
        "belegungsplan_homeaway",   // Belegungsplan (Homeaway)
        "preisuebersicht",          // Preislisten
        "bildergalerie",            // Bildergalerie (Fancybox)
        "preisrechner",             // Preisrechner
        "kontaktformular",          // Kontaktformuler
        "karte"                     // Karte
    );
    protected $jobHandles = array(
        "belegungsplan_homeaway",
        "pricelist"
    );

    /**
     * Gets the package name
     *
     * @return string package name
     */
    public function getPackageName()
    {
        return t("Fewo Toolkit");
    }

    /**
     * Gets the package description
     *
     * @return string package description
     */
    public function getPackageDescription()
    {
        return t("Nützliche Tool-Sammlung für Besitzer von Ferienwohnungen.");
    }

    /**
     * Installs blocks etc
     *
     * @return null
     */
    public function install()
    {
        $pkg = parent::install();
        
        // Installiere ggf. ein neues BlockTypeSet falls noch nicht vorhanden
        if (!BlockTypeSet::getByHandle(self::BLOCK_TYPE_SET)) {
            BlockTypeSet::add(self::BLOCK_TYPE_SET, t("Fewo Toolkit"), $pkg);
        }
        
        // Installiere 'Blocks'
        $this->installBlocks($pkg);

        // Installiere 'Single Pages'
        $this->installSinglePages($pkg);
        
        // Installiere Cronjobs
        $this->installJobs($pkg);

        // Konfiguration setzen
        $this->configure($pkg);
    }

    /**
     * Package upgrade method
     *
     * @return void
     */
    public function upgrade()
    {
        parent::upgrade();

        $pkg = parent::getByHandle($this->pkgHandle);

        $this->installBlocks($pkg);

        $this->installSinglePages($pkg);
        
        $this->installJobs($pkg);

        $this->configure($pkg);
    }

    private function installBlocks($pkg)
    {
        if (!empty($this->blockHandles)) {
            foreach ($this->blockHandles as $blockHandle) {
                if (!is_object(BlockType::getByHandle($blockHandle))) {
                    $bt = BlockType::installBlockTypeFromPackage($blockHandle, $pkg);
                }
            }
        }
    }

    private function installSinglePages($pkg)
    {
        // Toolkit: Pricelist
        $sp = SinglePage::add("/dashboard/".self::TOOLKIT_DIRECTORY."/pricelist", $pkg);
        
        // Toolkit: Contact
        // $sp = SinglePage::add("/dashboard/".self::TOOLKIT_DIRECTORY."/contact", $pkg);
        // -> Kontaktformular im Backend aufgrund Komplexität erstmal deaktiviert
        
        // Fewobacher: Hauptverzeichnis
        $sp = SinglePage::add("/".self::SYSTEM_DIRECTORY, $pkg);
        $sp->setAttribute("exclude_nav", true);

        // Fewobacher: Export
        $sp = SinglePage::add("/".self::SYSTEM_DIRECTORY."/export", $pkg);
        $sp->setAttribute("exclude_nav", true);

        // Fewobacher: Ajax
        $sp = SinglePage::add("/".self::SYSTEM_DIRECTORY."/ajax", $pkg);
        $sp->setAttribute("exclude_nav", true);

        // Fewobacher: PDF
        $sp = SinglePage::add("/".self::SYSTEM_DIRECTORY."/pdf", $pkg);
        $sp->setAttribute("exclude_nav", true);

        // // Fewobacher: Plugins
        $sp = SinglePage::add("/".self::SYSTEM_DIRECTORY."/plugins", $pkg);
        $sp->setAttribute("exclude_nav", true);
    }

    private function installJobs($pkg)
    {
        if (!empty($this->jobHandles)) {
            foreach ($this->jobHandles as $jobHandle) {
                if (!is_object(Job::getByHandle($jobHandle))) {
                    $jb = Job::installByPackage($jobHandle, $pkg);
                }
            }
        }
    }

    /**
    * Configures the package
    *
    * @param Concrete5_Model_Package $package The package instance
    *
    * @return void
    */
    private function configure($pkg)
    {
        // $config = new Config();
        // $config->setPackageObject($pkg);

        // $config->save("FWB_CONFIG_TEST", "Ferienapartment Sonnenseele");

        // Nutzung der Konfiguration:
        // Config::get("FWB_CONFIG_TEST"));
    }
}
