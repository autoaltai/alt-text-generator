<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final readonly class FileAccessService
{
    public function __construct(
        private ResourceFactory $resourceFactory,
    ) {}

    public function canReadFolder(BackendUserAuthentication $backendUser, Folder $folder): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }

        return $folder->getStorage()->checkFolderActionPermission('read', $folder);
    }

    public function canReadFile(BackendUserAuthentication $backendUser, File $file): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }

        return $file->getStorage()->checkFileActionPermission('read', $file);
    }

    public function canGenerateForFile(BackendUserAuthentication $backendUser, File $file): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }

        $storage = $file->getStorage();

        return $storage->checkFileActionPermission('read', $file)
            && $storage->checkFileActionPermission('editMeta', $file);
    }

    public function canGenerateForFileUid(BackendUserAuthentication $backendUser, int $fileUid): bool
    {
        if ($fileUid <= 0) {
            return false;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (\Throwable) {
            return false;
        }

        return $this->canGenerateForFile($backendUser, $file);
    }
}
