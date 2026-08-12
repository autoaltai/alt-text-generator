<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Dto;

use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextResult;
use PHPUnit\Framework\TestCase;

final class GenerateAltTextResultTest extends TestCase
{
    public function testFromApiResponseMapsPrimaryFields(): void
    {
        $response = [
            'alt_text' => 'A detailed product image',
            'title' => 'Product image',
            'description' => 'A product on a white background',
            'filename' => 'product.jpg',
            'asset_id' => 123,
        ];

        $result = GenerateAltTextResult::fromApiResponse($response);

        self::assertSame('A detailed product image', $result->altText);
        self::assertSame('Product image', $result->title);
        self::assertSame('A product on a white background', $result->description);
        self::assertSame('product.jpg', $result->filename);
        self::assertSame(123, $result->assetId);
        self::assertSame($response, $result->rawResponse);
    }

    public function testFromApiResponseSupportsAlternateApiFieldNames(): void
    {
        self::assertSame(
            'Fallback alt text',
            GenerateAltTextResult::fromApiResponse(['data' => 'Fallback alt text'])->altText
        );
        self::assertSame(
            'legacy-name.jpg',
            GenerateAltTextResult::fromApiResponse(['imagename' => 'legacy-name.jpg'])->filename
        );
        self::assertSame(
            'snake-name.jpg',
            GenerateAltTextResult::fromApiResponse(['image_name' => 'snake-name.jpg'])->filename
        );
    }
}
