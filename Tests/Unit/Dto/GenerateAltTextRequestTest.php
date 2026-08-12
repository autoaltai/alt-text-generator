<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Dto;

use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextRequest;
use PHPUnit\Framework\TestCase;

final class GenerateAltTextRequestTest extends TestCase
{
    public function testFilenameOnlyRequestUsesExistingApiFlags(): void
    {
        $payload = (new GenerateAltTextRequest(
            imageUrl: 'image.jpg',
            websiteDomain: 'example.test',
            renameFile: true,
            generateAltText: false,
        ))->toPayload();

        self::assertSame('on', $payload['autoaltai_rename_file']);
        self::assertSame('off', $payload['autoaltai_alt_text']);
        self::assertSame('example.test', $payload['website_domain']);
    }
}
