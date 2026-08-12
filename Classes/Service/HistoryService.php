<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\HistoryEntry;
use AutoAltAi\AltTextGenerator\Dto\HistoryListResult;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final readonly class HistoryService
{
    private const TABLE = 'tx_alttextgenerator_history';
    private const VALID_SOURCES = ['bulk', 'upload', 'manual', 'selection'];
    private const MAX_ENTRIES = 10000;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function recordSuccess(
        int $fileUid,
        int $metadataUid,
        string $fileIdentifier,
        string $fileName,
        string $source,
        string $language,
        string $generatedAltText,
        string $websiteDomain = '',
        string $generatedTitle = '',
        string $generatedDescription = '',
    ): void {
        $this->insert(
            fileUid: $fileUid,
            metadataUid: $metadataUid,
            fileIdentifier: $fileIdentifier,
            fileName: $fileName,
            source: $source,
            language: $language,
            status: 'success',
            generatedAltText: $generatedAltText,
            errorMessage: '',
            websiteDomain: $websiteDomain,
            generatedTitle: $generatedTitle,
            generatedDescription: $generatedDescription,
        );
    }

    public function recordFailure(
        int $fileUid,
        int $metadataUid,
        string $fileIdentifier,
        string $fileName,
        string $source,
        string $language,
        string $errorMessage,
        string $websiteDomain = '',
    ): void {
        $this->insert(
            fileUid: $fileUid,
            metadataUid: $metadataUid,
            fileIdentifier: $fileIdentifier,
            fileName: $fileName,
            source: $source,
            language: $language,
            status: 'failed',
            generatedAltText: '',
            errorMessage: $errorMessage,
            websiteDomain: $websiteDomain,
        );
    }

    public function findEntries(
        int $page = 1,
        int $perPage = 25,
        string $statusFilter = '',
        string $sourceFilter = '',
        string $search = '',
        string $languageFilter = '',
    ): HistoryListResult {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $queryBuilder->from(self::TABLE);
        $this->applyFilters($queryBuilder, $statusFilter, $sourceFilter, $search, $languageFilter);

        $total = (int)(clone $queryBuilder)->count('uid')->executeQuery()->fetchOne();
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);

        $rows = $queryBuilder
            ->select('*')
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = $this->countByStatus($statusFilter, $sourceFilter, $search, $languageFilter);

        return new HistoryListResult(
            items: array_map($this->buildEntry(...), $rows),
            total: $total,
            successCount: $counts['success'],
            failedCount: $counts['failed'],
            page: $page,
            totalPages: $totalPages,
        );
    }

    /**
     * @return array<int, string>
     */
    public function getDistinctLanguages(): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $languages = $queryBuilder
            ->selectLiteral('DISTINCT language')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->neq('language', $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
            )
            ->orderBy('language', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_map('strval', $languages));
    }

    public function countSuccessfullyProcessedFileUids(): int
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        return (int)$queryBuilder
            ->selectLiteral('COUNT(DISTINCT file_uid)')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'status',
                    $queryBuilder->createNamedParameter('success', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * A localized ALT may be overwritten only when its current value is still
     * the value produced by AutoAlt.ai. A changed value is treated as a manual
     * translation and remains untouched.
     */
    public function wasGeneratedAltText(int $metadataUid, string $altText): bool
    {
        if ($metadataUid <= 0 || $altText === '') {
            return false;
        }

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('metadata_uid', $queryBuilder->createNamedParameter($metadataUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter('success', Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('generated_alt_text', $queryBuilder->createNamedParameter($altText, Connection::PARAM_STR))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function findByUid(int $uid): ?HistoryEntry
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? $this->buildEntry($row) : null;
    }

    public function updateGeneratedAltText(int $uid, string $altText): bool
    {
        $affected = $this->getConnection()->update(
            self::TABLE,
            [
                'tstamp' => time(),
                'generated_alt_text' => $altText,
            ],
            [
                'uid' => $uid,
                'status' => 'success',
            ]
        );

        return $affected > 0;
    }

    public function updateGeneratedTitle(int $uid, string $title): bool
    {
        $affected = $this->getConnection()->update(
            self::TABLE,
            [
                'tstamp' => time(),
                'generated_title' => $title,
            ],
            [
                'uid' => $uid,
                'status' => 'success',
            ]
        );

        return $affected > 0;
    }

    public function updateGeneratedDescription(int $uid, string $description): bool
    {
        $affected = $this->getConnection()->update(
            self::TABLE,
            [
                'tstamp' => time(),
                'generated_description' => $description,
            ],
            [
                'uid' => $uid,
                'status' => 'success',
            ]
        );

        return $affected > 0;
    }

    /**
     * @return array{success: int, failed: int}
     */
    private function countByStatus(string $statusFilter, string $sourceFilter, string $search = '', string $languageFilter = ''): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $queryBuilder->from(self::TABLE);
        $this->applyFilters($queryBuilder, $statusFilter, $sourceFilter, $search, $languageFilter);

        $rows = (clone $queryBuilder)
            ->select('status')
            ->addSelectLiteral('COUNT(uid) AS total')
            ->groupBy('status')
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = ['success' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int)($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    private function applyFilters(
        QueryBuilder $queryBuilder,
        string $statusFilter,
        string $sourceFilter,
        string $search = '',
        string $languageFilter = '',
    ): void {
        if ($statusFilter !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter($statusFilter, Connection::PARAM_STR))
            );
        }

        if ($sourceFilter !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('source', $queryBuilder->createNamedParameter($sourceFilter, Connection::PARAM_STR))
            );
        }

        if ($languageFilter !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('language', $queryBuilder->createNamedParameter($languageFilter, Connection::PARAM_STR))
            );
        }

        if ($search !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->like(
                    'file_name',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($search) . '%', Connection::PARAM_STR)
                )
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildEntry(array $row): HistoryEntry
    {
        return new HistoryEntry(
            uid: (int)$row['uid'],
            fileUid: (int)$row['file_uid'],
            metadataUid: (int)($row['metadata_uid'] ?? 0),
            fileName: (string)($row['file_name'] ?? ''),
            fileIdentifier: (string)($row['file_identifier'] ?? ''),
            source: (string)($row['source'] ?? 'bulk'),
            language: (string)($row['language'] ?? ''),
            status: (string)($row['status'] ?? 'success'),
            generatedAltText: (string)($row['generated_alt_text'] ?? ''),
            generatedTitle: (string)($row['generated_title'] ?? ''),
            generatedDescription: (string)($row['generated_description'] ?? ''),
            errorMessage: (string)($row['error_message'] ?? ''),
            crdate: (int)($row['crdate'] ?? 0),
        );
    }

    private function insert(
        int $fileUid,
        int $metadataUid,
        string $fileIdentifier,
        string $fileName,
        string $source,
        string $language,
        string $status,
        string $generatedAltText,
        string $errorMessage,
        string $websiteDomain,
        string $generatedTitle = '',
        string $generatedDescription = '',
    ): void {
        $now = time();
        $connection = $this->getConnection();
        $connection->insert(self::TABLE, [
            'pid' => 0,
            'crdate' => $now,
            'tstamp' => $now,
            'file_uid' => $fileUid,
            'metadata_uid' => $metadataUid,
            'file_identifier' => $fileIdentifier,
            'file_name' => $fileName,
            'source' => in_array($source, self::VALID_SOURCES, true) ? $source : 'bulk',
            'language' => $language,
            'status' => $status,
            'generated_alt_text' => $generatedAltText,
            'generated_title' => $generatedTitle,
            'generated_description' => $generatedDescription,
            'error_message' => mb_substr($errorMessage, 0, 2000),
            'website_domain' => $websiteDomain,
        ]);

        $this->prune((int)$connection->lastInsertId());
    }

    private function prune(int $insertedUid): void
    {
        // Keeping the exact limit is not worth two extra queries for every
        // generated field and every language. Periodic pruning keeps the table
        // bounded (at most 99 entries beyond the limit) during large imports.
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
