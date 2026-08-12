<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class HistoryListResult
{
    /**
     * @param array<int, HistoryEntry> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $successCount,
        public int $failedCount,
        public int $page,
        public int $totalPages,
    ) {}
}
