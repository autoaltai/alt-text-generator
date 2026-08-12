<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final readonly class PermissionsService
{
    public function canManageSettings(BackendUserAuthentication $backendUser): bool
    {
        return $backendUser->isAdmin() || $this->isTsConfigEnabled($backendUser, 'manageSettings', false);
    }

    public function canRunBulkGeneration(BackendUserAuthentication $backendUser): bool
    {
        return $backendUser->isAdmin() || $this->isTsConfigEnabled($backendUser, 'runBulkGeneration', true);
    }

    public function canUseBulkGeneration(BackendUserAuthentication $backendUser): bool
    {
        return $this->canRunBulkGeneration($backendUser) && $this->canEditFileMetadata($backendUser);
    }

    public function canGenerateSingle(BackendUserAuthentication $backendUser): bool
    {
        return $backendUser->isAdmin() || $this->isTsConfigEnabled($backendUser, 'generateSingle', true);
    }

    public function canUseSingleGeneration(BackendUserAuthentication $backendUser): bool
    {
        return $this->canGenerateSingle($backendUser) && $this->canEditFileMetadata($backendUser);
    }

    public function canEditFileMetadata(BackendUserAuthentication $backendUser): bool
    {
        return $backendUser->isAdmin() || $backendUser->check('tables_modify', 'sys_file_metadata');
    }

    private function isTsConfigEnabled(BackendUserAuthentication $backendUser, string $key, bool $default): bool
    {
        $value = $backendUser->getTSConfig()['tx_alttextgenerator.']['permissions.'][$key] ?? null;
        if ($value === null) {
            return $default;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }
}
