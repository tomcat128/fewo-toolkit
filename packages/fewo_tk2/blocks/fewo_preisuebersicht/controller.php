<?php

namespace Concrete\Package\FewoTk2\Block\FewoPreisuebersicht;

use Concrete\Core\Block\BlockController;
use FewoTk2\Apartment\ApartmentRepository;
use FewoTk2\Block\AddDefaults;
use FewoTk2\Block\ApartmentBlock;

class Controller extends BlockController
{
    use AddDefaults;
    use ApartmentBlock;

    /** Saisons als Kacheln nebeneinander. */
    public const LAYOUT_TILES = 'tiles';

    /** Saisons als Liste untereinander. */
    public const LAYOUT_LIST = 'list';

    public $apartmentID = 0;
    public $title = '';
    public $layout = self::LAYOUT_TILES;
    public $showConditions = true;

    protected $btTable = 'btFewoPreisuebersicht';
    protected $btInterfaceWidth = 560;
    protected $btInterfaceHeight = 440;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    // Kein Output-Cache: Die Preise ändern sich unabhängig vom Block.
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('Price Overview');
    }

    public function getBlockTypeDescription()
    {
        return t('Shows the seasons and prices of a holiday apartment. The price list is loaded from Fewo-Office.');
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->edit();
    }

    public function edit()
    {
        $this->setApartmentOptions(t('** Please select'));
        $this->set('layoutOptions', [
            self::LAYOUT_TILES => t('Tiles side by side'),
            self::LAYOUT_LIST => t('List, one below the other'),
        ]);
    }

    public function view()
    {
        $loaded = $this->loadPricelist();
        $this->set('pricelist', $loaded->pricelist);
        $this->set('feed', $loaded->feed);
        $this->set('error', $loaded->error);
        $this->set('canEdit', $this->canEditPage());
        $this->set('layout', $this->layout === self::LAYOUT_LIST ? self::LAYOUT_LIST : self::LAYOUT_TILES);
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if ($this->app->make(ApartmentRepository::class)->find((int) ($args['apartmentID'] ?? 0)) === null) {
            $errors->add(t('Please select an apartment.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'apartmentID' => (int) ($args['apartmentID'] ?? 0),
            'title' => trim((string) ($args['title'] ?? '')),
            'layout' => ($args['layout'] ?? '') === self::LAYOUT_LIST ? self::LAYOUT_LIST : self::LAYOUT_TILES,
            'showConditions' => !empty($args['showConditions']) ? 1 : 0,
        ]);
    }
}
