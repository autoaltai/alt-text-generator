<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;

/**
 * Builds the stable, cross-platform payload accepted by
 * autoalt-get-data-from-plugin. Keep platform-specific setting names in this
 * class so additional TYPO3 settings can be added without changing transport
 * or synchronization code.
 */
final class PluginDataSyncPayloadBuilder
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<int, array{level: string, message: string, created_at: int}> $errorLogs
     * @return array<string, mixed>
     */
    public function build(
        array $configuration,
        ImageScanResult $imageStatistics,
        int $processedByPlugin,
        string $siteUrl,
        string $typo3Version,
        string $extensionVersion,
        array $errorLogs = [],
    ): array {
        $siteUrl = $this->normalizeWebsiteDomain($siteUrl);

        return [
            // These keys are shared by the WordPress, Shopware and Magento
            // integrations. Do not rename them without coordinating the API.
            'plugin_setting_data' => $this->buildSettings($configuration),
            'total_images' => $imageStatistics->totalImages,
            'missing_alt' => $imageStatistics->missingAltText,
            'framework' => 'typo3',
            'framework_version' => $typo3Version,
            'plugin_version' => $extensionVersion,
            'process_by_plugin' => max(0, $processedByPlugin),
            'error_log' => $errorLogs,
            'site_url' => $siteUrl,
            'website_domain' => $siteUrl,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function buildSettings(array $configuration): array
    {
        return [
            // Names shared with the WordPress payload.
            'api_key' => (string)($configuration['apiKey'] ?? ''),
            'language' => FileGenerateRequestFactory::FALLBACK_LANGUAGE,
            'writing_style' => (string)($configuration['writingStyle'] ?? ''),
            'generate_title' => $this->isEnabled($configuration['generateTitle'] ?? false),
            'generate_description' => $this->isEnabled($configuration['generateDescription'] ?? false),
            'alt_text_min' => (int)($configuration['altTextMinLength'] ?? 0),
            'alt_text_max' => (int)($configuration['altTextMaxLength'] ?? 0),
            'alt_prefix' => (string)($configuration['altTextPrefix'] ?? ''),
            'alt_suffix' => (string)($configuration['altTextSuffix'] ?? ''),
            'upload_enabled' => $this->isEnabled($configuration['autoGenerateOnUpload'] ?? false),
            'allowed_imagetype' => (string)($configuration['allowedImageExtensions'] ?? ''),
            'seo_keywords' => (string)($configuration['seoKeywords'] ?? ''),
            'negative_keywords' => (string)($configuration['negativeKeywords'] ?? ''),
            'chatgpt_prompt' => (string)($configuration['customPrompt'] ?? ''),
            'rename_file' => $this->isEnabled($configuration['autoRenameOnUpload'] ?? false),

            // TYPO3-specific settings, intentionally grouped to make the
            // payload extensible without disturbing the common contract.
            'typo3' => [
                'enabled' => $this->isEnabled($configuration['enabled'] ?? false),
                'overwrite_existing_alt_text' => $this->isEnabled($configuration['overwriteExistingAltText'] ?? false),
                'use_public_image_urls' => $this->isEnabled($configuration['usePublicImageUrls'] ?? false),
                'request_timeout' => (int)($configuration['requestTimeout'] ?? 30),
                'short_alt_text_length' => (int)($configuration['shortAltTextLength'] ?? 40),
                'log_api_errors' => $this->isEnabled($configuration['logApiErrors'] ?? false),
                'notify_on_bulk_complete' => $this->isEnabled($configuration['notifyOnBulkComplete'] ?? false),
                'notification_email' => (string)($configuration['notificationEmail'] ?? ''),
                'ignore_missing_images' => $this->isEnabled($configuration['ignoreMissingImages'] ?? false),
                'bulk_generation' => [
                    'batch_size' => BulkGenerationService::BATCH_SIZE,
                    'short_alt_text_length' => (int)($configuration['shortAltTextLength'] ?? 40),
                    'overwrite_existing_alt_text' => $this->isEnabled($configuration['overwriteExistingAltText'] ?? false),
                ],
            ],
        ];
    }

    private function normalizeWebsiteDomain(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') {
            return '';
        }

        $host = parse_url(str_contains($siteUrl, '://') ? $siteUrl : 'https://' . $siteUrl, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : $siteUrl;

        return preg_replace('/^www\./i', '', $host) ?? $host;
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'on', 'yes'], true);
    }
}
