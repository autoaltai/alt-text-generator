<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\AltTextTranslationService;
use PHPUnit\Framework\TestCase;

final class AltTextTranslationServiceTest extends TestCase
{
    public function testLegacyGeneratedTranslationMarkersAreRecognizedAsRegenerable(): void
    {
        $method = new \ReflectionMethod(AltTextTranslationService::class, 'canOverwriteExistingTranslation');
        $service = (new \ReflectionClass(AltTextTranslationService::class))->newInstanceWithoutConstructor();

        self::assertTrue((bool)$method->invoke($service, 0, 'AutoAlt.ai - Existing generated ALT text'));
        self::assertTrue((bool)$method->invoke($service, 0, '[Translate to German:] AutoAlt.ai - Copied generated ALT text'));
    }
}
