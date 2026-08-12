<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class ImageScanResult
{
    /**
     * @param array<int, ImageScanItem> $items
     */
    public function __construct(
        public int $totalImages,
        public int $missingAltText,
        public int $withAltText,
        public int $shortAltText = 0,
        public array $items = [],
    ) {}

    public function hasMissingAltText(): bool
    {
        return $this->missingAltText > 0;
    }
}
