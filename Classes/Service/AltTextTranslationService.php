<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\SiteLanguageSelection;
use AutoAltAi\AltTextGenerator\Dto\SiteLanguageTarget;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\File;

/**
 * Translates an already persisted language-0 alternative text. Individual
 * target failures are intentionally isolated so a good default ALT is never
 * rolled back because one translation endpoint request failed.
 */
final readonly class AltTextTranslationService
{
    public function __construct(
        private AutoAltApiService $autoAltApiService,
        private MetadataWriterService $metadataWriterService,
        private HistoryService $historyService,
        private ErrorLogService $errorLogService,
        private LoggerInterface $logger,
    ) {}

    public function translate(
        File $file,
        int $defaultMetadataUid,
        string $defaultAltText,
        string $defaultTitle,
        string $defaultDescription,
        SiteLanguageSelection $selection,
        string $source,
        string $websiteDomain,
        bool $overwriteGeneratedTranslations,
        bool $logFailures = true,
    ): void {
        if ($defaultMetadataUid <= 0 || trim($defaultAltText) === '') {
            return;
        }

        foreach ($selection->targets as $target) {
            $existing = $this->metadataWriterService->findLocalizedMetadata($file->getUid(), $defaultMetadataUid, $target->languageId);
            $existingAltText = trim((string)($existing['alternative'] ?? ''));
            if ($existingAltText !== '' && (!$overwriteGeneratedTranslations || !$this->canOverwriteExistingTranslation((int)$existing['uid'], $existingAltText))) {
                continue;
            }

            try {
                // Matches Magento's business rule: locale variants with the
                // same ISO language already have valid source text, so avoid a
                // redundant translation API request while still localizing it.
                $translated = $this->translateText($defaultAltText, $target, $selection, $websiteDomain);
                if ($translated === null) {
                    throw new \RuntimeException('AutoAlt.ai returned an empty translation.');
                }
                $translatedTitle = $this->translateText($defaultTitle, $target, $selection, $websiteDomain) ?? '';
                $translatedDescription = $this->translateText($defaultDescription, $target, $selection, $websiteDomain) ?? '';

                $metadata = $this->metadataWriterService->saveLocalizedFields(
                    fileUid: $file->getUid(),
                    defaultMetadataUid: $defaultMetadataUid,
                    languageId: $target->languageId,
                    altText: $translated,
                    title: $translatedTitle,
                    description: $translatedDescription,
                );
                $this->historyService->recordSuccess(
                    fileUid: $file->getUid(),
                    metadataUid: (int)$metadata['uid'],
                    fileIdentifier: $file->getIdentifier(),
                    fileName: $file->getName(),
                    source: $source,
                    language: $target->historyLanguage(),
                    generatedAltText: $translated,
                    websiteDomain: $websiteDomain,
                    generatedTitle: $translatedTitle,
                    generatedDescription: $translatedDescription,
                );
            } catch (\Throwable $exception) {
                $this->recordTargetFailure($file, $defaultMetadataUid, $target, $source, $websiteDomain, $exception, $logFailures);
            }
        }
    }

    private function translateText(string $text, SiteLanguageTarget $target, SiteLanguageSelection $selection, string $websiteDomain): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if ($target->languageCode === $selection->source->languageCode) {
            return $text;
        }

        return $this->autoAltApiService->translate(
            text: $text,
            targetLanguage: $target->languageCode,
            baseLanguage: $selection->source->languageCode,
            websiteDomain: $websiteDomain,
        );
    }

    private function canOverwriteExistingTranslation(int $metadataUid, string $altText): bool
    {
        return $this->isLegacyGeneratedAltText($altText)
            || $this->historyService->wasGeneratedAltText($metadataUid, $altText);
    }

    // Earlier AutoAlt releases did not record localized FAL metadata in
    // extension history. TYPO3 also copied prefixLangTitle fields as
    // "[Translate to …] AutoAlt.ai …". Both forms are generated values,
    // not editor-maintained translations, and may therefore be refreshed.
    private function isLegacyGeneratedAltText(string $altText): bool
    {
        return preg_match('/^(?:\[Translate to [^\]]+\]\s*)?AutoAlt\.ai\s*[-–:]/u', $altText) === 1;
    }

    private function recordTargetFailure(
        File $file,
        int $defaultMetadataUid,
        SiteLanguageTarget $target,
        string $source,
        string $websiteDomain,
        \Throwable $exception,
        bool $logFailure,
    ): void {
        $context = [
            'fileUid' => $file->getUid(),
            'metadataUid' => $defaultMetadataUid,
            'targetLanguageId' => $target->languageId,
            'targetLocale' => $target->locale,
            'exception' => $exception,
        ];
        if ($logFailure) {
            $this->logger->warning('AutoAlt.ai ALT translation failed.', $context);
        }
        try {
            if ($logFailure) {
                $this->errorLogService->record('warning', 'ALT translation failed for language ' . $target->historyLanguage() . ': ' . $exception->getMessage(), [
                    'fileUid' => $file->getUid(),
                    'metadataUid' => $defaultMetadataUid,
                    'targetLanguageId' => $target->languageId,
                    'targetLocale' => $target->locale,
                ]);
            }
            $this->historyService->recordFailure(
                fileUid: $file->getUid(),
                metadataUid: $defaultMetadataUid,
                fileIdentifier: $file->getIdentifier(),
                fileName: $file->getName(),
                source: $source,
                language: $target->historyLanguage(),
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );
        } catch (\Throwable $loggingException) {
            if ($logFailure) {
                $this->logger->warning('AutoAlt.ai translation failure could not be recorded.', [
                    'fileUid' => $file->getUid(),
                    'targetLanguageId' => $target->languageId,
                    'exception' => $loggingException,
                ]);
            }
        }
    }
}
