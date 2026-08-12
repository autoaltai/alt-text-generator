<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Dto;

use AutoAltAi\AltTextGenerator\Dto\HistoryEntry;
use PHPUnit\Framework\TestCase;

final class HistoryEntryTest extends TestCase
{
    public function testIdentifiesOnlyNonZeroTypedLanguageHistoryAsTranslation(): void
    {
        self::assertFalse($this->entry('0:en-US')->isLocalizedTranslation());
        self::assertTrue($this->entry('5:es-ES')->isLocalizedTranslation());
        self::assertFalse($this->entry('de')->isLocalizedTranslation());
    }

    private function entry(string $language): HistoryEntry
    {
        return new HistoryEntry(1, 1, 1, 'image.webp', '/image.webp', 'bulk', $language, 'success', 'ALT', '', '', '', 0);
    }
}
