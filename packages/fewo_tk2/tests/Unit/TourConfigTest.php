<?php

namespace FewoTk2\Tests\Unit;

use FewoTk2\Tour\TourConfig;
use PHPUnit\Framework\TestCase;

/**
 * Daten des Szenen-Editors für den 360°-Rundgang. Das Beispiel folgt dem Rundgang der alten Website
 * (Räume, Wegweiser mit Ankunftsrichtung, Hinweise, Winkel teils als 356° statt -4°).
 */
final class TourConfigTest extends TestCase
{
    private function input(): array
    {
        return [
            'first' => 'Eingang',
            'scenes' => [
                [
                    'name' => 'Eingang', 'fID' => '12', 'title' => "  Eingangsbereich \n", 'yaw' => 5, 'hfov' => 400, 'horizonRoll' => '-0.75',
                    'hotSpots' => [
                        ['type' => 'scene', 'pitch' => 356.26, 'yaw' => 87.13, 'text' => 'Badezimmer', 'target' => 'bad', 'targetPitch' => 1.9, 'targetYaw' => -74.23],
                        ['type' => 'scene', 'pitch' => 354, 'yaw' => 0, 'text' => 'Wohnbereich', 'target' => 'Wohn- & Schlafbereich'],
                        ['type' => 'info', 'pitch' => 342.74, 'yaw' => 297.03, 'text' => 'Hochbett. Liegefläche je 2 90x200cm'],
                        ['type' => 'info', 'pitch' => 0, 'yaw' => 0, 'text' => '   '],
                        ['type' => 'scene', 'pitch' => 0, 'yaw' => 10, 'text' => 'Nirgendwohin', 'target' => 'keller'],
                        ['type' => 'scene', 'pitch' => 0, 'yaw' => 20, 'text' => 'Im Kreis', 'target' => 'Eingang'],
                        'unsinn',
                    ],
                ],
                ['name' => 'bad', 'fID' => 13, 'title' => 'Badezimmer', 'hotSpots' => [['type' => 'scene', 'pitch' => -2.16, 'yaw' => 106.19, 'target' => 'Eingang']]],
                ['name' => 'Wohn- & Schlafbereich', 'fID' => 0, 'title' => 'Wohn- & Schlafbereich'],
                ['name' => 'bad', 'fID' => 15, 'title' => 'Zweites Bad'],
                ['name' => '360°', 'fID' => 16],
                'unsinn',
            ],
        ];
    }

    public function testSanitizeNormalizesNamesAnglesAndHotspots(): void
    {
        $tour = TourConfig::sanitize($this->input());

        self::assertSame('eingang', $tour['first']);
        self::assertSame(['eingang', 'bad', 'wohn-schlafbereich', 'bad-2', 'raum-360'], array_column($tour['scenes'], 'name'));

        $entrance = $tour['scenes'][0];
        self::assertSame(12, $entrance['fID']);
        self::assertSame('Eingangsbereich', $entrance['title']);
        self::assertSame(5.0, $entrance['yaw']);
        self::assertSame(120.0, $entrance['hfov']);
        self::assertSame(-0.75, $entrance['horizonRoll']);
        // 356,26° wird zu -3,74°, 297,03° zu -62,97°; leere Hinweise, unbekannte Ziele und Wegweiser auf sich selbst entfallen
        self::assertSame([
            ['type' => 'scene', 'pitch' => -3.74, 'yaw' => 87.13, 'text' => 'Badezimmer', 'target' => 'bad', 'targetYaw' => -74.23, 'targetPitch' => 1.9],
            ['type' => 'scene', 'pitch' => -6.0, 'yaw' => 0.0, 'text' => 'Wohnbereich', 'target' => 'wohn-schlafbereich'],
            ['type' => 'info', 'pitch' => -17.26, 'yaw' => -62.97, 'text' => 'Hochbett. Liegefläche je 2 90x200cm'],
        ], $entrance['hotSpots']);
        // Ziel unter dem bereinigten Namen
        self::assertSame('eingang', $tour['scenes'][1]['hotSpots'][0]['target']);
        self::assertSame(100.0, $tour['scenes'][1]['hfov']);
    }

    public function testSanitizeHandlesGarbage(): void
    {
        self::assertSame(['first' => '', 'scenes' => []], TourConfig::sanitize(null));
        self::assertSame(['first' => '', 'scenes' => []], TourConfig::sanitize('text'));
        self::assertSame(['first' => '', 'scenes' => []], TourConfig::sanitize(['scenes' => 'x']));
        // unbekannter Startraum: der erste Raum
        self::assertSame('a', TourConfig::sanitize(['first' => 'z', 'scenes' => [['name' => 'a'], ['name' => 'b']]])['first']);
    }

    public function testPannellumConfig(): void
    {
        $tour = TourConfig::sanitize($this->input());
        $config = TourConfig::toPannellum($tour, ['eingang', 'bad'], ['compass' => true, 'author' => ' Ferienapartment Sonnenseele ', 'preview' => true]);

        self::assertSame([
            'firstScene' => 'eingang',
            'sceneFadeDuration' => 500,
            'autoLoad' => true,
            'compass' => true,
            'author' => 'Ferienapartment Sonnenseele',
            'preview' => '{_preview}',
        ], $config['default']);
        // Räume ohne Bild fehlen, ebenso die Wegweiser dorthin
        self::assertSame(['eingang', 'bad'], array_keys($config['scenes']));
        self::assertSame([
            'title' => 'Eingangsbereich',
            'type' => 'equirectangular',
            'panorama' => '{eingang}',
            'yaw' => 5.0,
            'pitch' => 0.0,
            'hfov' => 120.0,
            'horizonRoll' => -0.75,
            'hotSpots' => [
                ['type' => 'scene', 'pitch' => -3.74, 'yaw' => 87.13, 'text' => 'Badezimmer', 'sceneId' => 'bad', 'targetYaw' => -74.23, 'targetPitch' => 1.9],
                ['type' => 'info', 'pitch' => -17.26, 'yaw' => -62.97, 'text' => 'Hochbett. Liegefläche je 2 90x200cm'],
            ],
        ], $config['scenes']['eingang']);
        // Wegweiser ohne Text und ohne eigene Ankunftsrichtung
        self::assertSame([['type' => 'scene', 'pitch' => -2.16, 'yaw' => 106.19, 'sceneId' => 'eingang']], $config['scenes']['bad']['hotSpots']);
    }

    public function testPannellumConfigWithoutImages(): void
    {
        $tour = TourConfig::sanitize($this->input());

        self::assertNull(TourConfig::toPannellum($tour, []));
        // Fehlt das Bild des Startraums, beginnt der Rundgang im ersten vorhandenen Raum
        $config = TourConfig::toPannellum($tour, ['bad'], ['autoLoad' => false]);
        self::assertSame(['firstScene' => 'bad', 'sceneFadeDuration' => 500, 'autoLoad' => false], $config['default']);
        self::assertSame([], $config['scenes']['bad']['hotSpots']);
    }

    public function testSlug(): void
    {
        self::assertSame('kueche', TourConfig::slug(' Küche '));
        self::assertSame('wohn-schlafbereich', TourConfig::slug('Wohn- & Schlafbereich'));
        self::assertSame('raum-2-og', TourConfig::slug('2. OG'));
        self::assertSame('raum', TourConfig::slug('***'));
        self::assertSame('_preview', TourConfig::PREVIEW_PLACEHOLDER);
        self::assertNotSame('_preview', TourConfig::slug('_preview'));
    }
}
