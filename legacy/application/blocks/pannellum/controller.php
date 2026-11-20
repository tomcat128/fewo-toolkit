<?php
namespace Application\Block\Pannellum;

use Loader;
use Page;
use Mobile_Detect;
use \Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    const DB_TABLE_MAIN = "btPannellum";

    protected $btTable = self::DB_TABLE_MAIN; // Die Haupttabelle
    protected $btInterfaceWidth = "640";
    protected $btInterfaceHeight = "640";
    protected $btExportTables = array(self::DB_TABLE_MAIN);

    private static $panorama_count = 1;

    private $isEditMode = false;

    public function __construct($obj = null)
    {
        parent::__construct($obj);

        $c = Page::getCurrentPage();

        if (method_exists($c, "isEditMode"))
        {
            $this->isEditMode = $c->isEditMode();
        }

        $this->set("is_editmode", $this->isEditMode);
    }

    public function getBlockTypeName()
    {
        return t('Pannellum 360° Viewer');
    }

    public function getBlockTypeDescription()
    {
        return t('Pannellum 360° Panorama Viewer');
    }

    public function on_before_render()
    {
        $is_mobile = false;

        $md = new Mobile_Detect();

        if ($md->isMobile())
        {
           $is_mobile = true;
        }

        $this->set("panorama_id", self::$panorama_count);
        $this->set("panorama_count", self::$panorama_count);
        
        self::$panorama_count++;

        $viewer_code_parsed = $this->viewer_code;

        // Strip multiline comments /* ... */
        $viewer_code_parsed = preg_replace('/(\s+)\/\*([^\/]*)\*\/(\s+)/s', "\n", $viewer_code_parsed);
        // Remove Newlines, Returns & Tabs
        $viewer_code_parsed = str_replace(array("\n", "\r", "  "), " ", $viewer_code_parsed);

        $mobile_images = array();

        // Parse mobile images if they exists ...
        if (isset($this->images_mobile) && !empty($this->images_mobile))
        {
            foreach (explode("\n", $this->images_mobile) as $row) {
                list($key, $value) = explode("->", trim($row));
                $mobile_images[$key] = $value;
            }
        }

        // Replace placeholder for images...
        foreach (explode("\n", $this->images) as $row) {
            list($key, $value) = explode("->", trim($row));
            if ($is_mobile && isset($mobile_images[$key]))
            {
                $viewer_code_parsed = str_replace("{".$key."}", $mobile_images[$key], $viewer_code_parsed);
            } else {
                $viewer_code_parsed = str_replace("{".$key."}", $value, $viewer_code_parsed);
            }
        }

        // Now set new value
        $this->set("viewer_code_parsed", $viewer_code_parsed);

        if ($this->aspect_ratio == "Manual")
        {
            $this->set("css_class", $default_css_class);
        } else {
            list($mode, $width, $height) = explode(":", $this->aspect_ratio);

            if ($width < 1)
                $width = 1;

            $this->set("css_class", "panorama_auto");
            $this->set("resize_ratio", ($height / $width));
        }

        $this->set("is_mobile", $is_mobile);
    }
}
