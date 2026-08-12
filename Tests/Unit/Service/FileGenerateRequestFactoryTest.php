<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use PHPUnit\Framework\TestCase;

final class FileGenerateRequestFactoryTest extends TestCase
{
    private FileGenerateRequestFactory $subject;

    protected function setUp(): void
    {
        $this->subject = new FileGenerateRequestFactory();
    }

    public function testEmptyAllowedExtensionListAllowsAnyExtension(): void
    {
        self::assertTrue($this->subject->isAllowedExtension('svg', ''));
    }

    public function testAllowedExtensionCheckIsCaseInsensitive(): void
    {
        self::assertTrue($this->subject->isAllowedExtension('JPG', 'jpg,png,webp'));
        self::assertTrue($this->subject->isAllowedExtension('webp', 'JPG, PNG, WEBP'));
    }

    public function testUnknownExtensionIsRejected(): void
    {
        self::assertFalse($this->subject->isAllowedExtension('pdf', 'jpg,png,webp'));
    }
}
