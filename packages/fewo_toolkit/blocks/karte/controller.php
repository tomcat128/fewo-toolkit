<?php

namespace Concrete\Package\FewoToolkit\Block\Karte;

use Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    public const DEFAULT_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const DEFAULT_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

    public $markers = '[]';
    public $zoom = 0;
    public $height = 400;
    public $requireConsent = true;
    public $tileUrl = self::DEFAULT_TILE_URL;
    public $attribution = self::DEFAULT_ATTRIBUTION;

    protected $btTable = 'btFewoKarte';
    protected $btInterfaceWidth = 680;
    protected $btInterfaceHeight = 560;
    protected $btDefaultSet = 'fewo_toolkit';
    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = true;

    public function getBlockTypeName()
    {
        return t('Map (OpenStreetMap)');
    }

    public function getBlockTypeDescription()
    {
        return t('Map with markers based on OpenStreetMap. Loads only after the visitor agrees.');
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('fewo_toolkit/leaflet');
    }

    public function add()
    {
        $this->set('markerList', []);
        $this->set('tileUrl', self::DEFAULT_TILE_URL);
        $this->set('attribution', self::DEFAULT_ATTRIBUTION);
    }

    public function edit()
    {
        $this->set('markerList', $this->getMarkers());
    }

    public function view()
    {
        $this->set('markerList', $this->getMarkers());
        $this->set('config', [
            'markers' => $this->getMarkers(),
            'zoom' => (int) $this->zoom,
            'tileUrl' => $this->tileUrl ?: self::DEFAULT_TILE_URL,
            'attribution' => $this->attribution ?: self::DEFAULT_ATTRIBUTION,
            'requireConsent' => (bool) $this->requireConsent,
        ]);
    }

    /**
     * @return array<int, array{lat: float, lng: float, title: string, text: string}>
     */
    public function getMarkers(): array
    {
        $markers = json_decode((string) $this->markers, true);

        return is_array($markers) ? $markers : [];
    }

    public function validate($args)
    {
        $errors = $this->app->make('helper/validation/error');
        if ($this->parseMarkers($args) === []) {
            $errors->add(t('Please add at least one marker with valid coordinates.'));
        }
        $tileUrl = trim((string) ($args['tileUrl'] ?? ''));
        if ($tileUrl !== '' && !preg_match('~^https://[^\s"\'<>]+\{z\}[^\s"\'<>]*$~', $tileUrl)) {
            $errors->add(t('The tile server URL must start with https:// and contain {z}, {x} and {y}.'));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'markers' => json_encode($this->parseMarkers($args), JSON_UNESCAPED_UNICODE),
            'zoom' => max(0, min(19, (int) ($args['zoom'] ?? 0))),
            'height' => max(150, min(1200, (int) ($args['height'] ?? 400))),
            'requireConsent' => !empty($args['requireConsent']) ? 1 : 0,
            'tileUrl' => trim((string) ($args['tileUrl'] ?? '')) ?: self::DEFAULT_TILE_URL,
            'attribution' => trim((string) ($args['attribution'] ?? '')) ?: self::DEFAULT_ATTRIBUTION,
        ]);
    }

    /**
     * @return array<int, array{lat: float, lng: float, title: string, text: string}>
     */
    private function parseMarkers(array $args): array
    {
        $markers = [];
        $lats = (array) ($args['markerLat'] ?? []);
        foreach ($lats as $index => $lat) {
            $lat = str_replace(',', '.', trim((string) $lat));
            $lng = str_replace(',', '.', trim((string) ($args['markerLng'][$index] ?? '')));
            if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                continue;
            }
            $markers[] = [
                'lat' => round((float) $lat, 7),
                'lng' => round((float) $lng, 7),
                'title' => mb_substr(trim((string) ($args['markerTitle'][$index] ?? '')), 0, 255),
                'text' => mb_substr(trim((string) ($args['markerText'][$index] ?? '')), 0, 1000),
            ];
        }

        return $markers;
    }
}
