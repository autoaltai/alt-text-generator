<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class ImageScanItem
{
    public function __construct(
        public int $fileUid,
        public int $metadataUid,
        public int $storageUid,
        public string $identifier,
        public string $name,
        public string $extension,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
        public string $alternative,
        public ?string $publicUrl,
    ) {}

    public function hasAlternative(): bool
    {
        return trim($this->alternative) !== '';
    }
}
