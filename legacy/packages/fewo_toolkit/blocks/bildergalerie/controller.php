<?php
/**
 * Fewo-Toolkit by FeWoBacher 2015
 *
 * @author  Tomasz Fehrenbacher <tomasz.fehrenbacher@gmx.de>
 * @version 1.0
 *
 */
namespace Concrete\Package\FewoToolkit\Block\Bildergalerie;

use \Concrete\Core\Block\BlockController;
use Detection\MobileDetect;
use Loader;
use Page;
use BlockType;
use File;
use FileSet;
use FileList;
use Concrete\Core\File\Image\Thumbnail\Type\Type;

define("FWB_TABLE_BILDERGALERIE", "btFwbBildergalerie");

class Controller extends BlockController
{
    protected $btTable = FWB_TABLE_BILDERGALERIE; // Die Haupttabelle
    protected $btInterfaceWidth = "420";
    protected $btInterfaceHeight = "300";
    protected $btDefaultSet = "fewobacher";
    protected $btExportTables = array(FWB_TABLE_BILDERGALERIE);

    private $isEditMode;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $c = Page::getCurrentPage();

        if (is_object($c)) {
            $this->isEditMode = $c->isEditMode();
        } else {
            $this->isEditMode = false;
        }

        // Für den Edit-Block
        $filesetOptions = array("0" => "");

        if ($this->isEditMode)
        {
            foreach (FileSet::getMySets() as $fs) {
                $filesetOptions[$fs->fsID] = $fs->getFileSetName();
            }
        }

        $this->set("filesetOptions", $filesetOptions);

        $picrowOptions = array();

        for ($i = 1; $i <= 5; $i++) {
            $picrowOptions[$i] = $i;
        }

        // $this->set("picrowOptions", $picrowOptions);
        
        $this->set("picrowOptions", array("3" => "3"));
    }

    /* Fuer C5-Backend */
    public function getBlockTypeName()
    {
        return "Bildergalerie (Fancybox)";
    }

    /* Fuer C5-Backend */
    public function getBlockTypeDescription()
    {
        return "Bildergalerie mit Lightbox (Fancybox)";
    }

    public function on_before_render()
    {
        $md = new MobileDetect();

        $this->set("is_mobile", $md->isMobile());
        
        if (!empty($this->dateialbum)) {
            
            $bildergalerie = array();

            $fs = FileSet::getByID($this->dateialbum);

            $fl = new FileList();

            $fl->filterBySet($fs);

            $fl->sortByFileSetDisplayOrder();

            $files = $fl->get();

            $type = \Concrete\Core\File\Image\Thumbnail\Type\Type::getByHandle("fewo_toolkit");

            if ($type == null)
            {
                $type = \Concrete\Core\File\Image\Thumbnail\Type\Type::getByHandle("file_manager_listing");
                // throw new \Exception("There is image handle 'fewo_toolkit' with image size 250x250 (Exact)");
            }

            foreach ($files as $f) {
                $sl = $f->getFileStorageLocationObject();
                
                $thumb = $f->getThumbnailURL($type->getBaseVersion());
                $thumb_2x = $f->getThumbnailURL($type->getDoubledVersion());

                $bildergalerie[] = array(   "id" => $f->getFileID(),
                                            "title" => $f->getTitle(),
                                            "description" => $f->getDescription(),
                                            "thumb" => $thumb,
                                            "thumb_2x" => $thumb_2x,
                                            "downloads" => $f->getTotalDownloads(),
                                            "rel_path" => File::getRelativePathFromID($f->getFileID()) );

            }

            $this->set("bildergalerie", $bildergalerie);
        }
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset("javascript", "jquery");

        // $this->addFooterItem(
        //     "<link rel=\"stylesheet\" href=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css\" />"
        // );
        // $this->addFooterItem(
        //     "<script type=\"text/javascript\" src=\"http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js\"></script>"
        // );
    }

    public function save($args)
    {
        if (!isset($args['top_br'])) {
            $args['top_br'] = "N";
        }
        
        parent::save($args);
    }
}
