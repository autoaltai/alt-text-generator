<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\File;

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

    public function testUploadRenameOverrideRequestsOnlyAnAiFilenameWhenMetadataGenerationIsDisabled(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('https://example.test/file.jpg');
        $file->method('getName')->willReturn('file.jpg');

        $request = $this->subject->buildFromFile(
            file: $file,
            configuration: ['usePublicImageUrls' => '1'],
            websiteDomain: 'example.test',
            generateTitleOverride: false,
            generateDescriptionOverride: false,
            generateAltTextOverride: false,
            renameFileOverride: true,
        );

        self::assertTrue($request->renameFile);
        self::assertFalse($request->generateAltText);
        self::assertFalse($request->generateTitle);
        self::assertFalse($request->generateDescription);
        self::assertSame('on', $request->toPayload()['autoaltai_rename_file']);
        self::assertSame('off', $request->toPayload()['autoaltai_alt_text']);
    }
}
