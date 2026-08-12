<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\FilenameQualityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FilenameQualityServiceTest extends TestCase
{
    private FilenameQualityService $subject;

    protected function setUp(): void
    {
        $this->subject = new FilenameQualityService();
    }

    #[DataProvider('poorFilenameProvider')]
    public function testDetectsPoorFilenames(string $filename): void
    {
        self::assertTrue($this->subject->isPoor($filename));
    }

    public static function poorFilenameProvider(): iterable
    {
        yield 'camera' => ['IMG_1234.jpg'];
        yield 'numeric' => ['1749985123.png'];
        yield 'generic' => ['image-1.webp'];
        yield 'uuid' => ['8f14e45f-ea7d-4a2b-a331-8d0f335b1234.avif'];
        yield 'marketplace' => ['71JfnePfYkL._AC_SL1500_.jpg'];
    }

    public function testAcceptsMeaningfulUnicodeFilenameRegardlessOfExtension(): void
    {
        self::assertFalse($this->subject->isPoor('grüner-gartenstuhl.jpg'));
        self::assertFalse($this->subject->isPoor('grüner-gartenstuhl.WEBP'));
    }
}
