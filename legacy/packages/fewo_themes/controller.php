<?php
namespace Concrete\Package\FewoThemes;

use Package;
use Theme;
use PageTheme;

/**
 * FeWo Themes
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 */

class Controller extends Package
{
    /* Definiere einige wichtige Klassenkonstanten */
    const PACKAGE_HANDLE = "fewo_themes";

    protected $pkgHandle = self::PACKAGE_HANDLE;
    protected $appVersionRequired = "5.8.0";
    protected $pkgVersion = "1.0-alpha-4";

    /**
     * Gets the package name
     *
     * @return string package name
     */
    public function getPackageName()
    {
        return t("Fewo Theme Fewobacher");
    }

    /**
     * Gets the package description
     *
     * @return string package description
     */
    public function getPackageDescription()
    {
        return t("Themes für Ferienwohnungen/Fewobacher.");
    }

    /**
     * Installs blocks etc
     *
     * @return null
     */
    public function install()
    {
        $pkg = parent::install();
        
        // Installiere 'Blocks'
        $this->installThemes($pkg);

    }

    /**
     * Package upgrade method
     *
     * @return void
     */
    public function update()
    {
        parent::upgrade();

        $pkg = parent::getByHandle($this->pkgHandle);

        $this->installThemes($pkg);
    }

    private function installThemes($pkg)
    {
        PageTheme::add('fewobacher', $pkg);
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
       
    }
}
