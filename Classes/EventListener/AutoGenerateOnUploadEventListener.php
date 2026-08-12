<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\EventListener;

use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\AltTextTranslationService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use AutoAltAi\AltTextGenerator\Service\HistoryService;
use AutoAltAi\AltTextGenerator\Service\MetadataWriterService;
use AutoAltAi\AltTextGenerator\Service\SiteLanguageResolver;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
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
        if (
            !$this->isEnabled($configuration['enabled'] ?? true)
            || !$this->isEnabled($configuration['autoGenerateOnUpload'] ?? true)
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
            if ($existingAltText !== '' && !$this->isEnabled($configuration['overwriteExistingAltText'] ?? false)) {
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
                )
            );
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
        } catch (\Throwable $exception) {
            $this->logFailure($configuration, $file, $exception);
            $this->recordFailureSafely($file, $metadata, $language, $websiteDomain, $exception);
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

        $this->logger->error('AutoAlt.ai automatic alt text generation failed for an uploaded file.', [
            'fileUid' => $file->getUid(),
            'fileName' => $file->getName(),
            'exception' => $exception,
        ]);
        try {
            $this->errorLogService->record('error', 'Auto-generate on upload failed for "' . $file->getName() . '": ' . $exception->getMessage(), [
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
