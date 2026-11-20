<?php
namespace Application\Block\PhpCode;

use Loader;
use \Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    const DB_TABLE_MAIN = "btPHPCode";

    protected $btTable = self::DB_TABLE_MAIN; // Die Haupttabelle
    protected $btInterfaceWidth = "400";
    protected $btInterfaceHeight = "250";
    protected $btExportTables = array(self::DB_TABLE_MAIN);

    public function getBlockTypeName()
    {
        return t('PHP-Code');
    }

    public function getBlockTypeDescription()
    {
        return t('Executes some PHP-Code.');
    }
}
