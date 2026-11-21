<?php

namespace Concrete\Package\FewoTk2\Block\FewoAusstattungsliste;

use Concrete\Core\Block\BlockController;
use FewoTk2\Block\AddDefaults;

class Controller extends BlockController
{
    use AddDefaults;

    public $title = '';
    public $items = '[]';
    public $columnCount = 2;
    public $collapsible = false;

    protected $btTable = 'btFewoAusstattungsliste';
    protected $btInterfaceWidth = 640;
    protected $btInterfaceHeight = 560;
    protected $btDefaultSet = 'fewo_tk2';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = true;

    public function getBlockTypeName()
    {
        return t('Amenities List');
    }

    public function getBlockTypeDescription()
    {
        return t('List of amenities with optional notes.');
    }

    public function getSearchableContent()
    {
        return implode(', ', array_map(static fn (array $item) => trim($item['name'] . ' ' . $item['note']), $this->getItems()));
    }

    public function add()
    {
        $this->setAddDefaults();
        $this->set('itemList', []);
    }

    public function edit()
    {
        $this->set('itemList', $this->getItems());
    }

    public function view()
    {
        $this->set('itemList', $this->getItems());
    }

    /**
     * @return array<int, array{name: string, note: string}>
     */
    public function getItems(): array
    {
        $items = json_decode((string) $this->items, true);

        return is_array($items) ? array_values(array_filter($items, static fn ($item) => is_array($item) && ($item['name'] ?? '') !== '')) : [];
    }

    public function save($args)
    {
        $items = [];
        $names = (array) ($args['itemName'] ?? []);
        $notes = (array) ($args['itemNote'] ?? []);
        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $items[] = ['name' => mb_substr($name, 0, 255), 'note' => mb_substr(trim((string) ($notes[$index] ?? '')), 0, 1000)];
            }
        }

        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'columnCount' => max(1, min(4, (int) ($args['columnCount'] ?? 2))),
            'collapsible' => !empty($args['collapsible']) ? 1 : 0,
        ]);
    }
}
