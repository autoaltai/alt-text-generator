<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Service\HistoryService;
use AutoAltAi\AltTextGenerator\Service\FileAccessService;
use AutoAltAi\AltTextGenerator\Service\MetadataWriterService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

#[AsController]
final readonly class HistoryAjaxController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';
    private const MAX_ALT_TEXT_LENGTH = 1000;
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 2000;

    public function __construct(
        private HistoryService $historyService,
        private FileAccessService $fileAccessService,
        private MetadataWriterService $metadataWriterService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function updateAltTextAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->canEditHistoryMetadata()) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $uid = (int)($body['uid'] ?? 0);
        $altText = trim(mb_substr(strip_tags((string)($body['altText'] ?? '')), 0, self::MAX_ALT_TEXT_LENGTH));

        if ($uid <= 0 || $altText === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 400);
        }

        $entry = $this->historyService->findByUid($uid);
        if ($entry === null || !$entry->isSuccessful() || $entry->fileUid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 404);
        }
        if (!$this->fileAccessService->canGenerateForFileUid($this->getBackendUser(), $entry->fileUid)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        try {
            $this->metadataWriterService->updateSingleField($entry->fileUid, $entry->metadataUid, 'alternative', $altText);
            $this->historyService->updateGeneratedAltText($uid, $altText);
        } catch (\Throwable) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.failed'),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'altText' => $altText,
        ]);
    }

    public function updateTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->canEditHistoryMetadata()) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $uid = (int)($body['uid'] ?? 0);
        $title = trim(mb_substr(strip_tags((string)($body['title'] ?? '')), 0, self::MAX_TITLE_LENGTH));

        if ($uid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 400);
        }

        $entry = $this->historyService->findByUid($uid);
        if ($entry === null || !$entry->isSuccessful() || $entry->fileUid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 404);
        }
        if (!$this->fileAccessService->canGenerateForFileUid($this->getBackendUser(), $entry->fileUid)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        try {
            $this->metadataWriterService->updateSingleField($entry->fileUid, $entry->metadataUid, 'title', $title);
            $this->historyService->updateGeneratedTitle($uid, $title);
        } catch (\Throwable) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.failed'),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'title' => $title,
        ]);
    }

    public function updateDescriptionAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->canEditHistoryMetadata()) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $uid = (int)($body['uid'] ?? 0);
        $description = trim(mb_substr(strip_tags((string)($body['description'] ?? '')), 0, self::MAX_DESCRIPTION_LENGTH));

        if ($uid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 400);
        }

        $entry = $this->historyService->findByUid($uid);
        if ($entry === null || !$entry->isSuccessful() || $entry->fileUid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.invalid'),
            ], 404);
        }
        if (!$this->fileAccessService->canGenerateForFileUid($this->getBackendUser(), $entry->fileUid)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        try {
            $this->metadataWriterService->updateSingleField($entry->fileUid, $entry->metadataUid, 'description', $description);
            $this->historyService->updateGeneratedDescription($uid, $description);
        } catch (\Throwable) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'history.edit.failed'),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'description' => $description,
        ]);
    }

    private function canEditHistoryMetadata(): bool
    {
        $backendUser = $this->getBackendUser();

        return $this->permissionsService->canUseBulkGeneration($backendUser);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
