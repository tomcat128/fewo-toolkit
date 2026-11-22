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
        // 4096 × 2048 braucht rund 100 MB: Mit 128 MB und der Grundlast von Concrete (40 MB) reicht es nicht
        self::assertFalse(ImageMemory::canResize(4096, 2048, 128 * self::MB, 40 * self::MB));
        self::assertTrue(ImageMemory::canResize(4096, 2048, 512 * self::MB, 40 * self::MB));
        // ein übliches Foto fürs Web
        self::assertTrue(ImageMemory::canResize(2000, 1500, 128 * self::MB, 40 * self::MB));
        // Foto einer Kamera mit 24 Megapixeln
        self::assertFalse(ImageMemory::canResize(6000, 4000, 128 * self::MB, 40 * self::MB));
        self::assertTrue(ImageMemory::canResize(6000, 4000, 512 * self::MB, 40 * self::MB));
    }

    public function testWithoutLimitOrSizeTheImageIsResized(): void
    {
        self::assertTrue(ImageMemory::canResize(20000, 10000, -1, 40 * self::MB));
        self::assertTrue(ImageMemory::canResize(0, 0, 128 * self::MB, 120 * self::MB));
    }
}
