<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class GenerateAltTextResult
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public string $altText,
        public string $title = '',
        public string $description = '',
        public string $filename = '',
        public int|string|null $assetId = null,
        public array $rawResponse = [],
    ) {}

    /**
     * @param array<string, mixed> $response
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            altText: (string)($response['alt_text'] ?? $response['data'] ?? ''),
            title: (string)($response['title'] ?? ''),
            description: (string)($response['description'] ?? ''),
            filename: (string)($response['filename'] ?? $response['imagename'] ?? $response['image_name'] ?? ''),
            assetId: $response['asset_id'] ?? null,
            rawResponse: $response,
        );
    }
}
