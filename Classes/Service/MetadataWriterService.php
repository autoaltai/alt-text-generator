<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class MetadataWriterService
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private MetaDataRepository $metaDataRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function updateGeneratedFields(
        int $fileUid,
        int $metadataUid,
        string $altText,
        string $title = '',
        string $description = '',
    ): array {
        $updateData = ['alternative' => $altText];
        if ($title !== '') {
            $updateData['title'] = $title;
        }
        if ($description !== '') {
            $updateData['description'] = $description;
        }

        return $this->updateFields($fileUid, $metadataUid, $updateData);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateSingleField(int $fileUid, int $metadataUid, string $fieldName, string $value): array
    {
        if (!in_array($fieldName, ['alternative', 'title', 'description'], true)) {
            throw new \InvalidArgumentException('Unsupported metadata field "' . $fieldName . '".', 1754380800);
        }

        return $this->updateFields($fileUid, $metadataUid, [$fieldName => $value]);
    }

    /**
     * Applies the per-field actions selected in Bulk Generate. "Keep" is
     * deliberately omitted from the update payload, while "Clear" writes an
     * empty value even when no AI request was needed for the image.
     *
     * @param array{alternative?: string, title?: string, description?: string} $generatedValues
     * @param array{alternative: string, title: string, description: string} $fieldActions
     * @return array<string, mixed>
     */
    public function applyFieldActions(int $fileUid, int $metadataUid, array $generatedValues, array $fieldActions): array
    {
        $updateData = [];
        foreach (['alternative', 'title', 'description'] as $fieldName) {
            $action = $fieldActions[$fieldName];
            if ($action === 'clear') {
                $updateData[$fieldName] = '';
            } elseif ($action === 'generate' && array_key_exists($fieldName, $generatedValues)) {
                $updateData[$fieldName] = $generatedValues[$fieldName];
            }
        }

        if ($updateData === []) {
            return $this->resolveMetadata($fileUid, $metadataUid);
        }

        return $this->updateFields($fileUid, $metadataUid, $updateData);
    }

    /**
     * @param array<string, mixed> $updateData
     * @return array<string, mixed>
     */
    private function updateFields(int $fileUid, int $metadataUid, array $updateData): array
    {
        $metadata = $this->resolveMetadata($fileUid, $metadataUid);

        return $this->metaDataRepository->update($fileUid, $updateData, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveMetadata(int $fileUid, int $metadataUid = 0): array
    {
        $metadata = $this->findExistingMetadata($fileUid, $metadataUid);
        if ($metadata !== []) {
            return $metadata;
        }

        return $this->metaDataRepository->createMetaDataRecord($fileUid);
    }

    /**
     * @return array<string, mixed>
     */
    public function findExistingMetadata(int $fileUid, int $metadataUid = 0): array
    {
        if ($metadataUid > 0) {
            $metadata = $this->findByUidAndFileUid($metadataUid, $fileUid);
            if ($metadata !== []) {
                return $metadata;
            }
        }

        return $this->metaDataRepository->findByFileUid($fileUid);
    }

    /**
     * Finds a native localized sys_file_metadata record. Every localized
     * record must point directly at the canonical language-0 metadata record.
     *
     * @return array<string, mixed>
     */
    public function findLocalizedMetadata(int $fileUid, int $defaultMetadataUid, int $languageId): array
    {
        if ($defaultMetadataUid <= 0 || $languageId <= 0) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $row = $queryBuilder
            ->select('*')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($defaultMetadataUid, Connection::PARAM_INT))
            )
            ->orderBy('uid', 'ASC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : [];
    }

    /**
     * Creates a translation through DataHandler's normal "localize" command,
     * then writes the translated fields through MetaDataRepository. This
     * preserves TYPO3's language fields, parent relation and diff source.
     *
     * @return array<string, mixed>
     */
    public function saveLocalizedFields(
        int $fileUid,
        int $defaultMetadataUid,
        int $languageId,
        string $altText,
        string $title = '',
        string $description = '',
    ): array
    {
        if ($defaultMetadataUid <= 0 || $languageId <= 0 || trim($altText) === '') {
            throw new \InvalidArgumentException('A default metadata record, target language and translated ALT text are required.', 1775347200);
        }
        $defaultMetadata = $this->findByUidAndFileUid($defaultMetadataUid, $fileUid);
        if ($defaultMetadata === [] || (int)($defaultMetadata['sys_language_uid'] ?? 0) !== 0) {
            throw new \InvalidArgumentException('Localized metadata must be created from its language-0 source record.', 1775347203);
        }

        $metadata = $this->findLocalizedMetadata($fileUid, $defaultMetadataUid, $languageId);
        if ($metadata === []) {
            $this->localizeMetadata($defaultMetadataUid, $languageId);
            $metadata = $this->findLocalizedMetadata($fileUid, $defaultMetadataUid, $languageId);
        }
        if ($metadata === []) {
            throw new \RuntimeException(sprintf('TYPO3 could not create metadata translation %d for record %d.', $languageId, $defaultMetadataUid), 1775347201);
        }

        $updateData = ['alternative' => $altText];
        if ($title !== '') {
            $updateData['title'] = $title;
        }
        if ($description !== '') {
            $updateData['description'] = $description;
        }

        return $this->metaDataRepository->update($fileUid, $updateData, $metadata);
    }

    /**
     * @return array<string, mixed>
     */
    private function findByUidAndFileUid(int $metadataUid, int $fileUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $row = $queryBuilder
            ->select('*')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($metadataUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : [];
    }

    private function localizeMetadata(int $defaultMetadataUid, int $languageId): void
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof \TYPO3\CMS\Core\Authentication\BackendUserAuthentication) {
            throw new \RuntimeException('TYPO3 backend user context is required to localize file metadata.', 1775347202);
        }

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'sys_file_metadata' => [
                $defaultMetadataUid => ['localize' => $languageId],
            ],
        ], $backendUser);
        $dataHandler->process_cmdmap();
    }
}
