<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Exception\AutoAltApiException;
use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\AltTextTranslationService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use AutoAltAi\AltTextGenerator\Service\FileAccessService;
use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use AutoAltAi\AltTextGenerator\Service\HistoryService;
use AutoAltAi\AltTextGenerator\Service\MetadataWriterService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use AutoAltAi\AltTextGenerator\Service\SiteLanguageResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[AsController]
final readonly class SingleGenerateAjaxController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private ResourceFactory $resourceFactory,
        private MetaDataRepository $metaDataRepository,
        private ConfigurationService $configurationService,
        private AutoAltApiService $autoAltApiService,
        private FileAccessService $fileAccessService,
        private FileGenerateRequestFactory $generateRequestFactory,
        private MetadataWriterService $metadataWriterService,
        private SiteLanguageResolver $siteLanguageResolver,
        private AltTextTranslationService $altTextTranslationService,
        private HistoryService $historyService,
        private ErrorLogService $errorLogService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());

        if (!$this->permissionsService->canUseSingleGeneration($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'ajax.noPermission'),
            ], 403);
        }

        $body = $request->getParsedBody();
        $fileUid = (int)(is_array($body) ? ($body['fileUid'] ?? 0) : 0);
        if ($fileUid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'single.invalidFile'),
            ], 400);
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'single.invalidFile'),
            ], 404);
        }

        if (!$this->generateRequestFactory->isGenerableImage($file)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'single.notAnImage'),
            ], 400);
        }

        if (!$this->fileAccessService->canGenerateForFile($this->getBackendUser(), $file)) {
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

        $metadata = $this->metaDataRepository->findByFileUid($fileUid);
        $language = FileGenerateRequestFactory::FALLBACK_LANGUAGE;
        $websiteDomain = $this->generateRequestFactory->resolveWebsiteDomain();
        $languageSelection = $this->siteLanguageResolver->resolveForFile($fileUid);
        $requestLanguage = $languageSelection?->source->languageCode ?? $language;
        $historyLanguage = $languageSelection?->source->historyLanguage() ?? $language;

        try {
            $result = $this->autoAltApiService->generateAltText(
                $this->generateRequestFactory->buildFromFile($file, $configuration, $websiteDomain, languageOverride: $requestLanguage)
            );
            $altText = trim($result->altText);
            if ($altText === '') {
                throw new \RuntimeException('AutoAlt.ai returned an empty alt text response.');
            }
            $title = $this->isEnabled($configuration['generateTitle'] ?? true) ? trim($result->title) : '';
            $description = $this->isEnabled($configuration['generateDescription'] ?? true) ? trim($result->description) : '';
        } catch (AutoAltApiException|\RuntimeException $exception) {
            $this->logFailure($configuration, $file, $exception);
            $this->historyService->recordFailure(
                fileUid: $fileUid,
                metadataUid: (int)($metadata['uid'] ?? 0),
                fileIdentifier: $file->getIdentifier(),
                fileName: $file->getName(),
                source: 'manual',
                language: $historyLanguage,
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );

            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        // Single generation previously only filled the open FormEngine field.
        // Persisting here is necessary so localized records can be attached to
        // the canonical language-0 metadata in the same user action.
        try {
            $metadata = $this->metadataWriterService->updateGeneratedFields(
                $fileUid,
                (int)($metadata['uid'] ?? 0),
                $altText,
                $title,
                $description,
            );
            if ($languageSelection !== null) {
                $this->altTextTranslationService->translate(
                    file: $file,
                    defaultMetadataUid: (int)($metadata['uid'] ?? 0),
                    defaultAltText: $altText,
                    defaultTitle: trim((string)($metadata['title'] ?? '')),
                    defaultDescription: trim((string)($metadata['description'] ?? '')),
                    selection: $languageSelection,
                    source: 'manual',
                    websiteDomain: $websiteDomain,
                    overwriteGeneratedTranslations: true,
                    logFailures: $this->isEnabled($configuration['logApiErrors'] ?? true),
                );
            }
        } catch (\Throwable $exception) {
            $this->logFailure($configuration, $file, $exception);
            $this->historyService->recordFailure(
                fileUid: $fileUid,
                metadataUid: (int)($metadata['uid'] ?? 0),
                fileIdentifier: $file->getIdentifier(),
                fileName: $file->getName(),
                source: 'manual',
                language: $historyLanguage,
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );

            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        $this->historyService->recordSuccess(
            fileUid: $fileUid,
            metadataUid: (int)($metadata['uid'] ?? 0),
            fileIdentifier: $file->getIdentifier(),
            fileName: $file->getName(),
            source: 'manual',
            language: $historyLanguage,
            generatedAltText: $altText,
            websiteDomain: $websiteDomain,
            generatedTitle: $title,
            generatedDescription: $description,
        );

        return new JsonResponse([
            'success' => true,
            'altText' => $altText,
            'title' => $title,
            'description' => $description,
        ]);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function logFailure(array $configuration, File $file, \Throwable $exception): void
    {
        if (!$this->isEnabled($configuration['logApiErrors'] ?? true)) {
            return;
        }

        $this->logger->error('AutoAlt.ai manual alt text generation failed.', [
            'fileUid' => $file->getUid(),
            'fileName' => $file->getName(),
            'exception' => $exception,
        ]);
        $this->errorLogService->record('error', 'Manual generation failed for "' . $file->getName() . '": ' . $exception->getMessage(), [
            'fileUid' => $file->getUid(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getExtensionConfiguration(): array
    {
        return $this->configurationService->get();
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
