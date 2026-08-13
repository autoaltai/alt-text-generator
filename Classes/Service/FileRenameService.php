<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\FileRenameResult;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final readonly class FileRenameService
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private FilenameNormalizationService $filenameNormalizationService,
        private RenameHistoryRepository $historyRepository,
        private FileGenerateRequestFactory $fileGenerateRequestFactory,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {}

    public function rename(
        int $fileUid,
        string $requestedBasename,
        string $method,
        BackendUserAuthentication $backendUser,
        string $apiRequestId = '',
    ): FileRenameResult {
        if ($fileUid <= 0 || !in_array($method, ['manual', 'ai'], true)) {
            return new FileRenameResult(false, $fileUid, message: 'Invalid rename request.');
        }

        $locker = $this->lockFactory->createLocker('autoalt-file-rename-' . $fileUid);
        if (!$locker->acquire()) {
            return new FileRenameResult(false, $fileUid, message: 'This file is already being processed.');
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            return $this->renameFile($file, $requestedBasename, $method, $backendUser, $apiRequestId);
        } catch (\Throwable $exception) {
            $this->logger->error('AutoAlt.ai FAL rename failed.', ['fileUid' => $fileUid, 'method' => $method, 'exception' => $exception]);
            return new FileRenameResult(false, $fileUid, message: $this->safeMessage($exception));
        } finally {
            $locker->release();
        }
    }

    /**
     * Renames a file immediately after it has been added to a FAL storage.
     *
     * The event can run outside an interactive backend request (for example
     * during an import), where no BackendUserAuthentication is available. In
     * that case the rename is still limited to writable FAL storages; TYPO3's
     * normal upload workflow remains the authority that allowed the file to be
     * added in the first place.
     */
    public function renameAfterUpload(
        File $file,
        string $requestedBasename,
        string $apiRequestId = '',
        ?BackendUserAuthentication $backendUser = null,
    ): FileRenameResult {
        $fileUid = $file->getUid();
        if ($fileUid <= 0) {
            return new FileRenameResult(false, $fileUid, message: 'The uploaded file has no valid TYPO3 file UID.');
        }

        $locker = $this->lockFactory->createLocker('autoalt-file-rename-' . $fileUid);
        if (!$locker->acquire()) {
            return new FileRenameResult(false, $fileUid, message: 'This file is already being processed.');
        }

        try {
            return $this->renameFile($file, $requestedBasename, 'auto', $backendUser, $apiRequestId);
        } catch (\Throwable $exception) {
            $this->logger->error('AutoAlt.ai automatic FAL rename after upload failed.', [
                'fileUid' => $fileUid,
                'fileName' => $file->getName(),
                'exception' => $exception,
            ]);
            return new FileRenameResult(false, $fileUid, message: $this->safeMessage($exception));
        } finally {
            $locker->release();
        }
    }

    public function undo(int $fileUid, BackendUserAuthentication $backendUser): FileRenameResult
    {
        if ($fileUid <= 0) {
            return new FileRenameResult(false, $fileUid, message: 'Invalid file UID.');
        }
        $locker = $this->lockFactory->createLocker('autoalt-file-rename-' . $fileUid);
        if (!$locker->acquire()) {
            return new FileRenameResult(false, $fileUid, message: 'This file is already being processed.');
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            $history = $this->historyRepository->findLatestUndoable($fileUid, $file->getName());
            if ($history === null) {
                return new FileRenameResult(false, $fileUid, message: 'No valid rename is available to undo.');
            }
            $this->assertRenameAllowed($file, $backendUser);
            $oldFilename = (string)$history['old_filename'];
            if ($oldFilename === '' || $file->getStorage()->hasFileInFolder($oldFilename, $file->getParentFolder())) {
                return new FileRenameResult(false, $fileUid, message: 'The previous filename is already occupied and cannot be restored.');
            }

            $currentIdentifier = $file->getIdentifier();
            $currentFilename = $file->getName();
            $renamedFile = $file->rename($oldFilename, DuplicationBehavior::CANCEL);
            $historyUid = $this->historyRepository->add(
                fileUid: $fileUid,
                storageUid: $file->getStorage()->getUid(),
                oldIdentifier: $currentIdentifier,
                newIdentifier: $renamedFile->getIdentifier(),
                oldFilename: $currentFilename,
                newFilename: $renamedFile->getName(),
                method: 'undo',
                status: 'success',
                backendUserUid: (int)($backendUser->user['uid'] ?? 0),
                metadata: ['undoneHistoryUid' => (int)$history['uid']],
            );
            $this->historyRepository->markUndone((int)$history['uid']);

            return new FileRenameResult(true, $fileUid, $currentFilename, $renamedFile->getName(), historyUid: $historyUid);
        } catch (\Throwable $exception) {
            $this->logger->error('AutoAlt.ai FAL rename undo failed.', ['fileUid' => $fileUid, 'exception' => $exception]);
            return new FileRenameResult(false, $fileUid, message: $this->safeMessage($exception));
        } finally {
            $locker->release();
        }
    }

    private function renameFile(File $file, string $requestedBasename, string $method, ?BackendUserAuthentication $backendUser, string $apiRequestId): FileRenameResult
    {
        $this->assertRenameAllowed($file, $backendUser);
        $oldFilename = $file->getName();
        $oldIdentifier = $file->getIdentifier();
        $normalized = $this->filenameNormalizationService->normalize($requestedBasename, $file->getExtension());
        $target = $this->filenameNormalizationService->resolveAvailableName($file->getStorage(), $file->getParentFolder(), $normalized, $oldFilename);
        if (strcasecmp($target, $oldFilename) === 0) {
            return new FileRenameResult(false, $file->getUid(), $oldFilename, $oldFilename, 'The image already uses this filename.', skipped: true);
        }

        try {
            $renamedFile = $file->rename($target, DuplicationBehavior::CANCEL);
            if ($renamedFile->getName() !== $target) {
                throw new \RuntimeException('TYPO3 could not apply the requested filename.');
            }
            try {
                $historyUid = $this->historyRepository->add(
                    fileUid: $file->getUid(),
                    storageUid: $file->getStorage()->getUid(),
                    oldIdentifier: $oldIdentifier,
                    newIdentifier: $renamedFile->getIdentifier(),
                    oldFilename: $oldFilename,
                    newFilename: $renamedFile->getName(),
                    method: $method,
                    status: 'success',
                    backendUserUid: $this->backendUserUid($backendUser),
                    apiRequestId: $apiRequestId,
                );
            } catch (\Throwable $historyException) {
                $renamedFile->rename($oldFilename, DuplicationBehavior::CANCEL);
                throw new \RuntimeException('Rename history could not be saved; the file rename was rolled back.', 0, $historyException);
            }

            return new FileRenameResult(true, $file->getUid(), $oldFilename, $renamedFile->getName(), historyUid: $historyUid);
        } catch (\Throwable $exception) {
            try {
                $this->historyRepository->add(
                    fileUid: $file->getUid(),
                    storageUid: $file->getStorage()->getUid(),
                    oldIdentifier: $oldIdentifier,
                    newIdentifier: $oldIdentifier,
                    oldFilename: $oldFilename,
                    newFilename: $target,
                    method: $method,
                    status: 'error',
                    backendUserUid: $this->backendUserUid($backendUser),
                    errorMessage: $this->safeMessage($exception),
                    apiRequestId: $apiRequestId,
                );
            } catch (\Throwable $historyException) {
                $this->logger->error('AutoAlt.ai rename failure history could not be saved.', ['fileUid' => $file->getUid(), 'exception' => $historyException]);
            }
            throw $exception;
        }
    }

    private function assertRenameAllowed(File $file, ?BackendUserAuthentication $backendUser): void
    {
        if ($file->isMissing()) {
            throw new \RuntimeException('The physical file is missing.');
        }
        if (!$this->fileGenerateRequestFactory->isGenerableImage($file)) {
            throw new \RuntimeException('The file is not a supported image.');
        }
        $storage = $file->getStorage();
        if (!$storage->isOnline()) {
            throw new \RuntimeException('The file storage is offline.');
        }
        if (!$storage->isWritable()) {
            throw new \RuntimeException('The file storage is read-only.');
        }
        if ($backendUser !== null && !$backendUser->isAdmin() && !$storage->checkFileActionPermission('rename', $file)) {
            throw new \RuntimeException('You do not have permission to rename this file.');
        }
    }

    private function backendUserUid(?BackendUserAuthentication $backendUser): int
    {
        return (int)($backendUser?->user['uid'] ?? 0);
    }

    private function safeMessage(\Throwable $exception): string
    {
        $message = trim((string)preg_replace('/\s+/', ' ', $exception->getMessage()));
        $message = str_replace(
            array_filter([Environment::getProjectPath(), Environment::getPublicPath()]),
            '[internal path]',
            $message,
        );
        return mb_substr($message !== '' ? $message : 'The file could not be renamed.', 0, 500);
    }
}
