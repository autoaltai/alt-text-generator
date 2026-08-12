<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class HistoryEntry
{
    public function __construct(
        public int $uid,
        public int $fileUid,
        public int $metadataUid,
        public string $fileName,
        public string $fileIdentifier,
        public string $source,
        public string $language,
        public string $status,
        public string $generatedAltText,
        public string $generatedTitle,
        public string $generatedDescription,
        public string $errorMessage,
        public int $crdate,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isLocalizedTranslation(): bool
    {
        return preg_match('/^[1-9]\d*:.+$/', $this->language) === 1;
    }
}
