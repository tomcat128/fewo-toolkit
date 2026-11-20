<?php
namespace Application\Block\NiceHr;

use Loader;
use \Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    protected $btInterfaceWidth = "350";
    protected $btInterfaceHeight = "300";

    public function getBlockTypeName()
    {
        return t('Nice HR');
    }

    public function getBlockTypeDescription()
    {
        return t('A nice HR for FEWO HEGNE');
    }
}
