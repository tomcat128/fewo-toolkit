<?php

namespace Concrete\Package\FewoToolkit\Block\Preisuebersicht;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Support\Facade\Url;
use FewoToolkit\Pricing\PricelistRepository;

class Controller extends BlockController
{
    public $pricelistID = 0;
    public $title = '';
    public $showConditions = true;

    protected $btTable = 'btFewoPreisuebersicht';
    protected $btInterfaceWidth = 500;
    protected $btInterfaceHeight = 380;
    protected $btDefaultSet = 'fewo_toolkit';
    protected $btCacheBlockRecord = true;
    // Kein Output-Cache: Preise werden im Dashboard gepflegt.
    protected $btCacheBlockOutput = false;

    public function getBlockTypeName()
    {
        return t('Price Overview');
    }

    public function getBlockTypeDescription()
    {
        return t('Shows the seasons and prices of a price list.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('pricelistOptions', $this->getRepository()->getSelectOptions(t('** Please select')));
        $this->set('dashboardUrl', (string) Url::to('/dashboard/fewo_toolkit/pricelists'));
    }

    public function view()
    {
        $this->set('pricelist', $this->getRepository()->find((int) $this->pricelistID));
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if ($this->getRepository()->find((int) ($args['pricelistID'] ?? 0)) === null) {
            $errors->add(t('Please select a price list.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'pricelistID' => (int) ($args['pricelistID'] ?? 0),
            'title' => trim((string) ($args['title'] ?? '')),
            'showConditions' => !empty($args['showConditions']) ? 1 : 0,
        ]);
    }

    private function getRepository(): PricelistRepository
    {
        return $this->app->make(PricelistRepository::class);
    }
}
