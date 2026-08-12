<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final readonly class RenameFileQueryService
{
    private const CHUNK_SIZE = 250;

    public function __construct(
        private ConnectionPool $connectionPool,
        private ResourceFactory $resourceFactory,
        private FilenameQualityService $filenameQualityService,
        private RenameHistoryRepository $historyRepository,
        private FileAccessService $fileAccessService,
    ) {}

    /**
     * @param array{tab:string, search:string, referencedOnly:bool, skipProcessed:bool, page:int, limit:int} $filter
     * @return array{items:list<array<string,mixed>>, total:int, page:int, pages:int, limit:int}
     */
    public function find(array $filter, string $allowedExtensions, BackendUserAuthentication $backendUser): array
    {
        if ($filter['tab'] === 'history') {
            return $this->findHistory($filter);
        }

        $extensions = $this->normalizeExtensions($allowedExtensions);
        $offset = ($filter['page'] - 1) * $filter['limit'];
        if ($filter['tab'] === 'poor') {
            [$rows, $total] = $this->findPoorRows($filter, $extensions, $offset, $filter['limit']);
        } else {
            $total = $this->countFileRows($filter, $extensions);
            $rows = $this->fetchFileRows($filter, $extensions, $offset, $filter['limit']);
        }

        $items = [];
        foreach ($rows as $row) {
            try {
                $file = $this->resourceFactory->getFileObject((int)$row['uid'], $row);
                if (!$this->fileAccessService->canReadFile($backendUser, $file)) {
                    continue;
                }
                $items[] = $this->buildFileItem($file, $row, $backendUser);
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->pageResult($items, $total, $filter['page'], $filter['limit']);
    }

    /** @return array{total:int, poor:int, renamed:int, history:int} */
    public function getStatistics(string $allowedExtensions): array
    {
        $filter = ['tab' => 'all', 'search' => '', 'referencedOnly' => false, 'skipProcessed' => false, 'page' => 1, 'limit' => 10];
        $extensions = $this->normalizeExtensions($allowedExtensions);
        $total = $this->countFileRows($filter, $extensions);
        [, $poor] = $this->findPoorRows($filter, $extensions, PHP_INT_MAX, 1);

        return [
            'total' => $total,
            'poor' => $poor,
            'renamed' => $this->historyRepository->countSuccessfullyRenamedFiles(),
            'history' => $this->historyRepository->countEntries(),
        ];
    }

    /**
     * @param list<string> $extensions
     * @return array{0:list<array<string,mixed>>,1:int}
     */
    private function findPoorRows(array $filter, array $extensions, int $wantedOffset, int $limit): array
    {
        $matched = 0;
        $selected = [];
        $databaseOffset = 0;
        do {
            $rows = $this->fetchFileRows($filter, $extensions, $databaseOffset, self::CHUNK_SIZE);
            foreach ($rows as $row) {
                if (!$this->filenameQualityService->isPoor((string)$row['name'])) {
                    continue;
                }
                if ($matched >= $wantedOffset && count($selected) < $limit) {
                    $selected[] = $row;
                }
                ++$matched;
            }
            $databaseOffset += self::CHUNK_SIZE;
        } while (count($rows) === self::CHUNK_SIZE);

        return [$selected, $matched];
    }

    /** @param list<string> $extensions */
    private function fetchFileRows(array $filter, array $extensions, int $offset, int $limit): array
    {
        $queryBuilder = $this->createFileQuery($filter, $extensions);
        $queryBuilder
            ->select('f.*')
            ->addSelectLiteral(
                '(SELECT COUNT(*) FROM ' . RenameHistoryRepository::TABLE . " rh_count WHERE rh_count.file_uid = f.uid AND rh_count.status IN ('success', 'undone')) AS autoalt_rename_count",
                '(SELECT rh_undo.uid FROM ' . RenameHistoryRepository::TABLE . " rh_undo WHERE rh_undo.file_uid = f.uid AND rh_undo.status = 'success' AND rh_undo.rename_method <> 'undo' ORDER BY rh_undo.uid DESC LIMIT 1) AS autoalt_undo_history_uid",
                '(SELECT rh_undo_name.new_filename FROM ' . RenameHistoryRepository::TABLE . " rh_undo_name WHERE rh_undo_name.file_uid = f.uid AND rh_undo_name.status = 'success' AND rh_undo_name.rename_method <> 'undo' ORDER BY rh_undo_name.uid DESC LIMIT 1) AS autoalt_undo_new_filename",
                '(SELECT rh_original.old_filename FROM ' . RenameHistoryRepository::TABLE . ' rh_original WHERE rh_original.file_uid = f.uid ORDER BY rh_original.uid ASC LIMIT 1) AS autoalt_original_filename',
            )
            ->orderBy('f.uid', 'DESC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit));

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /** @param list<string> $extensions */
    private function countFileRows(array $filter, array $extensions): int
    {
        return (int)$this->createFileQuery($filter, $extensions)
            ->count('f.uid')
            ->executeQuery()
            ->fetchOne();
    }

    /** @param list<string> $extensions */
    private function createFileQuery(array $filter, array $extensions): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->from('sys_file', 'f')->where(
            $queryBuilder->expr()->eq('f.type', $queryBuilder->createNamedParameter(FileType::IMAGE->value, ParameterType::INTEGER)),
            $queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
        );
        if ($extensions !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('f.extension', $queryBuilder->createNamedParameter($extensions, Connection::PARAM_STR_ARRAY)));
        }
        if ($filter['referencedOnly']) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM sys_file_reference sfr WHERE sfr.uid_local = f.uid AND sfr.deleted = 0)');
        }
        if ($filter['skipProcessed'] && $filter['tab'] !== 'history') {
            $queryBuilder->andWhere("NOT EXISTS (SELECT 1 FROM " . RenameHistoryRepository::TABLE . " rh_processed WHERE rh_processed.file_uid = f.uid AND rh_processed.status IN ('success', 'undone'))");
        }
        $search = trim($filter['search']);
        if ($search !== '') {
            $like = '%' . $queryBuilder->escapeLikeWildcards($search) . '%';
            $conditions = [
                $queryBuilder->expr()->like('f.name', $queryBuilder->createNamedParameter($like)),
                $queryBuilder->expr()->like('f.identifier', $queryBuilder->createNamedParameter($like)),
                'EXISTS (SELECT 1 FROM ' . RenameHistoryRepository::TABLE . ' rh_search WHERE rh_search.file_uid = f.uid AND (rh_search.old_filename LIKE ' . $queryBuilder->createNamedParameter($like) . ' OR rh_search.new_filename LIKE ' . $queryBuilder->createNamedParameter($like) . '))',
            ];
            if (ctype_digit($search)) {
                $conditions[] = $queryBuilder->expr()->eq('f.uid', $queryBuilder->createNamedParameter((int)$search, ParameterType::INTEGER));
            }
            $queryBuilder->andWhere($queryBuilder->expr()->or(...$conditions));
        }

        return $queryBuilder;
    }

    /** @return array{items:list<array<string,mixed>>, total:int, page:int, pages:int, limit:int} */
    private function findHistory(array $filter): array
    {
        $base = function () use ($filter): QueryBuilder {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(RenameHistoryRepository::TABLE);
            $queryBuilder->from(RenameHistoryRepository::TABLE, 'h');
            if ($filter['referencedOnly']) {
                $queryBuilder->andWhere('EXISTS (SELECT 1 FROM sys_file_reference sfr WHERE sfr.uid_local = h.file_uid AND sfr.deleted = 0)');
            }
            $search = trim($filter['search']);
            if ($search !== '') {
                $like = '%' . $queryBuilder->escapeLikeWildcards($search) . '%';
                $conditions = [
                    $queryBuilder->expr()->like('h.old_filename', $queryBuilder->createNamedParameter($like)),
                    $queryBuilder->expr()->like('h.new_filename', $queryBuilder->createNamedParameter($like)),
                    $queryBuilder->expr()->like('h.old_identifier', $queryBuilder->createNamedParameter($like)),
                    $queryBuilder->expr()->like('h.new_identifier', $queryBuilder->createNamedParameter($like)),
                ];
                if (ctype_digit($search)) {
                    $conditions[] = $queryBuilder->expr()->eq('h.file_uid', $queryBuilder->createNamedParameter((int)$search, ParameterType::INTEGER));
                }
                $queryBuilder->andWhere($queryBuilder->expr()->or(...$conditions));
            }
            return $queryBuilder;
        };

        $total = (int)$base()->count('h.uid')->executeQuery()->fetchOne();
        $rows = $base()
            ->select('h.*')
            ->orderBy('h.uid', 'DESC')
            ->setFirstResult(($filter['page'] - 1) * $filter['limit'])
            ->setMaxResults($filter['limit'])
            ->executeQuery()
            ->fetchAllAssociative();

        $fileRows = $this->fetchFileRowsByUid(array_column($rows, 'file_uid'));
        $items = [];
        foreach ($rows as $row) {
            $file = null;
            $fileUid = (int)$row['file_uid'];
            if (isset($fileRows[$fileUid])) {
                try {
                    $file = $this->resourceFactory->getFileObject($fileUid, $fileRows[$fileUid]);
                } catch (\Throwable) {
                }
            }
            $row['file'] = $file;
            $row['undoable'] = $file instanceof File
                && $row['status'] === 'success'
                && $row['rename_method'] !== 'undo'
                && (int)$row['undone_at'] === 0
                && $file->getName() === $row['new_filename'];
            $row['statusClass'] = $row['status'] === 'error' ? 'danger' : ($row['status'] === 'undone' ? 'warning' : 'success');
            $items[] = $row;
        }

        return $this->pageResult($items, $total, $filter['page'], $filter['limit']);
    }

    /** @param list<int|string> $uids @return array<int,array<string,mixed>> */
    private function fetchFileRowsByUid(array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $rows = $queryBuilder->select('*')->from('sys_file')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()->fetchAllAssociative();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['uid']] = $row;
        }
        return $indexed;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function buildFileItem(File $file, array $row, BackendUserAuthentication $backendUser): array
    {
        $reasons = $this->filenameQualityService->getPoorFilenameReasons($file->getName());
        $storage = $file->getStorage();
        $canRename = $storage->isOnline() && $storage->isWritable()
            && ($backendUser->isAdmin() || $storage->checkFileActionPermission('rename', $file));
        if (!$storage->isOnline()) {
            $status = 'error';
        } elseif (!$storage->isWritable()) {
            $status = 'read_only';
        } elseif ((int)($row['autoalt_rename_count'] ?? 0) > 0) {
            $status = 'renamed';
        } elseif ($reasons !== []) {
            $status = 'poor';
        } else {
            $status = 'good';
        }

        return [
            'uid' => $file->getUid(),
            'file' => $file,
            'filename' => $file->getName(),
            'identifier' => $file->getIdentifier(),
            'combinedIdentifier' => $file->getCombinedIdentifier(),
            'status' => $status,
            'reasons' => $reasons,
            'canRename' => $canRename,
            'undoable' => (int)($row['autoalt_undo_history_uid'] ?? 0) > 0
                && (string)($row['autoalt_undo_new_filename'] ?? '') === $file->getName(),
            'originalFilename' => (string)($row['autoalt_original_filename'] ?? ''),
        ];
    }

    /** @return list<string> */
    private function normalizeExtensions(string $allowedExtensions): array
    {
        return array_values(array_unique(preg_split('/[,\s]+/', strtolower($allowedExtensions), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    private function pageResult(array $items, int $total, int $page, int $limit): array
    {
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $limit)), 'limit' => $limit];
    }
}
