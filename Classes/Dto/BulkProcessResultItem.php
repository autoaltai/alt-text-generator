<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

/**
 * A single processed image's outcome within a batch, used to render the
 * live generation log on the Bulk Generate page.
 */
final readonly class BulkProcessResultItem
{
    public function __construct(
        public int $fileUid,
        public string $fileName,
        public ?string $publicUrl,
        public bool $success,
        public string $message,
    ) {}
}
