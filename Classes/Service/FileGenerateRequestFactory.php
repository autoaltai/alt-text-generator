<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextRequest;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class FileGenerateRequestFactory
{
    /**
     * Used only when a file cannot be associated with a TYPO3 site. Normal
     * generation always passes the locale of site language ID 0 explicitly.
     */
    public const FALLBACK_LANGUAGE = 'en';

    /**
     * @param array<string, mixed> $configuration
     */
    public function buildFromFile(
        File $file,
        array $configuration,
        ?string $websiteDomain = null,
        ?string $seoKeywordsOverride = null,
        ?string $negativeKeywordsOverride = null,
        ?string $languageOverride = null,
        ?bool $generateTitleOverride = null,
        ?bool $generateDescriptionOverride = null,
        ?bool $generateAltTextOverride = null,
        ?bool $renameFileOverride = null,
    ): GenerateAltTextRequest {
        $usePublicUrl = $this->isEnabled($configuration['usePublicImageUrls'] ?? false);
        $publicUrl = $usePublicUrl ? (string)($file->getPublicUrl() ?? '') : '';

        return new GenerateAltTextRequest(
            imageUrl: $publicUrl !== '' ? $publicUrl : $file->getName(),
            websiteDomain: $websiteDomain ?? $this->resolveWebsiteDomain(),
            language: trim((string)($languageOverride ?? '')) ?: self::FALLBACK_LANGUAGE,
            base64Image: $publicUrl === '' ? $this->buildBase64ImageDataUri($file) : null,
            writingStyle: trim((string)($configuration['writingStyle'] ?? 'default')) ?: 'default',
            seoKeywords: trim((string)($seoKeywordsOverride ?? '')) ?: trim((string)($configuration['seoKeywords'] ?? '')),
            negativeKeywords: trim((string)($negativeKeywordsOverride ?? '')) ?: trim((string)($configuration['negativeKeywords'] ?? '')),
            prefix: trim((string)($configuration['altTextPrefix'] ?? '')),
            suffix: trim((string)($configuration['altTextSuffix'] ?? '')),
            customPrompt: trim((string)($configuration['customPrompt'] ?? '')),
            altTextMinLimit: max(0, (int)($configuration['altTextMinLength'] ?? 0)),
            altTextMaxLimit: max(0, (int)($configuration['altTextMaxLength'] ?? 0)),
            generateTitle: $generateTitleOverride ?? $this->isEnabled($configuration['generateTitle'] ?? true),
            generateDescription: $generateDescriptionOverride ?? $this->isEnabled($configuration['generateDescription'] ?? true),
            renameFile: $renameFileOverride ?? false,
            generateAltText: $generateAltTextOverride ?? true,
        );
    }

    /**
     * File::isImage() is unreliable for this purpose: it's extension-list based
     * (TYPO3_CONF_VARS.GFX.imagefile_ext), and that list includes formats like
     * "pdf" and "ai" by default too - those are just processable by the image
     * engine for thumbnail generation, they aren't actually images. The indexed
     * sys_file.type classification (also what ImageScannerService's bulk-scan
     * SQL filters on) is the reliable source of truth here.
     */
    public function isGenerableImage(File $file): bool
    {
        return $file->getType() === FileType::IMAGE->value;
    }

    public function isAllowedExtension(string $extension, string $allowedImageExtensions): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', strtolower($allowedImageExtensions))));
        if ($allowed === []) {
            return true;
        }

        return in_array(strtolower($extension), $allowed, true);
    }

    public function resolveWebsiteDomain(): string
    {
        $host = GeneralUtility::getIndpEnv('HTTP_HOST');

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    private function buildBase64ImageDataUri(File $file): string
    {
        return 'data:' . $file->getMimeType() . ';base64,' . base64_encode($file->getContents());
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }
}
