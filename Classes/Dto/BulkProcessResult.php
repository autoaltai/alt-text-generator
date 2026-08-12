<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class BulkProcessResult
{
    /**
     * @param array<int, BulkProcessResultItem> $items
     */
    public function __construct(
        public int $processed,
        public int $completed,
        public int $failed,
        public int $remaining,
        public int $percentage,
        public string $message = '',
        public array $items = [],
        public bool $creditExhausted = false,
        public string $messageKey = '',
        public array $messageArguments = [],
        public int $nextCursor = 0,
    ) {}
}
