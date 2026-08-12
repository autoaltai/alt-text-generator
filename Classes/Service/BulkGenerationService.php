<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\BulkProcessResult;
use AutoAltAi\AltTextGenerator\Dto\BulkProcessResultItem;
use AutoAltAi\AltTextGenerator\Dto\ImageScanItem;
use AutoAltAi\AltTextGenerator\Exception\MissingImageException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Drives bulk alt-text generation directly against the media library, one
 * batch per call, with no persisted job/queue state. Each call re-queries
 * eligible images fresh (via ImageScannerService), so the caller (the Bulk
 * Generate page's JS) is responsible for repeating calls until "remaining"
 * reaches zero and for tracking its own running totals across calls.
 */
final readonly class BulkGenerationService
{
    public const BATCH_SIZE = 1;

    private const MAX_SELECTION_FILES = 100;
    private const MAX_RESOLUTION_FILES = 5000;

    public function __construct(
        private ImageScannerService $imageScannerService,
        private AutoAltApiService $autoAltApiService,
        private FileGenerateRequestFactory $generateRequestFactory,
        private MetadataWriterService $metadataWriterService,
        private SiteLanguageResolver $siteLanguageResolver,
        private AltTextTranslationService $altTextTranslationService,
        private FileAccessService $fileAccessService,
        private ResourceFactory $resourceFactory,
        private HistoryService $historyService,
        private BulkCompletionNotifierService $bulkCompletionNotifierService,
        private ErrorLogService $errorLogService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     * @param array<int, int> $additionalExcludedFileUids Files already handled earlier in the
     *        current run (see class docblock: MODE_ALL has no "already has alt text" signal to
     *        naturally shrink the eligible set batch over batch, so without this, and with
     *        "skip already processed" unchecked, every batch would re-select the same top-N
     *        images forever - the caller must track and resend this as the run progresses).
     */
    public function processBatch(
        array $configuration,
        string $websiteDomain,
        string $allowedImageExtensions,
        string $language,
        bool $overwriteExisting = false,
        bool $skipProcessed = false,
        bool $onlyShortAltText = false,
        int $shortAltTextLength = 40,
        string $seoKeywords = '',
        string $negativeKeywords = '',
        string $altTextAction = 'generate',
        string $titleAction = 'keep',
        string $descriptionAction = 'keep',
        array $additionalExcludedFileUids = [],
        int $afterFileUid = 0,
        ?BackendUserAuthentication $backendUser = null,
    ): BulkProcessResult {
        $fieldActions = $this->fieldActions($altTextAction, $titleAction, $descriptionAction);
        if (trim((string)($configuration['apiKey'] ?? '')) === '' && in_array('generate', $fieldActions, true)) {
            return new BulkProcessResult(
                processed: 0,
                completed: 0,
                failed: 0,
                remaining: 0,
                percentage: 0,
                message: 'Configure an AutoAlt.ai API key before generating alt text.',
                messageKey: 'bulk.message.noApiKey',
            );
        }

        $mode = $this->resolveMode($overwriteExisting, $onlyShortAltText);
        $excludedFileUids = array_values(array_unique($additionalExcludedFileUids));
        $limit = self::BATCH_SIZE;

        $candidateItems = $this->imageScannerService->findEligibleImages(
            allowedImageExtensions: $allowedImageExtensions,
            mode: $mode,
            limit: $this->resolveCandidateLimit($limit, $backendUser),
            excludedFileUids: $excludedFileUids,
            shortAltTextLength: $shortAltTextLength,
            excludeSuccessfullyProcessed: $skipProcessed,
            afterFileUid: $afterFileUid,
        );
        $items = array_slice($this->filterAccessibleItems($candidateItems, $backendUser), 0, max(1, min(100, $limit)));

        $result = $this->processItems(
            $items,
            $configuration,
            $websiteDomain,
            $language,
            $seoKeywords,
            $negativeKeywords,
            'bulk',
            $fieldActions,
            $overwriteExisting || $onlyShortAltText,
            $backendUser
        );
        $completed = $result['completed'];
        $failed = $result['failed'];
        $creditExhausted = $result['creditExhausted'];
        $processedFileUids = array_map(static fn(ImageScanItem $item): int => $item->fileUid, $candidateItems);
        $nextCursor = $processedFileUids !== [] ? min($processedFileUids) : max(0, $afterFileUid);

        $remaining = $creditExhausted ? 0 : $this->countRemainingEligible(
            $allowedImageExtensions,
            $mode,
            $excludedFileUids,
            $shortAltTextLength,
            $skipProcessed,
            $nextCursor,
            $backendUser
        );

        if (count($candidateItems) > 0 && $remaining === 0 && !$creditExhausted) {
            $this->bulkCompletionNotifierService->notifyIfEnabled($configuration, [
                'total' => $completed + $failed,
                'completed' => $completed,
                'failed' => $failed,
            ]);
        }

        return new BulkProcessResult(
            processed: count($candidateItems),
            completed: $completed,
            failed: $failed,
            remaining: $remaining,
            percentage: 0,
            message: $creditExhausted
                ? 'You have run out of AutoAlt.ai credits. Add more credits to your account to continue.'
                : (count($candidateItems) > 0 ? '' : 'No eligible images were available to process.'),
            items: $result['items'],
            creditExhausted: $creditExhausted,
            messageKey: $creditExhausted
                ? 'bulk.message.creditExhausted'
                : (count($candidateItems) > 0 ? '' : 'bulk.message.noneEligible'),
            nextCursor: $nextCursor,
        );
    }

    /**
     * Generates alt text (and title/description, per settings) for an explicit
     * set of file uids - e.g. a user's multi-selection in the File List - rather
     * than files discovered via scan-mode criteria. Existing alt text is always
     * overwritten, since these files were deliberately hand-picked by the user.
     *
     * @param array<int, int> $fileUids
     * @param array<string, mixed> $configuration
     */
    public function processFileUids(
        array $fileUids,
        array $configuration,
        string $websiteDomain,
        string $language,
        string $seoKeywords = '',
        string $negativeKeywords = '',
        ?BackendUserAuthentication $backendUser = null,
    ): BulkProcessResult {
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            return new BulkProcessResult(
                processed: 0,
                completed: 0,
                failed: 0,
                remaining: 0,
                percentage: 0,
                message: 'Configure an AutoAlt.ai API key before generating alt text.',
                messageKey: 'bulk.message.noApiKey',
            );
        }

        $items = $this->imageScannerService->findByFileUids($fileUids, $backendUser);
        $result = $this->processItems(
            $items,
            $configuration,
            $websiteDomain,
            $language,
            $seoKeywords,
            $negativeKeywords,
            'selection',
            $this->fieldActions(
                'generate',
                $this->isEnabled($configuration['generateTitle'] ?? true) ? 'generate' : 'keep',
                $this->isEnabled($configuration['generateDescription'] ?? true) ? 'generate' : 'keep'
            ),
            true,
            $backendUser
        );
        $creditExhausted = $result['creditExhausted'];

        return new BulkProcessResult(
            processed: count($items),
            completed: $result['completed'],
            failed: $result['failed'],
            remaining: 0,
            percentage: 0,
            message: $creditExhausted
                ? 'You have run out of AutoAlt.ai credits. Add more credits to your account to continue.'
                : (count($items) > 0 ? '' : 'No matching images were found for the selected files.'),
            items: $result['items'],
            creditExhausted: $creditExhausted,
            messageKey: $creditExhausted
                ? 'bulk.message.creditExhausted'
                : (count($items) > 0 ? '' : 'bulk.message.noneMatchingSelection'),
        );
    }

    /**
     * Resolves an explicit + folder-based File List selection into a flat,
     * deduped list of generable image file uids, without generating anything.
     * Large selections (e.g. a folder with thousands of images) must never be
     * generated in one synchronous HTTP request - the caller is expected to
     * chunk this list and drive generation via repeated processFileUids()
     * calls, the same way the Bulk Generate page paces its own batches.
     *
     * @param array<int, int> $fileUids
     * @param array<int, string> $folderIdentifiers
     * @return array{fileUids: array<int, int>, truncated: bool}
     */
    public function resolveSelectionFileUids(
        array $fileUids,
        array $folderIdentifiers,
        ?BackendUserAuthentication $backendUser = null,
    ): array {
        $folderFileUids = $folderIdentifiers !== [] ? $this->imageScannerService->findImageUidsInFolders($folderIdentifiers, $backendUser) : [];
        $combinedUids = array_values(array_unique(array_merge($fileUids, $folderFileUids)));
        if ($backendUser !== null && !$backendUser->isAdmin()) {
            $combinedUids = array_values(array_filter(
                $combinedUids,
                fn(int $fileUid): bool => $this->fileAccessService->canGenerateForFileUid($backendUser, $fileUid)
            ));
        }
        $truncated = count($combinedUids) > self::MAX_RESOLUTION_FILES;

        return [
            'fileUids' => array_slice($combinedUids, 0, self::MAX_RESOLUTION_FILES),
            'truncated' => $truncated,
        ];
    }

    /**
     * Same as processFileUids(), but also expands any selected folders into
     * the image files they (recursively) contain - for a File List selection
     * that mixes individually-picked files with whole folders. This still
     * processes everything in one request, capped at MAX_SELECTION_FILES; it
     * exists as a defensive fallback for direct/legacy callers of this
     * endpoint. The File List button itself no longer uses it - it resolves
     * via resolveSelectionFileUids() and drives chunked calls to
     * processFileUids() instead, to stay safe for large folders.
     *
     * @param array<int, int> $fileUids
     * @param array<int, string> $folderIdentifiers
     * @param array<string, mixed> $configuration
     */
    public function processSelection(
        array $fileUids,
        array $folderIdentifiers,
        array $configuration,
        string $websiteDomain,
        string $language,
        string $seoKeywords = '',
        string $negativeKeywords = '',
        ?BackendUserAuthentication $backendUser = null,
    ): BulkProcessResult {
        $folderFileUids = $folderIdentifiers !== [] ? $this->imageScannerService->findImageUidsInFolders($folderIdentifiers, $backendUser) : [];
        $combinedUids = array_values(array_unique(array_merge($fileUids, $folderFileUids)));
        if ($backendUser !== null && !$backendUser->isAdmin()) {
            $combinedUids = array_values(array_filter(
                $combinedUids,
                fn(int $fileUid): bool => $this->fileAccessService->canGenerateForFileUid($backendUser, $fileUid)
            ));
        }
        $truncated = count($combinedUids) > self::MAX_SELECTION_FILES;
        $combinedUids = array_slice($combinedUids, 0, self::MAX_SELECTION_FILES);

        $result = $this->processFileUids(
            $combinedUids,
            $configuration,
            $websiteDomain,
            $language,
            $seoKeywords,
            $negativeKeywords,
            $backendUser
        );

        if ($truncated && !$result->creditExhausted) {
            return new BulkProcessResult(
                processed: $result->processed,
                completed: $result->completed,
                failed: $result->failed,
                remaining: $result->remaining,
                percentage: $result->percentage,
                message: 'Only the first ' . self::MAX_SELECTION_FILES . ' matching images were processed. Run the action again for the rest.',
                items: $result->items,
                creditExhausted: $result->creditExhausted,
                messageKey: 'bulk.message.selectionTruncated',
                messageArguments: [self::MAX_SELECTION_FILES],
            );
        }

        return $result;
    }

    /**
     * @param array<int, ImageScanItem> $items
     * @param array<string, mixed> $configuration
     * @return array{completed: int, failed: int, items: array<int, BulkProcessResultItem>, creditExhausted: bool}
     */
    private function processItems(
        array $items,
        array $configuration,
        string $websiteDomain,
        string $language,
        string $seoKeywords,
        string $negativeKeywords,
        string $source,
        array $fieldActions,
        bool $overwriteGeneratedTranslations,
        ?BackendUserAuthentication $backendUser = null,
    ): array {
        $completed = 0;
        $failed = 0;
        $resultItems = [];
        $creditExhausted = false;
        // Keep and Clear only change (or preserve) existing metadata. They are
        // not AI generations, so they must not appear in generation history.
        $recordsGenerationHistory = in_array('generate', $fieldActions, true);

        foreach ($items as $item) {
            try {
                $file = $this->resolveFile($item->fileUid);
                if ($backendUser !== null && !$this->fileAccessService->canGenerateForFile($backendUser, $file)) {
                    throw new \RuntimeException('You do not have permission to generate alt text for this image.');
                }
                $generatedValues = [];
                $languageSelection = $this->siteLanguageResolver->resolveForFile($item->fileUid);
                $requestLanguage = $languageSelection?->source->languageCode ?? $language;
                if (in_array('generate', $fieldActions, true)) {
                    $this->assertReadable($file, $configuration);
                    $request = $this->generateRequestFactory->buildFromFile(
                        $file,
                        $configuration,
                        $websiteDomain,
                        $seoKeywords,
                        $negativeKeywords,
                        $requestLanguage,
                        $fieldActions['title'] === 'generate',
                        $fieldActions['description'] === 'generate',
                        $fieldActions['alternative'] === 'generate',
                    );
                    $result = $this->autoAltApiService->generateAltText($request);
                    $altText = trim($result->altText);
                    if ($fieldActions['alternative'] === 'generate' && $altText === '') {
                        throw new \RuntimeException('AutoAlt.ai returned an empty alt text response.');
                    }
                    if ($fieldActions['alternative'] === 'generate') {
                        $generatedValues['alternative'] = $altText;
                    }
                    $title = trim($result->title);
                    if ($fieldActions['title'] === 'generate' && $title !== '') {
                        $generatedValues['title'] = $title;
                    }
                    $description = trim($result->description);
                    if ($fieldActions['description'] === 'generate' && $description !== '') {
                        $generatedValues['description'] = $description;
                    }
                }

                $metadata = $this->metadataWriterService->applyFieldActions(
                    $item->fileUid,
                    $item->metadataUid,
                    $generatedValues,
                    $fieldActions
                );
                if ($fieldActions['alternative'] === 'generate' && isset($generatedValues['alternative']) && $languageSelection !== null) {
                    $this->altTextTranslationService->translate(
                        file: $file,
                        defaultMetadataUid: (int)($metadata['uid'] ?? 0),
                        defaultAltText: $generatedValues['alternative'],
                        defaultTitle: trim((string)($metadata['title'] ?? '')),
                        defaultDescription: trim((string)($metadata['description'] ?? '')),
                        selection: $languageSelection,
                        source: $source,
                        websiteDomain: $websiteDomain,
                        overwriteGeneratedTranslations: $overwriteGeneratedTranslations,
                        logFailures: $this->isLoggingEnabled($configuration),
                    );
                }
                if ($recordsGenerationHistory) {
                    $this->historyService->recordSuccess(
                        fileUid: $item->fileUid,
                        metadataUid: (int)($metadata['uid'] ?? $item->metadataUid),
                        fileIdentifier: $item->identifier,
                        fileName: $item->name,
                        source: $source,
                        language: $languageSelection?->source->historyLanguage() ?? $language,
                        generatedAltText: $generatedValues['alternative'] ?? '',
                        websiteDomain: $websiteDomain,
                        generatedTitle: $generatedValues['title'] ?? '',
                        generatedDescription: $generatedValues['description'] ?? '',
                    );
                }
                $resultItems[] = new BulkProcessResultItem(
                    fileUid: $item->fileUid,
                    fileName: $item->name,
                    publicUrl: $item->publicUrl,
                    success: true,
                    message: $generatedValues['alternative'] ?? 'Metadata updated.',
                );
                ++$completed;
            } catch (MissingImageException $exception) {
                continue;
            } catch (\Throwable $exception) {
                if ($recordsGenerationHistory) {
                    $this->recordFailure($configuration, $item, $language, $websiteDomain, $exception, 'Bulk generation failed', $source);
                }
                $resultItems[] = new BulkProcessResultItem(
                    fileUid: $item->fileUid,
                    fileName: $item->name,
                    publicUrl: $item->publicUrl,
                    success: false,
                    message: $exception->getMessage(),
                );
                ++$failed;

                // The account has no credits left - every remaining item in this
                // batch (and any future batch) would fail identically, so stop
                // immediately instead of burning through the rest one by one.
                if ($this->isCreditExhaustedError($exception)) {
                    $creditExhausted = true;
                    break;
                }
            }
        }

        return ['completed' => $completed, 'failed' => $failed, 'items' => $resultItems, 'creditExhausted' => $creditExhausted];
    }

    private function isCreditExhaustedError(\Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'out of credit');
    }

    /** @return array{alternative: string, title: string, description: string} */
    private function fieldActions(string $altTextAction, string $titleAction, string $descriptionAction): array
    {
        return [
            'alternative' => $this->normalizeFieldAction($altTextAction),
            'title' => $this->normalizeFieldAction($titleAction),
            'description' => $this->normalizeFieldAction($descriptionAction),
        ];
    }

    private function normalizeFieldAction(string $action): string
    {
        $action = strtolower(trim($action));

        return in_array($action, ['generate', 'keep', 'clear'], true) ? $action : 'keep';
    }

    public function previewEligibleCount(
        string $allowedImageExtensions,
        bool $overwriteExisting = false,
        bool $skipProcessed = false,
        bool $onlyShortAltText = false,
        int $shortAltTextLength = 40,
        ?BackendUserAuthentication $backendUser = null,
    ): int {
        $mode = $this->resolveMode($overwriteExisting, $onlyShortAltText);
        if ($backendUser !== null && !$backendUser->isAdmin()) {
            return $this->countAccessibleEligible(
                $allowedImageExtensions,
                $mode,
                [],
                $shortAltTextLength,
                $skipProcessed,
                backendUser: $backendUser
            );
        }

        return $this->imageScannerService->countEligible(
            allowedImageExtensions: $allowedImageExtensions,
            mode: $mode,
            shortAltTextLength: $shortAltTextLength,
            excludeSuccessfullyProcessed: $skipProcessed,
        );
    }

    /**
     * @param array<int, int> $excludedFileUids
     */
    private function countRemainingEligible(
        string $allowedImageExtensions,
        string $mode,
        array $excludedFileUids,
        int $shortAltTextLength,
        bool $skipProcessed,
        int $afterFileUid,
        ?BackendUserAuthentication $backendUser,
    ): int {
        if ($backendUser !== null && !$backendUser->isAdmin()) {
            return $this->countAccessibleEligible(
                $allowedImageExtensions,
                $mode,
                $excludedFileUids,
                $shortAltTextLength,
                $skipProcessed,
                $afterFileUid,
                $backendUser
            );
        }

        return $this->imageScannerService->countEligible(
            allowedImageExtensions: $allowedImageExtensions,
            mode: $mode,
            excludedFileUids: $excludedFileUids,
            shortAltTextLength: $shortAltTextLength,
            excludeSuccessfullyProcessed: $skipProcessed,
            afterFileUid: $afterFileUid,
        );
    }

    /**
     * @param array<int, int> $excludedFileUids
     */
    private function countAccessibleEligible(
        string $allowedImageExtensions,
        string $mode,
        array $excludedFileUids,
        int $shortAltTextLength,
        bool $skipProcessed,
        int $afterFileUid = 0,
        ?BackendUserAuthentication $backendUser = null,
    ): int {
        $count = 0;
        $cursor = max(0, $afterFileUid);

        do {
            $items = $this->imageScannerService->findEligibleImages(
                allowedImageExtensions: $allowedImageExtensions,
                mode: $mode,
                limit: 500,
                excludedFileUids: $excludedFileUids,
                shortAltTextLength: $shortAltTextLength,
                excludeSuccessfullyProcessed: $skipProcessed,
                afterFileUid: $cursor,
            );
            if ($items === []) {
                break;
            }

            $count += count($this->filterAccessibleItems($items, $backendUser));
            $cursor = min(array_map(static fn(ImageScanItem $item): int => $item->fileUid, $items));
        } while (count($items) === 500);

        return $count;
    }

    /**
     * @param array<int, ImageScanItem> $items
     * @return array<int, ImageScanItem>
     */
    private function filterAccessibleItems(array $items, ?BackendUserAuthentication $backendUser): array
    {
        if ($backendUser === null || $backendUser->isAdmin()) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn(ImageScanItem $item): bool => $this->fileAccessService->canGenerateForFileUid($backendUser, $item->fileUid)
        ));
    }

    private function resolveCandidateLimit(int $limit, ?BackendUserAuthentication $backendUser): int
    {
        $limit = max(1, min(100, $limit));
        if ($backendUser === null || $backendUser->isAdmin()) {
            return $limit;
        }

        return min(500, max($limit * 5, 50));
    }

    public function countSuccessfullyProcessed(): int
    {
        return $this->historyService->countSuccessfullyProcessedFileUids();
    }

    private function resolveMode(bool $overwriteExisting, bool $onlyShortAltText): string
    {
        return match (true) {
            $onlyShortAltText => ImageScannerService::MODE_SHORT,
            $overwriteExisting => ImageScannerService::MODE_ALL,
            default => ImageScannerService::MODE_MISSING,
        };
    }

    private function resolveFile(int $fileUid): File
    {
        try {
            return $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException $exception) {
            throw new MissingImageException('Image #' . $fileUid . ' no longer exists.', 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function assertReadable(File $file, array $configuration): void
    {
        if ($this->isEnabled($configuration['usePublicImageUrls'] ?? false)) {
            return;
        }

        if ($file->getContents() === '') {
            throw new MissingImageException('File contents could not be read for image #' . $file->getUid() . '.');
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function recordFailure(
        array $configuration,
        ImageScanItem $item,
        string $language,
        string $websiteDomain,
        \Throwable $exception,
        string $context,
        string $source = 'bulk',
    ): void {
        try {
            $this->historyService->recordFailure(
                fileUid: $item->fileUid,
                metadataUid: $item->metadataUid,
                fileIdentifier: $item->identifier,
                fileName: $item->name,
                source: $source,
                language: $language,
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );
        } catch (\Throwable $historyException) {
            // A diagnostics-table failure must not turn an individual API
            // failure into a failed bulk request or stop the remaining files.
            $this->logger->warning('AutoAlt.ai bulk failure could not be written to generation history.', [
                'fileUid' => $item->fileUid,
                'fileName' => $item->name,
                'exception' => $historyException,
            ]);
        }

        if ($this->isLoggingEnabled($configuration)) {
            $this->logger->warning('AutoAlt.ai ' . $context . '.', [
                'fileUid' => $item->fileUid,
                'fileName' => $item->name,
                'exception' => $exception,
            ]);
            try {
                $this->errorLogService->record('warning', $context . ' for "' . $item->name . '": ' . $exception->getMessage(), [
                    'fileUid' => $item->fileUid,
                ]);
            } catch (\Throwable $loggingException) {
                $this->logger->warning('AutoAlt.ai bulk failure could not be written to the extension error log.', [
                    'fileUid' => $item->fileUid,
                    'fileName' => $item->name,
                    'exception' => $loggingException,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function isLoggingEnabled(array $configuration): bool
    {
        return $this->isEnabled($configuration['logApiErrors'] ?? true);
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }
}
