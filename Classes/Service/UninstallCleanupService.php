<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Removes data that is exclusively owned by the extension.
 *
 * This service intentionally drops only complete extension tables. It never
 * changes FAL metadata, files, or records owned by TYPO3 or another extension.
 * The extension does not register scheduler tasks or custom cache backends, so
 * there are no scheduler or cache records to remove here.
 */
final readonly class UninstallCleanupService
{
    public const EXTENSION_KEY = 'alt_text_generator';

    /** @var list<string> */
    private const OWNED_TABLES = [
        'tx_alttextgenerator_configuration',
        'tx_alttextgenerator_errorlog',
        'tx_alttextgenerator_history',
        'tx_alttextgenerator_rename_history',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @return list<string> Tables removed by this run
     */
    public function cleanup(): array
    {
        $this->removeExtensionConfiguration();
        $this->removeSystemLogRecords();
        $this->removeRegistryRecords();

        $droppedTables = [];
        foreach (self::OWNED_TABLES as $table) {
            if ($this->dropTableIfExists($table)) {
                $droppedTables[] = $table;
            }
        }

        return $droppedTables;
    }

    /**
     * @return list<string>
     */
    public static function getOwnedTables(): array
    {
        return self::OWNED_TABLES;
    }

    private function removeExtensionConfiguration(): void
    {
        // Settings from early versions were kept in TYPO3's extension
        // configuration. Remove them as well: ConfigurationService otherwise
        // deliberately imports that legacy data on a subsequent installation.
        $this->extensionConfiguration->set(self::EXTENSION_KEY);
    }

    private function removeSystemLogRecords(): void
    {
        $table = 'sys_log';
        if (!$this->tableExists($table)) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->delete($table)
            ->where(
                $queryBuilder->expr()->like(
                    'component',
                    $queryBuilder->createNamedParameter('AutoAltAi.AltTextGenerator%', Connection::PARAM_STR),
                ),
            )
            ->executeStatement();
    }

    private function removeRegistryRecords(): void
    {
        $table = 'sys_registry';
        if (!$this->tableExists($table)) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->delete($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'entry_namespace',
                    $queryBuilder->createNamedParameter(self::EXTENSION_KEY, Connection::PARAM_STR),
                ),
            )
            ->executeStatement();
    }

    private function dropTableIfExists(string $table): bool
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tableExists($table)) {
            return false;
        }

        try {
            $schemaManager->dropTable($table);
        } catch (Exception $exception) {
            // A concurrent cleanup may have removed the table between the
            // existence check and DROP TABLE. It is still a successful cleanup.
            if ($schemaManager->tableExists($table)) {
                throw $exception;
            }
        }

        return true;
    }

    private function tableExists(string $table): bool
    {
        return $this->connectionPool
            ->getConnectionForTable($table)
            ->createSchemaManager()
            ->tableExists($table);
    }
}
