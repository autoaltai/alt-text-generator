<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\ImageScanItem;
use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final readonly class ImageScannerService
{
    private const DEFAULT_IMAGE_EXTENSIONS = 'jpg,jpeg,png,webp,gif,avif';
    private const MINIMUM_DIMENSION = 30;

    public const MODE_MISSING = 'missing';
    public const MODE_ALL = 'all';
    public const MODE_SHORT = 'short';

    public function __construct(
        private ConnectionPool $connectionPool,
        private ResourceFactory $resourceFactory,
        private FileGenerateRequestFactory $generateRequestFactory,
        private FileAccessService $fileAccessService,
    ) {}

    /**
     * Recursively resolves every generable image file uid within the given
     * folders (and their subfolders) - used when a user selects a folder in
     * the File List rather than picking individual files.
     *
     * @param array<int, string> $combinedFolderIdentifiers
     * @return array<int, int>
     */
    public function findImageUidsInFolders(array $combinedFolderIdentifiers, ?BackendUserAuthentication $backendUser = null): array
    {
        $fileUids = [];
        foreach ($combinedFolderIdentifiers as $combinedIdentifier) {
            try {
                $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            } catch (\Throwable) {
                continue;
            }
            if (!$folder instanceof Folder) {
                continue;
            }
            if ($backendUser !== null && !$this->fileAccessService->canReadFolder($backendUser, $folder)) {
                continue;
            }

            foreach ($folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, true) as $file) {
                if (
                    $this->generateRequestFactory->isGenerableImage($file)
                    && ($backendUser === null || $this->fileAccessService->canGenerateForFile($backendUser, $file))
                ) {
                    $fileUids[] = $file->getUid();
                }
            }
        }

        return array_values(array_unique($fileUids));
    }

    public function scan(string $allowedImageExtensions = self::DEFAULT_IMAGE_EXTENSIONS, int $limit = 10, int $shortAltTextLength = 40): ImageScanResult
    {
        $extensions = $this->normalizeExtensions($allowedImageExtensions);
        $totalImages = $this->countImages($extensions, self::MODE_ALL);
        $missingAltText = $this->countImages($extensions, self::MODE_MISSING);
        $shortAltText = $this->countImages($extensions, self::MODE_SHORT, $shortAltTextLength);
        $items = $this->fetchImages($extensions, self::MODE_MISSING, $limit, [], $shortAltTextLength);

        return new ImageScanResult(
            totalImages: $totalImages,
            missingAltText: $missingAltText,
            withAltText: max(0, $totalImages - $missingAltText),
            shortAltText: $shortAltText,
            items: $items,
        );
    }

    /**
     * @param array<int, int> $excludedFileUids
     * @return array<int, ImageScanItem>
     */
    public function findEligibleImages(
        string $allowedImageExtensions,
        string $mode,
        int $limit = 50,
        array $excludedFileUids = [],
        int $shortAltTextLength = 40,
        bool $excludeSuccessfullyProcessed = false,
        int $afterFileUid = 0,
    ): array {
        return $this->fetchImages(
            $this->normalizeExtensions($allowedImageExtensions),
            $mode,
            $limit,
            $excludedFileUids,
            $shortAltTextLength,
            $excludeSuccessfullyProcessed,
            $afterFileUid
        );
    }

    /**
     * Fetches specific files by uid regardless of their current alt-text state,
     * for explicit user selections (e.g. picked in the File List) rather than
     * scan-mode discovery. Non-image files and missing files are skipped.
     *
     * @param array<int, int> $fileUids
     * @return array<int, ImageScanItem>
     */
    public function findByFileUids(array $fileUids, ?BackendUserAuthentication $backendUser = null): array
    {
        $fileUids = array_values(array_unique(array_filter(array_map('intval', $fileUids), static fn(int $uid): bool => $uid > 0)));
        if ($fileUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder
            ->from('sys_file', 'sf')
            ->leftJoin(
                'sf',
                'sys_file_metadata',
                'sfm',
                'sfm.file = sf.uid AND sfm.sys_language_uid = 0'
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'sf.type',
                    $queryBuilder->createNamedParameter(FileType::IMAGE->value, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sf.missing',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'sf.uid',
                    $queryBuilder->createNamedParameter($fileUids, Connection::PARAM_INT_ARRAY)
                )
            );

        $rows = $queryBuilder
            ->select('sf.uid', 'sf.storage', 'sf.identifier', 'sf.name', 'sf.mime_type', 'sf.size')
            ->addSelectLiteral(
                'sfm.uid AS metadata_uid',
                'sfm.alternative',
                'sfm.width',
                'sfm.height'
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $items = array_map($this->buildScanItem(...), $rows);
        if ($backendUser === null || $backendUser->isAdmin()) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn(ImageScanItem $item): bool => $this->fileAccessService->canGenerateForFileUid($backendUser, $item->fileUid)
        ));
    }

    public function countByMode(string $allowedImageExtensions, string $mode, int $shortAltTextLength = 40): int
    {
        return $this->countImages($this->normalizeExtensions($allowedImageExtensions), $mode, $shortAltTextLength);
    }

    /**
     * Counts images matching the given bulk-generation mode, excluding specific file uids
     * (e.g. already-processed files). Used to live-preview how many images a bulk run
     * would actually pick up before it starts.
     *
     * @param array<int, int> $excludedFileUids
     */
    public function countEligible(
        string $allowedImageExtensions,
        string $mode,
        array $excludedFileUids = [],
        int $shortAltTextLength = 40,
        bool $excludeSuccessfullyProcessed = false,
        int $afterFileUid = 0,
    ): int {
        return $this->countImages(
            $this->normalizeExtensions($allowedImageExtensions),
            $mode,
            $shortAltTextLength,
            $excludedFileUids,
            $excludeSuccessfullyProcessed,
            $afterFileUid
        );
    }

    /**
     * @param array<int, string> $extensions
     * @param array<int, int> $excludedFileUids
     */
    private function countImages(
        array $extensions,
        string $mode,
        int $shortAltTextLength = 40,
        array $excludedFileUids = [],
        bool $excludeSuccessfullyProcessed = false,
        int $afterFileUid = 0,
    ): int
    {
        $queryBuilder = $this->createBaseQueryBuilder($extensions, $mode, $shortAltTextLength);
        $this->applyBulkCursorConstraints($queryBuilder, $excludedFileUids, $excludeSuccessfullyProcessed, $afterFileUid);

        return (int)$queryBuilder
            ->count('sf.uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param array<int, string> $extensions
     * @param array<int, int> $excludedFileUids
     * @return array<int, ImageScanItem>
     */
    private function fetchImages(
        array $extensions,
        string $mode,
        int $limit,
        array $excludedFileUids,
        int $shortAltTextLength,
        bool $excludeSuccessfullyProcessed = false,
        int $afterFileUid = 0,
    ): array
    {
        $queryBuilder = $this->createBaseQueryBuilder($extensions, $mode, $shortAltTextLength);
        $this->applyBulkCursorConstraints($queryBuilder, $excludedFileUids, $excludeSuccessfullyProcessed, $afterFileUid);

        $rows = $queryBuilder
            ->select('sf.uid', 'sf.storage', 'sf.identifier', 'sf.name', 'sf.mime_type', 'sf.size')
            ->addSelectLiteral(
                'sfm.uid AS metadata_uid',
                'sfm.alternative',
                'sfm.width',
                'sfm.height'
            )
            ->orderBy('sf.uid', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->executeQuery()
            ->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->buildScanItem($row);
        }

        return $items;
    }

    /**
     * @param array<int, string> $extensions
     */
    private function createBaseQueryBuilder(array $extensions, string $mode, int $shortAltTextLength = 40): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder
            ->from('sys_file', 'sf')
            ->leftJoin(
                'sf',
                'sys_file_metadata',
                'sfm',
                'sfm.file = sf.uid AND sfm.sys_language_uid = 0'
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'sf.type',
                    $queryBuilder->createNamedParameter(FileType::IMAGE->value, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sf.missing',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $this->buildMinimumDimensionConstraint($queryBuilder)
            );

        $extensionConstraint = $this->buildExtensionConstraint($queryBuilder, $extensions);
        if ($extensionConstraint !== '') {
            $queryBuilder->andWhere($extensionConstraint);
        }

        if ($mode === self::MODE_MISSING) {
            $queryBuilder->andWhere($this->buildMissingAltTextConstraint($queryBuilder));
        } elseif ($mode === self::MODE_SHORT) {
            $queryBuilder->andWhere($this->buildShortAltTextConstraint($queryBuilder, $shortAltTextLength));
        }

        return $queryBuilder;
    }

    /**
     * @param array<int, int> $excludedFileUids
     */
    private function applyBulkCursorConstraints(
        QueryBuilder $queryBuilder,
        array $excludedFileUids,
        bool $excludeSuccessfullyProcessed,
        int $afterFileUid,
    ): void {
        $afterFileUid = max(0, $afterFileUid);
        if ($afterFileUid > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lt(
                    'sf.uid',
                    $queryBuilder->createNamedParameter($afterFileUid, Connection::PARAM_INT)
                )
            );
        }

        $excludedFileUids = array_values(array_unique(array_filter(array_map('intval', $excludedFileUids))));
        if ($excludedFileUids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->notIn(
                    'sf.uid',
                    $queryBuilder->createNamedParameter($excludedFileUids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        if ($excludeSuccessfullyProcessed) {
            $queryBuilder->andWhere($this->buildSuccessfullyProcessedExclusion($queryBuilder));
        }

    }

    /**
     * Skips images with known, tiny (e.g. spacer/icon) dimensions to avoid wasting
     * API credits on decorative images. Files whose dimensions are not indexed yet
     * (NULL/0) are not excluded, since those values mean "unknown", not "tiny".
     */
    private function buildMinimumDimensionConstraint(QueryBuilder $queryBuilder): string
    {
        return (string)$queryBuilder->expr()->and(
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->isNull('sfm.width'),
                $queryBuilder->expr()->eq('sfm.width', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('sfm.width', $queryBuilder->createNamedParameter(self::MINIMUM_DIMENSION, Connection::PARAM_INT))
            ),
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->isNull('sfm.height'),
                $queryBuilder->expr()->eq('sfm.height', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('sfm.height', $queryBuilder->createNamedParameter(self::MINIMUM_DIMENSION, Connection::PARAM_INT))
            )
        );
    }

    /**
     * @param array<int, string> $extensions
     */
    private function buildExtensionConstraint(QueryBuilder $queryBuilder, array $extensions): string
    {
        if ($extensions === []) {
            return '';
        }

        $lowerName = 'LOWER(' . $queryBuilder->quoteIdentifier('sf.name') . ')';
        $constraints = [];
        foreach ($extensions as $extension) {
            $constraints[] = $lowerName . ' LIKE ' . $queryBuilder->createNamedParameter('%.' . $extension, Connection::PARAM_STR);
        }

        return (string)$queryBuilder->expr()->or(...$constraints);
    }

    private function buildMissingAltTextConstraint(QueryBuilder $queryBuilder): string
    {
        return (string)$queryBuilder->expr()->or(
            $queryBuilder->expr()->isNull('sfm.uid'),
            $queryBuilder->expr()->isNull('sfm.alternative'),
            "TRIM(sfm.alternative) = ''"
        );
    }

    private function buildShortAltTextConstraint(QueryBuilder $queryBuilder, int $shortAltTextLength): string
    {
        $maxLength = max(1, $shortAltTextLength);

        return (string)$queryBuilder->expr()->and(
            $queryBuilder->expr()->isNotNull('sfm.alternative'),
            "TRIM(sfm.alternative) != ''",
            'CHAR_LENGTH(TRIM(' . $queryBuilder->quoteIdentifier('sfm.alternative') . ')) <= ' . $maxLength
        );
    }

    private function buildSuccessfullyProcessedExclusion(QueryBuilder $queryBuilder): string
    {
        return 'NOT EXISTS ('
            . 'SELECT 1 FROM tx_alttextgenerator_history autoalt_history '
            . 'WHERE autoalt_history.file_uid = sf.uid '
            . 'AND autoalt_history.status = ' . $queryBuilder->createNamedParameter('success', Connection::PARAM_STR)
            . ')';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildScanItem(array $row): ImageScanItem
    {
        $publicUrl = null;
        try {
            $file = $this->resourceFactory->getFileObject((int)$row['uid'], [
                'uid' => (int)$row['uid'],
                'storage' => (int)$row['storage'],
                'identifier' => (string)$row['identifier'],
                'name' => (string)$row['name'],
                'mime_type' => (string)($row['mime_type'] ?? ''),
                'size' => (int)($row['size'] ?? 0),
                'type' => FileType::IMAGE->value,
            ]);
            $publicUrl = $file->getPublicUrl();
        } catch (\Throwable) {
            $publicUrl = null;
        }

        return new ImageScanItem(
            fileUid: (int)$row['uid'],
            metadataUid: (int)($row['metadata_uid'] ?? 0),
            storageUid: (int)$row['storage'],
            identifier: (string)$row['identifier'],
            name: (string)$row['name'],
            extension: strtolower((string)pathinfo((string)$row['name'], PATHINFO_EXTENSION)),
            mimeType: (string)($row['mime_type'] ?? ''),
            size: (int)($row['size'] ?? 0),
            width: (int)($row['width'] ?? 0),
            height: (int)($row['height'] ?? 0),
            alternative: (string)($row['alternative'] ?? ''),
            publicUrl: $publicUrl,
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeExtensions(string $allowedImageExtensions): array
    {
        $extensions = preg_split('/[,\\s]+/', strtolower($allowedImageExtensions), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $extensions = array_map(static fn(string $extension): string => preg_replace('/[^a-z0-9]/', '', $extension) ?? '', $extensions);
        $extensions = array_filter(array_unique($extensions));

        return array_values($extensions);
    }
}
