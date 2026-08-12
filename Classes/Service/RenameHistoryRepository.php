<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class RenameHistoryRepository
{
    public const TABLE = 'tx_alttextgenerator_rename_history';

    public function __construct(private ConnectionPool $connectionPool) {}

    /** @param array<string, mixed> $metadata */
    public function add(
        int $fileUid,
        int $storageUid,
        string $oldIdentifier,
        string $newIdentifier,
        string $oldFilename,
        string $newFilename,
        string $method,
        string $status,
        int $backendUserUid,
        string $errorMessage = '',
        string $apiRequestId = '',
        array $metadata = [],
    ): int {
        $now = time();
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'pid' => 0,
            'crdate' => $now,
            'tstamp' => $now,
            'file_uid' => $fileUid,
            'storage_uid' => $storageUid,
            'old_identifier' => $oldIdentifier,
            'new_identifier' => $newIdentifier,
            'old_filename' => $oldFilename,
            'new_filename' => $newFilename,
            'rename_method' => $method,
            'status' => $status,
            'error_message' => mb_substr($errorMessage, 0, 2000),
            'api_request_id' => mb_substr($apiRequestId, 0, 255),
            'backend_user_uid' => $backendUserUid,
            'created_at' => $now,
            'undone_at' => 0,
            'metadata' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
        ]);

        return (int)$connection->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findLatestUndoable(int $fileUid, string $currentFilename): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('file_uid', $queryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('new_filename', $queryBuilder->createNamedParameter($currentFilename)),
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter('success')),
                $queryBuilder->expr()->neq('rename_method', $queryBuilder->createNamedParameter('undo')),
                $queryBuilder->expr()->eq('undone_at', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->orderBy('uid', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    public function markUndone(int $historyUid): void
    {
        $now = time();
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['status' => 'undone', 'undone_at' => $now, 'tstamp' => $now],
            ['uid' => $historyUid],
            ['uid' => ParameterType::INTEGER]
        );
    }

    public function countSuccessfullyRenamedFiles(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        return (int)$queryBuilder
            ->addSelectLiteral('COUNT(DISTINCT file_uid)')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->in('status', $queryBuilder->createNamedParameter(['success', 'undone'], Connection::PARAM_STR_ARRAY)))
            ->executeQuery()
            ->fetchOne();
    }

    public function countEntries(): int
    {
        return (int)$this->connectionPool
            ->getQueryBuilderForTable(self::TABLE)
            ->count('uid')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchOne();
    }
}
