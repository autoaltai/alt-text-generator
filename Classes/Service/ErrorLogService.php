<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Backs the Settings page "Error Logs" panel, mirroring the AutoAlt.ai WordPress
 * plugin's in-admin log viewer. Keeps only the most recent entries so the table
 * never grows unbounded.
 */
final readonly class ErrorLogService
{
    private const TABLE = 'tx_alttextgenerator_errorlog';
    private const MAX_ENTRIES = 200;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $level, string $message, array $context = []): void
    {
        $now = time();
        $connection = $this->getConnection();
        $connection->insert(self::TABLE, [
            'pid' => 0,
            'crdate' => $now,
            'tstamp' => $now,
            'level' => $level,
            'message' => mb_substr($message, 0, 2000),
            'context' => $this->encodeContext($context),
        ]);

        $this->prune((int)$connection->lastInsertId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 20): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(max(1, min(self::MAX_ENTRIES, $limit)))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function clear(): void
    {
        $this->getConnection()->truncate(self::TABLE);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): string
    {
        $safeContext = [];
        foreach ($context as $key => $value) {
            $safeContext[$key] = $value instanceof \Throwable ? $value->getMessage() : $value;
        }

        try {
            return json_encode($safeContext, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }
    }

    private function prune(int $insertedUid): void
    {
        // Retention is deliberately periodic: error bursts must not run a
        // table count and delete query for every failed API request. The table
        // can exceed its limit by at most 99 records between cleanups.
        if ($insertedUid <= self::MAX_ENTRIES || $insertedUid % 100 !== 0) {
            return;
        }

        $connection = $this->getConnection();
        $total = (int)$connection->createQueryBuilder()->count('uid')->from(self::TABLE)->executeQuery()->fetchOne();
        if ($total <= self::MAX_ENTRIES) {
            return;
        }

        $cutoffUid = $connection->createQueryBuilder()
            ->select('uid')
            ->from(self::TABLE)
            ->orderBy('uid', 'DESC')
            ->setFirstResult(self::MAX_ENTRIES - 1)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($cutoffUid === false) {
            return;
        }

        $deleteQueryBuilder = $connection->createQueryBuilder();
        $deleteQueryBuilder
            ->delete(self::TABLE)
            ->where(
                $deleteQueryBuilder->expr()->lt(
                    'uid',
                    $deleteQueryBuilder->createNamedParameter((int)$cutoffUid, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}
