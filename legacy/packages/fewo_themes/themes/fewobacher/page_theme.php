<?php
namespace Concrete\Package\FewoThemes\Theme\Fewobacher;

class PageTheme extends \Concrete\Core\Page\Theme\Theme
{

    public function registerAssets()
    {
        $this->requireAsset('javascript', 'jquery');
    }
}
