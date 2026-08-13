<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;
use AutoAltAi\AltTextGenerator\Service\PluginDataSyncPayloadBuilder;
use PHPUnit\Framework\TestCase;

final class PluginDataSyncPayloadBuilderTest extends TestCase
{
    public function testBuildUsesTheSharedAutoAltPayloadContractAndMapsTypo3Settings(): void
    {
        $payload = (new PluginDataSyncPayloadBuilder())->build(
            configuration: [
                'apiKey' => 'api-key',
                'writingStyle' => 'Professional',
                'generateTitle' => '1',
                'generateDescription' => '0',
                'altTextMinLength' => '100',
                'altTextMaxLength' => '150',
                'altTextPrefix' => 'Photo:',
                'altTextSuffix' => 'for AutoAlt.ai',
                'autoGenerateOnUpload' => '1',
                'autoRenameOnUpload' => '1',
                'allowedImageExtensions' => 'jpg,png,webp',
                'seoKeywords' => 'tiles, kitchen',
                'negativeKeywords' => 'stock',
                'customPrompt' => 'Use concise language.',
                'overwriteExistingAltText' => '1',
                'shortAltTextLength' => '40',
            ],
            imageStatistics: new ImageScanResult(42, 8, 34, 3),
            processedByPlugin: 12,
            siteUrl: 'https://www.example.test/typo3',
            typo3Version: '13.4.34',
            extensionVersion: '1.0.5',
            errorLogs: [['level' => 'warning', 'message' => 'Example error', 'created_at' => 123]],
        );

        self::assertSame(42, $payload['total_images']);
        self::assertSame(8, $payload['missing_alt']);
        self::assertSame('typo3', $payload['framework']);
        self::assertSame('13.4.34', $payload['framework_version']);
        self::assertSame('1.0.5', $payload['plugin_version']);
        self::assertSame(12, $payload['process_by_plugin']);
        self::assertSame('example.test', $payload['site_url']);
        self::assertSame('example.test', $payload['website_domain']);
        self::assertSame([['level' => 'warning', 'message' => 'Example error', 'created_at' => 123]], $payload['error_log']);

        $settings = $payload['plugin_setting_data'];
        self::assertSame('api-key', $settings['api_key']);
        self::assertSame('en', $settings['language']);
        self::assertSame('Professional', $settings['writing_style']);
        self::assertTrue($settings['generate_title']);
        self::assertFalse($settings['generate_description']);
        self::assertTrue($settings['upload_enabled']);
        self::assertTrue($settings['rename_file']);
        self::assertSame('jpg,png,webp', $settings['allowed_imagetype']);
        self::assertSame('stock', $settings['negative_keywords']);
        self::assertSame(1, $settings['typo3']['bulk_generation']['batch_size']);
        self::assertTrue($settings['typo3']['bulk_generation']['overwrite_existing_alt_text']);
    }
}
