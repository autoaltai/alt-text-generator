<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class FileRenameResult
{
    public function __construct(
        public bool $success,
        public int $fileUid,
        public string $oldFilename = '',
        public string $newFilename = '',
        public string $message = '',
        public ?int $historyUid = null,
        public bool $skipped = false,
    ) {}
}
