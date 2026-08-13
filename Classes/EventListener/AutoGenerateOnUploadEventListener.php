<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\EventListener;

use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\AltTextTranslationService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use AutoAltAi\AltTextGenerator\Service\FileRenameService;
use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use AutoAltAi\AltTextGenerator\Service\HistoryService;
use AutoAltAi\AltTextGenerator\Service\MetadataWriterService;
use AutoAltAi\AltTextGenerator\Service\SiteLanguageResolver;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;

final readonly class AutoGenerateOnUploadEventListener
{
    public function __construct(
        private ConfigurationService $configurationService,
        private AutoAltApiService $autoAltApiService,
        private MetaDataRepository $metaDataRepository,
        private HistoryService $historyService,
        private FileGenerateRequestFactory $generateRequestFactory,
        private FileRenameService $fileRenameService,
        private MetadataWriterService $metadataWriterService,
        private SiteLanguageResolver $siteLanguageResolver,
        private AltTextTranslationService $altTextTranslationService,
        private ErrorLogService $errorLogService,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener(identifier: 'alt-text-generator/auto-generate-on-upload')]
    public function __invoke(AfterFileAddedEvent $event): void
    {
        $file = $event->getFile();
        if (!$file instanceof File || !$this->generateRequestFactory->isGenerableImage($file)) {
            return;
        }

        $configuration = $this->getExtensionConfiguration();
        $generateMetadata = $this->isEnabled($configuration['autoGenerateOnUpload'] ?? true);
        $renameFile = $this->isEnabled($configuration['autoRenameOnUpload'] ?? false);
        if (
            !$this->isEnabled($configuration['enabled'] ?? true)
            || (!$generateMetadata && !$renameFile)
            || trim((string)($configuration['apiKey'] ?? '')) === ''
        ) {
            return;
        }

        if (!$this->generateRequestFactory->isAllowedExtension($file->getExtension(), (string)($configuration['allowedImageExtensions'] ?? ''))) {
            return;
        }

        $metadata = [];
        $language = FileGenerateRequestFactory::FALLBACK_LANGUAGE;
        $websiteDomain = '';

        try {
            $websiteDomain = $this->generateRequestFactory->resolveWebsiteDomain();
            $metadata = $this->metaDataRepository->findByFileUid($file->getUid());
            $existingAltText = trim((string)($metadata['alternative'] ?? ''));
            if ($generateMetadata && $existingAltText !== '' && !$this->isEnabled($configuration['overwriteExistingAltText'] ?? false)) {
                $generateMetadata = false;
            }
            if (!$generateMetadata && !$renameFile) {
                return;
            }

            $languageSelection = $this->siteLanguageResolver->resolveForFile($file->getUid());
            $language = $languageSelection?->source->historyLanguage() ?? $language;

            $result = $this->autoAltApiService->generateAltText(
                $this->generateRequestFactory->buildFromFile(
                    $file,
                    $configuration,
                    $websiteDomain,
                    languageOverride: $languageSelection?->source->languageCode,
                    generateTitleOverride: $generateMetadata && $this->isEnabled($configuration['generateTitle'] ?? true),
                    generateDescriptionOverride: $generateMetadata && $this->isEnabled($configuration['generateDescription'] ?? true),
                    generateAltTextOverride: $generateMetadata,
                    renameFileOverride: $renameFile,
                )
            );
            if ($renameFile) {
                $this->renameUploadedFile($configuration, $file, trim($result->filename), $result->assetId !== null ? (string)$result->assetId : '');
            }
            if ($generateMetadata) {
                $altText = trim($result->altText);
                if ($altText === '') {
                    throw new \RuntimeException('AutoAlt.ai returned an empty alt text response.');
                }
                $title = $this->isEnabled($configuration['generateTitle'] ?? true) ? trim($result->title) : '';
                $description = $this->isEnabled($configuration['generateDescription'] ?? true) ? trim($result->description) : '';

                $metadata = $this->metadataWriterService->updateGeneratedFields(
                    $file->getUid(),
                    (int)($metadata['uid'] ?? 0),
                    $altText,
                    $title,
                    $description
                );
                if ($languageSelection !== null) {
                    $this->altTextTranslationService->translate(
                        file: $file,
                        defaultMetadataUid: (int)($metadata['uid'] ?? 0),
                        defaultAltText: $altText,
                        defaultTitle: trim((string)($metadata['title'] ?? '')),
                        defaultDescription: trim((string)($metadata['description'] ?? '')),
                        selection: $languageSelection,
                        source: 'upload',
                        websiteDomain: $websiteDomain,
                        overwriteGeneratedTranslations: $this->isEnabled($configuration['overwriteExistingAltText'] ?? false),
                        logFailures: $this->isEnabled($configuration['logApiErrors'] ?? true),
                    );
                }
                $this->historyService->recordSuccess(
                    fileUid: $file->getUid(),
                    metadataUid: (int)($metadata['uid'] ?? 0),
                    fileIdentifier: $file->getIdentifier(),
                    fileName: $file->getName(),
                    source: 'upload',
                    language: $language,
                    generatedAltText: $altText,
                    websiteDomain: $websiteDomain,
                    generatedTitle: $title,
                    generatedDescription: $description,
                );
            }
        } catch (\Throwable $exception) {
            $this->logFailure($configuration, $file, $exception);
            if ($generateMetadata) {
                $this->recordFailureSafely($file, $metadata, $language, $websiteDomain, $exception);
            }
            return;
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function logFailure(array $configuration, File $file, \Throwable $exception): void
    {
        if (!$this->isEnabled($configuration['logApiErrors'] ?? true)) {
            return;
        }

        $this->logger->error('AutoAlt.ai automatic upload processing failed.', [
            'fileUid' => $file->getUid(),
            'fileName' => $file->getName(),
            'exception' => $exception,
        ]);
        try {
            $this->errorLogService->record('error', 'Automatic upload processing failed for "' . $file->getName() . '": ' . $exception->getMessage(), [
                'fileUid' => $file->getUid(),
            ]);
        } catch (\Throwable $loggingException) {
            $this->logger->warning('AutoAlt.ai upload failure could not be written to the extension error log.', [
                'fileUid' => $file->getUid(),
                'fileName' => $file->getName(),
                'exception' => $loggingException,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function recordFailureSafely(
        File $file,
        array $metadata,
        string $language,
        string $websiteDomain,
        \Throwable $exception,
    ): void {
        try {
            $this->historyService->recordFailure(
                fileUid: $file->getUid(),
                metadataUid: (int)($metadata['uid'] ?? 0),
                fileIdentifier: $file->getIdentifier(),
                fileName: $file->getName(),
                source: 'upload',
                language: $language,
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );
        } catch (\Throwable $historyException) {
            $this->logger->warning('AutoAlt.ai upload failure could not be written to generation history.', [
                'fileUid' => $file->getUid(),
                'fileName' => $file->getName(),
                'exception' => $historyException,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function renameUploadedFile(array $configuration, File $file, string $filename, string $apiRequestId): void
    {
        if ($filename === '') {
            $this->logRenameFailure($configuration, $file, 'AutoAlt.ai did not return a filename. The uploaded file was not renamed.');
            return;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $result = $this->fileRenameService->renameAfterUpload(
            file: $file,
            requestedBasename: $filename,
            apiRequestId: $apiRequestId,
            backendUser: $backendUser instanceof BackendUserAuthentication ? $backendUser : null,
        );
        if (!$result->success && !$result->skipped) {
            $this->logRenameFailure($configuration, $file, $result->message);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function logRenameFailure(array $configuration, File $file, string $message): void
    {
        if (!$this->isEnabled($configuration['logApiErrors'] ?? true)) {
            return;
        }

        $this->logger->warning('AutoAlt.ai automatic image rename after upload failed.', [
            'fileUid' => $file->getUid(),
            'fileName' => $file->getName(),
            'message' => $message,
        ]);
        try {
            $this->errorLogService->record('warning', 'Auto-rename on upload failed for "' . $file->getName() . '": ' . $message, [
                'fileUid' => $file->getUid(),
            ]);
        } catch (\Throwable $loggingException) {
            $this->logger->warning('AutoAlt.ai upload rename failure could not be written to the extension error log.', [
                'fileUid' => $file->getUid(),
                'fileName' => $file->getName(),
                'exception' => $loggingException,
            ]);
        }
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function getExtensionConfiguration(): array
    {
        return $this->configurationService->get();
    }
}
