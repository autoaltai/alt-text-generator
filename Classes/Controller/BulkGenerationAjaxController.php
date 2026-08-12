<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Dto\BulkProcessResult;
use AutoAltAi\AltTextGenerator\Dto\BulkProcessResultItem;
use AutoAltAi\AltTextGenerator\Service\BulkGenerationService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use AutoAltAi\AltTextGenerator\Service\KeywordValidationService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

#[AsController]
final readonly class BulkGenerationAjaxController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private BulkGenerationService $bulkGenerationService,
        private ConfigurationService $configurationService,
        private KeywordValidationService $keywordValidationService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function previewAction(ServerRequestInterface $request): ResponseInterface
    {
        $configuration = $this->getExtensionConfiguration();
        $queryParams = $request->getQueryParams();

        $count = $this->bulkGenerationService->previewEligibleCount(
            allowedImageExtensions: trim((string)($configuration['allowedImageExtensions'] ?? '')),
            overwriteExisting: $this->isEnabled($queryParams['overwriteExisting'] ?? false),
            skipProcessed: $this->isEnabled($queryParams['skipProcessed'] ?? false),
            onlyShortAltText: $this->isEnabled($queryParams['onlyShortAltText'] ?? false),
            shortAltTextLength: max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
            backendUser: $this->getBackendUser(),
        );

        return new JsonResponse([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function processAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->permissionsService->canUseBulkGeneration($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $configuration = $this->getExtensionConfiguration();
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $additionalExcludedFileUids = array_values(array_filter(array_map('intval', (array)($body['excludeFileUids'] ?? []))));
        $afterFileUid = max(0, (int)($body['afterFileUid'] ?? 0));
        $seoKeywords = trim((string)($body['seoKeywords'] ?? ''));
        $negativeKeywords = trim((string)($body['negativeKeywords'] ?? ''));
        $keywordValidationError = $this->keywordValidationService->validate($seoKeywords, $negativeKeywords);
        if ($keywordValidationError !== null) {
            return new JsonResponse([
                'success' => false,
                'message' => vsprintf(
                    $languageService->sL(self::LLL . $keywordValidationError['key']),
                    $keywordValidationError['arguments']
                ),
            ], 422);
        }

        try {
            $result = $this->bulkGenerationService->processBatch(
                configuration: $configuration,
                websiteDomain: $this->resolveWebsiteDomain($request),
                allowedImageExtensions: trim((string)($configuration['allowedImageExtensions'] ?? '')),
                language: FileGenerateRequestFactory::FALLBACK_LANGUAGE,
                overwriteExisting: $this->isEnabled($body['overwriteExisting'] ?? false),
                skipProcessed: $this->isEnabled($body['skipProcessed'] ?? false),
                onlyShortAltText: $this->isEnabled($body['onlyShortAltText'] ?? false),
                shortAltTextLength: max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
                seoKeywords: $seoKeywords,
                negativeKeywords: $negativeKeywords,
                altTextAction: $this->fieldAction($body['altTextAction'] ?? 'generate'),
                titleAction: $this->fieldAction($body['titleAction'] ?? 'keep'),
                descriptionAction: $this->fieldAction($body['descriptionAction'] ?? 'keep'),
                additionalExcludedFileUids: $additionalExcludedFileUids,
                afterFileUid: $afterFileUid,
                backendUser: $this->getBackendUser(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf($languageService->sL(self::LLL . 'ajax.processingFailed'), $exception->getMessage()),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'result' => [
                'processed' => $result->processed,
                'completed' => $result->completed,
                'failed' => $result->failed,
                'remaining' => $result->remaining,
                'message' => $this->localizeResultMessage($result, $languageService),
                'creditExhausted' => $result->creditExhausted,
                'nextCursor' => $result->nextCursor,
                'items' => array_map(
                    static fn(BulkProcessResultItem $item): array => [
                        'fileUid' => $item->fileUid,
                        'fileName' => $item->fileName,
                        'publicUrl' => $item->publicUrl,
                        'success' => $item->success,
                        'message' => $item->message,
                    ],
                    $result->items
                ),
            ],
        ]);
    }

    public function resolveSelectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->permissionsService->canUseBulkGeneration($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $configuration = $this->getExtensionConfiguration();
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'single.noApiKey'),
            ], 400);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $fileUids = array_values(array_filter(array_map('intval', (array)($body['fileUids'] ?? []))));
        $folderIdentifiers = array_values(array_filter(array_map('strval', (array)($body['folderIdentifiers'] ?? []))));
        $folderIdentifiers = array_slice($folderIdentifiers, 0, 20);

        if ($fileUids === [] && $folderIdentifiers === []) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'selection.noFiles'),
            ], 400);
        }

        $resolved = $this->bulkGenerationService->resolveSelectionFileUids($fileUids, $folderIdentifiers, $this->getBackendUser());

        return new JsonResponse([
            'success' => true,
            'fileUids' => $resolved['fileUids'],
            'total' => count($resolved['fileUids']),
            'truncated' => $resolved['truncated'],
            'batchSize' => BulkGenerationService::BATCH_SIZE,
        ]);
    }

    public function processSelectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->permissionsService->canUseBulkGeneration($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $configuration = $this->getExtensionConfiguration();
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'single.noApiKey'),
            ], 400);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $fileUids = array_values(array_filter(array_map('intval', (array)($body['fileUids'] ?? []))));
        $fileUids = array_slice($fileUids, 0, 100);
        $folderIdentifiers = array_values(array_filter(array_map('strval', (array)($body['folderIdentifiers'] ?? []))));
        $folderIdentifiers = array_slice($folderIdentifiers, 0, 20);

        if ($fileUids === [] && $folderIdentifiers === []) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'selection.noFiles'),
            ], 400);
        }

        try {
            $result = $this->bulkGenerationService->processSelection(
                fileUids: $fileUids,
                folderIdentifiers: $folderIdentifiers,
                configuration: $configuration,
                websiteDomain: $this->resolveWebsiteDomain($request),
                language: FileGenerateRequestFactory::FALLBACK_LANGUAGE,
                backendUser: $this->getBackendUser(),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf($languageService->sL(self::LLL . 'ajax.processingFailed'), $exception->getMessage()),
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'result' => [
                'processed' => $result->processed,
                'completed' => $result->completed,
                'failed' => $result->failed,
                'message' => $this->localizeResultMessage($result, $languageService),
                'creditExhausted' => $result->creditExhausted,
                'items' => array_map(
                    static fn(BulkProcessResultItem $item): array => [
                        'fileName' => $item->fileName,
                        'publicUrl' => $item->publicUrl,
                        'success' => $item->success,
                        'message' => $item->message,
                    ],
                    $result->items
                ),
            ],
        ]);
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    private function fieldAction(mixed $value): string
    {
        $action = strtolower(trim((string)$value));

        return in_array($action, ['generate', 'keep', 'clear'], true) ? $action : 'keep';
    }

    private function localizeResultMessage(BulkProcessResult $result, LanguageService $languageService): string
    {
        if ($result->messageKey === '') {
            return $result->message;
        }

        $message = $languageService->sL(self::LLL . $result->messageKey);
        if ($message === '') {
            return $result->message;
        }

        return $result->messageArguments !== [] ? sprintf($message, ...$result->messageArguments) : $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function getExtensionConfiguration(): array
    {
        return $this->configurationService->get();
    }

    private function resolveWebsiteDomain(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        if ($host === '') {
            $host = (string)($request->getServerParams()['HTTP_HOST'] ?? '');
        }

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
