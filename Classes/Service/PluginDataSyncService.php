<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Performs a best-effort synchronization of installation information. It is
 * deliberately fire-and-forget from the caller's perspective: a remote API or
 * database failure is logged and can never break a TYPO3 request.
 */
final readonly class PluginDataSyncService
{
    private const PACKAGE_NAME = 'autoaltai/alt-text-generator';

    public function __construct(
        private AutoAltApiService $autoAltApiService,
        private ConfigurationService $configurationService,
        private ImageScannerService $imageScannerService,
        private HistoryService $historyService,
        private ErrorLogService $errorLogService,
        private PluginDataSyncPayloadBuilder $payloadBuilder,
        private LoggerInterface $logger,
    ) {}

    public function synchronize(string $websiteDomain): void
    {
        try {
            $configuration = $this->configurationService->get();
            if (trim((string)($configuration['apiKey'] ?? '')) === '') {
                return;
            }

            $imageStatistics = $this->imageScannerService->scan(
                allowedImageExtensions: trim((string)($configuration['allowedImageExtensions'] ?? '')),
                limit: 1,
                shortAltTextLength: max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
            );

            $payload = $this->payloadBuilder->build(
                configuration: $configuration,
                imageStatistics: $imageStatistics,
                processedByPlugin: $this->historyService->countSuccessfullyProcessedFileUids(),
                siteUrl: $websiteDomain,
                typo3Version: (new Typo3Version())->getVersion(),
                extensionVersion: $this->resolveExtensionVersion(),
                errorLogs: $this->buildErrorLogPayload(),
            );

            $this->autoAltApiService->synchronizePluginData($payload);
        } catch (\Throwable $exception) {
            // Never add configuration values or the API key to this context.
            $this->logger->warning('AutoAlt.ai plugin data synchronization failed.', [
                'websiteDomain' => $websiteDomain,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return array<int, array{level: string, message: string, created_at: int}>
     */
    private function buildErrorLogPayload(): array
    {
        $logs = [];
        foreach ($this->errorLogService->getRecent(20) as $entry) {
            $logs[] = [
                'level' => (string)($entry['level'] ?? 'error'),
                'message' => (string)($entry['message'] ?? ''),
                'created_at' => (int)($entry['crdate'] ?? 0),
            ];
        }

        return $logs;
    }

    private function resolveExtensionVersion(): string
    {
        try {
            $extensionKey = ConfigurationService::EXTENSION_KEY;
            $emConfPath = ExtensionManagementUtility::extPath($extensionKey) . 'ext_emconf.php';
            if (is_file($emConfPath)) {
                $EM_CONF = [];
                $_EXTKEY = $extensionKey;
                require $emConfPath;
                $version = trim((string)($EM_CONF[$extensionKey]['version'] ?? ''));
                if ($version !== '') {
                    return $version;
                }
            }
        } catch (\Throwable) {
            // Composer metadata below is a safe fallback.
        }

        try {
            return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
