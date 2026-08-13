<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class ConfigurationService
{
    public const EXTENSION_KEY = 'alt_text_generator';
    private const TABLE = 'tx_alttextgenerator_configuration';
    private const SINGLETON_UID = 1;

    /** @var array<string, string> */
    private const DEFAULT_CONFIGURATION = [
        'apiKey' => '',
        'enabled' => '1',
        'writingStyle' => '',
        'altTextMinLength' => '100',
        'altTextMaxLength' => '150',
        'altTextPrefix' => '',
        'altTextSuffix' => '',
        'generateTitle' => '1',
        'generateDescription' => '1',
        'seoKeywords' => '',
        'negativeKeywords' => '',
        'customPrompt' => '',
        'autoGenerateOnUpload' => '1',
        'autoRenameOnUpload' => '0',
        'overwriteExistingAltText' => '1',
        'usePublicImageUrls' => '0',
        'allowedImageExtensions' => 'jpg,jpeg,png,webp,gif,avif,svg',
        'requestTimeout' => '30',
        'shortAltTextLength' => '40',
        'logApiErrors' => '1',
        'notifyOnBulkComplete' => '0',
        'notificationEmail' => '',
        'ignoreMissingImages' => '1',
    ];

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        // Optional for compatibility with TYPO3's already-warmed DI container
        // from releases where ConfigurationService only accepted this first
        // argument. Fresh containers autowire it as usual.
        private ?ConnectionPool $connectionPool = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->withFixedDefaults(array_replace(self::DEFAULT_CONFIGURATION, $this->getStored()));
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultConfiguration(): array
    {
        return self::DEFAULT_CONFIGURATION;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStored(): array
    {
        try {
            $storedConfiguration = $this->getDatabaseConfiguration();
            if ($storedConfiguration !== null) {
                return $storedConfiguration;
            }

            // Migrate installations that saved their settings before the
            // dedicated table was introduced. This is deliberately a one-time
            // fallback: TYPO3's extension configuration is deployment/system
            // configuration, not durable user-managed extension data.
            $legacyConfiguration = $this->getLegacyConfiguration();
            $configuration = $legacyConfiguration !== [] ? $legacyConfiguration : self::DEFAULT_CONFIGURATION;
            $this->saveDatabaseConfiguration($configuration);

            return $configuration;
        } catch (\Throwable) {
            // Keep the extension usable until an administrator has applied the
            // database schema update. Existing installations can still read
            // their former ExtensionConfiguration during that short period.
            return $this->getLegacyConfiguration();
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function set(array $configuration): void
    {
        $configuration = $this->removeObsoleteConfiguration($configuration);

        try {
            $this->saveDatabaseConfiguration($configuration);
        } catch (\Throwable) {
            // Do not write settings back into TYPO3's configuration file. A
            // schema update is required for saving; callers should receive the
            // database error instead of silently persisting data in the old,
            // cache-sensitive location.
            throw new \RuntimeException(
                'AutoAlt.ai settings could not be saved. Apply the extension database schema update first.',
                1775926793,
                null,
            );
        }
    }

    public function getApiKey(): string
    {
        return trim((string)($this->get()['apiKey'] ?? ''));
    }

    public function getRequestTimeout(): int
    {
        return 30;
    }

    public function isLoggingEnabled(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function withFixedDefaults(array $configuration): array
    {
        $configuration = $this->removeObsoleteConfiguration($configuration);
        $configuration['requestTimeout'] = '30';
        $configuration['logApiErrors'] = '1';
        $configuration['ignoreMissingImages'] = '1';
        $configuration['usePublicImageUrls'] = '0';
        unset($configuration['batchSize']);
        unset($configuration['defaultLanguage']);

        return $configuration;
    }

    /**
     * @return array<string, mixed>|null Null means no database record exists yet.
     */
    private function getDatabaseConfiguration(): ?array
    {
        $configurationJson = $this->getConnectionPool()
            ->getConnectionForTable(self::TABLE)
            ->createQueryBuilder()
            ->select('configuration')
            ->from(self::TABLE)
            ->where('uid = ' . self::SINGLETON_UID)
            ->executeQuery()
            ->fetchOne();

        if ($configurationJson === false) {
            return null;
        }

        $configuration = json_decode((string)$configurationJson, true, 512, JSON_THROW_ON_ERROR);
        return is_array($configuration) ? $configuration : [];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function saveDatabaseConfiguration(array $configuration): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable(self::TABLE);
        $now = time();
        $values = [
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'tstamp' => $now,
        ];

        $existingRecord = $connection->createQueryBuilder()
            ->select('uid')
            ->from(self::TABLE)
            ->where('uid = ' . self::SINGLETON_UID)
            ->executeQuery()
            ->fetchOne();

        if ($existingRecord !== false) {
            $connection->update(self::TABLE, $values, ['uid' => self::SINGLETON_UID], ['uid' => Connection::PARAM_INT]);
            return;
        }

        $connection->insert(self::TABLE, $values + [
            'uid' => self::SINGLETON_UID,
            'pid' => 0,
            'crdate' => $now,
        ], [
            'uid' => Connection::PARAM_INT,
            'pid' => Connection::PARAM_INT,
            'crdate' => Connection::PARAM_INT,
            'tstamp' => Connection::PARAM_INT,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getLegacyConfiguration(): array
    {
        try {
            $configuration = $this->extensionConfiguration->get(self::EXTENSION_KEY);
        } catch (\Throwable) {
            return [];
        }

        return is_array($configuration) ? $this->removeObsoleteConfiguration($configuration) : [];
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function removeObsoleteConfiguration(array $configuration): array
    {
        // These values are no longer configurable by this extension.
        unset($configuration['apiBaseUrl'], $configuration['batchSize'], $configuration['defaultLanguage']);

        return $configuration;
    }

    private function getConnectionPool(): ConnectionPool
    {
        return $this->connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
    }

}
