<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\KeywordValidationService;
use PHPUnit\Framework\TestCase;

final class KeywordValidationServiceTest extends TestCase
{
    public function testAcceptsSixDistinctKeywordsAndPhrases(): void
    {
        $result = (new KeywordValidationService())->validate(
            'Camino, hiking route, Northern Spain, travel guide, pilgrimage, map',
            'logo, watermark'
        );

        self::assertNull($result);
    }

    public function testRejectsMoreThanSixKeywords(): void
    {
        $result = (new KeywordValidationService())->validate('one, two, three, four, five, six, seven', '');

        self::assertSame('bulk.keywordValidation.seoCount', $result['key']);
    }

    public function testRejectsDuplicateKeywordsIgnoringCase(): void
    {
        $result = (new KeywordValidationService())->validate('Camino, camino', '');

        self::assertSame('bulk.keywordValidation.seoDuplicates', $result['key']);
    }

    public function testRejectsKeywordSharedByBothLists(): void
    {
        $result = (new KeywordValidationService())->validate('Camino, map', 'camino');

        self::assertSame('bulk.keywordValidation.conflict', $result['key']);
    }

    public function testRejectsAnOverlongKeyword(): void
    {
        $result = (new KeywordValidationService())->validate(str_repeat('a', 31), '');

        self::assertSame('bulk.keywordValidation.seoKeywordLength', $result['key']);
    }
}
