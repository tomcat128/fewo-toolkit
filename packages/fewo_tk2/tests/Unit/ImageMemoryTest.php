<?php

namespace FewoTk2\Tests\Unit;

use FewoTk2\Support\ImageMemory;
use PHPUnit\Framework\TestCase;

/**
 * Bilder werden nur verkleinert, wenn der Arbeitsspeicher von PHP dafür reicht – sonst fiele die Seite aus.
 */
final class ImageMemoryTest extends TestCase
{
    private const MB = 1024 * 1024;

    public function testMemoryLimitIsParsed(): void
    {
        self::assertSame(128 * self::MB, ImageMemory::parse('128M'));
        self::assertSame(1024 * self::MB, ImageMemory::parse('1G'));
        self::assertSame(512 * 1024, ImageMemory::parse('512k'));
        self::assertSame(134217728, ImageMemory::parse('134217728'));
        self::assertSame(-1, ImageMemory::parse('-1'));
        self::assertSame(-1, ImageMemory::parse(''));
    }

    public function testLargePanoramasAreNotResizedWithTheUsualLimit(): void
    {
        // 4096 × 2048 braucht rund 120 MB: Mit 128 MB und der Grundlast von Concrete (30 MB) reicht es nicht
        self::assertFalse(ImageMemory::canResize(4096, 2048, 5 * self::MB, 128 * self::MB, 30 * self::MB));
        self::assertTrue(ImageMemory::canResize(4096, 2048, 5 * self::MB, 512 * self::MB, 30 * self::MB));
        // gemessen: 5000 × 2500 braucht zusätzlich 150 MB – mit 128 MB nie, mit 256 MB ja
        self::assertFalse(ImageMemory::canResize(5000, 2500, 0, 128 * self::MB, 0));
        self::assertTrue(ImageMemory::canResize(5000, 2500, 8 * self::MB, 256 * self::MB, 30 * self::MB));
        // knapp unter der Grenze von 128 MB: 4000 × 2000 hätte nach der alten Schätzung (12 Byte) noch gepasst
        self::assertFalse(ImageMemory::canResize(4000, 2000, 4 * self::MB, 128 * self::MB, 26 * self::MB));
        // ein übliches Foto fürs Web
        self::assertTrue(ImageMemory::canResize(2000, 1500, 1 * self::MB, 128 * self::MB, 30 * self::MB));
        // Foto einer Kamera mit 24 Megapixeln
        self::assertFalse(ImageMemory::canResize(6000, 4000, 9 * self::MB, 128 * self::MB, 30 * self::MB));
        self::assertTrue(ImageMemory::canResize(6000, 4000, 9 * self::MB, 512 * self::MB, 30 * self::MB));
        // eine große Datei zählt mit: Sie wird zum Entpacken vollständig eingelesen
        self::assertTrue(ImageMemory::canResize(2500, 2000, 1 * self::MB, 128 * self::MB, 30 * self::MB));
        self::assertFalse(ImageMemory::canResize(2500, 2000, 12 * self::MB, 128 * self::MB, 30 * self::MB));
    }

    public function testWithoutLimitOrSizeTheImageIsResized(): void
    {
        self::assertTrue(ImageMemory::canResize(20000, 10000, 0, -1, 40 * self::MB));
        self::assertTrue(ImageMemory::canResize(0, 0, 0, 128 * self::MB, 120 * self::MB));
    }
}
