<?php

namespace Concrete\Package\FewoTk2\Block\FewoAusstattungsliste;

use Concrete\Core\Block\BlockController;
use FewoTk2\Block\AddDefaults;

class Controller extends BlockController
{
    use AddDefaults;

    /** Darstellungen der Liste; die erste ist die Vorgabe (so sahen die Listen bis Version 2.7 aus). */
    public const STYLES = ['plain', 'checks', 'tiles', 'card', 'lines', 'chips'];

    public $title = '';
    public $listStyle = 'plain';
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
        $this->set('styleOptions', self::getStyleOptions());
    }

    public function edit()
    {
        $this->set('itemList', $this->getItems());
        $this->set('styleOptions', self::getStyleOptions());
    }

    public function view()
    {
        $this->set('itemList', $this->getItems());
        $this->set('listStyle', self::sanitizeStyle($this->listStyle));
    }

    /**
     * @return array<string, string>
     */
    public static function getStyleOptions(): array
    {
        return [
            'plain' => t('Plain – bullet points'),
            'checks' => t('Check marks'),
            'tiles' => t('Tiles'),
            'card' => t('Card with header'),
            'lines' => t('List with dividing lines'),
            'chips' => t('Labels side by side'),
        ];
    }

    public static function sanitizeStyle(mixed $style): string
    {
        return in_array($style, self::STYLES, true) ? (string) $style : self::STYLES[0];
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
            'listStyle' => self::sanitizeStyle($args['listStyle'] ?? ''),
        ]);
    }
}
