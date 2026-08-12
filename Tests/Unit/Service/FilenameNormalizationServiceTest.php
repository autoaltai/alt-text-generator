<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Service\FilenameNormalizationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final class FilenameNormalizationServiceTest extends TestCase
{
    private FilenameNormalizationService $subject;

    protected function setUp(): void
    {
        $this->subject = new FilenameNormalizationService();
    }

    #[DataProvider('normalizationProvider')]
    public function testNormalizesAndPreservesTheSuppliedExtension(string $input, string $extension, string $expected): void
    {
        self::assertSame($expected, $this->subject->normalize($input, $extension));
    }

    public static function normalizationProvider(): iterable
    {
        yield 'spaces' => ['Blue Product Photo', 'JPG', 'blue-product-photo.jpg'];
        yield 'unsafe punctuation' => ['Summer!!! (Final)', 'png', 'summer-final.png'];
        yield 'repeated separators' => ['blue___--- shirt', 'webp', 'blue-shirt.webp'];
        yield 'extension is replaced' => ['Product Image.jpeg', 'avif', 'product-image.avif'];
        yield 'unicode' => ['Grüner Gartenstuhl', 'jpg', 'grüner-gartenstuhl.jpg'];
    }

    #[DataProvider('invalidFilenameProvider')]
    public function testRejectsEmptyReservedAndTraversalNames(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subject->normalize($input, 'jpg');
    }

    public static function invalidFilenameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'path traversal' => ['../secret'];
        yield 'directory separator' => ['folder/name'];
        yield 'reserved' => ['CON'];
        yield 'unsafe only' => ['!!!'];
    }

    public function testEnforcesSafeMaximumLength(): void
    {
        $result = $this->subject->normalize(str_repeat('a', 400), 'jpeg');
        self::assertLessThanOrEqual(240, strlen($result));
        self::assertStringEndsWith('.jpeg', $result);
    }

    public function testDuplicateNameReceivesDeterministicSuffix(): void
    {
        $folder = $this->createMock(Folder::class);
        $storage = $this->createMock(ResourceStorage::class);
        $storage->expects(self::exactly(2))
            ->method('hasFileInFolder')
            ->willReturnCallback(static fn(string $name): bool => $name === 'product-image.jpg');

        self::assertSame('product-image-2.jpg', $this->subject->resolveAvailableName($storage, $folder, 'product-image.jpg'));
    }
}
