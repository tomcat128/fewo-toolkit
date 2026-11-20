<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Karte;

use \Concrete\Core\Block\BlockController;
use \Concrete\Package\FewoToolkit\Controller as FewoToolkit;
use Blocktype;
use Loader;
use Core;
use Page;

class Controller extends BlockController
{
    const DB_TABLE_MAIN = "btFwbKarte";

    protected $btTable = self::DB_TABLE_MAIN; // Die Haupttabelle
    protected $btInterfaceWidth = "420";
    protected $btInterfaceHeight = "350";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(self::DB_TABLE_MAIN);

    private $isEditMode = false;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $c = Page::getCurrentPage();

        if ($c != null)
        {
            $this->isEditMode = $c->isEditMode();
        }

        $this->set("is_editmode", $this->isEditMode);
    }

    /* Fuer C5-Backend */
    public function getBlockTypeName()
    {
        return "Karte/Google Maps";
    }

    /* Fuer C5-Backend */
    public function getBlockTypeDescription()
    {
        return "Karte/Google Maps";
    }

    public function on_before_render()
    {
        $bt = BlockType::getByHandle('karte');
        $bPath = Core::make('helper/concrete/urls')->getBlockTypeAssetsURL($bt);

        $this->set("block_image_dir", $bPath."/img");
    }

    public function registerViewAssets($outputContent = '') {
        $html = Loader::helper('html');

        $this->addHeaderItem(
            "<script type=\"text/javascript\" src=\"http://maps.google.com/maps/api/js?sensor=false\"></script>"
        );

        // Standard CSS/JS
        $this->addHeaderItem($html->javascript("jquery.js"));
    }

    // public function save() {

    // }

}
