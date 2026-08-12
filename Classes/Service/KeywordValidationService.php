<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

/**
 * Validates the optional, comma-separated keyword lists accepted by the bulk
 * generation endpoint. Keeping this validation on the server ensures direct
 * AJAX requests cannot bypass the limits shown in the backend form.
 */
final readonly class KeywordValidationService
{
    public const MAX_KEYWORDS = 6;
    public const MAX_KEYWORD_LENGTH = 30;
    public const MAX_TOTAL_LENGTH = 180;

    /**
     * @return array{key: string, arguments: array<int, int|string>}|null
     */
    public function validate(string $seoKeywordsInput, string $negativeKeywordsInput): ?array
    {
        $seoKeywordsInput = trim($seoKeywordsInput);
        $negativeKeywordsInput = trim($negativeKeywordsInput);
        if (mb_strlen($seoKeywordsInput) > self::MAX_TOTAL_LENGTH) {
            return $this->error('bulk.keywordValidation.seoTotal', [self::MAX_TOTAL_LENGTH]);
        }
        if (mb_strlen($negativeKeywordsInput) > self::MAX_TOTAL_LENGTH) {
            return $this->error('bulk.keywordValidation.negativeTotal', [self::MAX_TOTAL_LENGTH]);
        }

        $seoKeywords = $this->parseKeywords($seoKeywordsInput);
        $negativeKeywords = $this->parseKeywords($negativeKeywordsInput);
        if (count($seoKeywords) > self::MAX_KEYWORDS) {
            return $this->error('bulk.keywordValidation.seoCount', [self::MAX_KEYWORDS]);
        }
        if (count($negativeKeywords) > self::MAX_KEYWORDS) {
            return $this->error('bulk.keywordValidation.negativeCount', [self::MAX_KEYWORDS]);
        }
        if ($this->containsTooLongKeyword($seoKeywords)) {
            return $this->error('bulk.keywordValidation.seoKeywordLength', [self::MAX_KEYWORD_LENGTH]);
        }
        if ($this->containsTooLongKeyword($negativeKeywords)) {
            return $this->error('bulk.keywordValidation.negativeKeywordLength', [self::MAX_KEYWORD_LENGTH]);
        }

        $normalizedSeoKeywords = $this->normalizeKeywords($seoKeywords);
        $normalizedNegativeKeywords = $this->normalizeKeywords($negativeKeywords);
        if (count($normalizedSeoKeywords) !== count(array_unique($normalizedSeoKeywords))) {
            return $this->error('bulk.keywordValidation.seoDuplicates');
        }
        if (count($normalizedNegativeKeywords) !== count(array_unique($normalizedNegativeKeywords))) {
            return $this->error('bulk.keywordValidation.negativeDuplicates');
        }

        $conflicts = array_values(array_intersect($normalizedSeoKeywords, $normalizedNegativeKeywords));
        if ($conflicts !== []) {
            return $this->error('bulk.keywordValidation.conflict', [implode(', ', $conflicts)]);
        }

        return null;
    }

    /** @return array<int, string> */
    private function parseKeywords(string $input): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $input)), static fn(string $keyword): bool => $keyword !== ''));
    }

    /** @param array<int, string> $keywords */
    private function containsTooLongKeyword(array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) > self::MAX_KEYWORD_LENGTH) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(array $keywords): array
    {
        return array_map(static fn(string $keyword): string => mb_strtolower($keyword), $keywords);
    }

    /**
     * @param array<int, int|string> $arguments
     * @return array{key: string, arguments: array<int, int|string>}
     */
    private function error(string $key, array $arguments = []): array
    {
        return ['key' => $key, 'arguments' => $arguments];
    }
}
