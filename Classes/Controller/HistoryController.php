<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Dto\HistoryEntry;
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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[AsController]
final readonly class HistoryController
{
    private const PER_PAGE = 25;
    private const VALID_STATUSES = ['success', 'failed'];
    private const VALID_SOURCES = ['bulk', 'upload', 'manual', 'selection'];
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private UriBuilder $uriBuilder,
        private HistoryService $historyService,
        private ResourceFactory $resourceFactory,
        private ConfigurationService $configurationService,
        private AutoAltApiService $autoAltApiService,
        private FileAccessService $fileAccessService,
        private FileGenerateRequestFactory $generateRequestFactory,
        private MetadataWriterService $metadataWriterService,
        private SiteLanguageResolver $siteLanguageResolver,
        private AltTextTranslationService $altTextTranslationService,
        private ErrorLogService $errorLogService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');

        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $queryParams = $request->getQueryParams();
        $statusFilter = $this->sanitizeFilter((string)($queryParams['status'] ?? ''), self::VALID_STATUSES);
        $sourceFilter = $this->sanitizeFilter((string)($queryParams['source'] ?? ''), self::VALID_SOURCES);
        $search = trim(mb_substr(strip_tags((string)($queryParams['search'] ?? '')), 0, 255));
        $availableLanguages = $this->historyService->getDistinctLanguages();
        $languageFilter = $this->sanitizeFilter((string)($queryParams['language'] ?? ''), $availableLanguages);
        $page = max(1, (int)($queryParams['page'] ?? 1));

        $result = $this->historyService->findEntries($page, self::PER_PAGE, $statusFilter, $sourceFilter, $search, $languageFilter);
        [$historyPath, $historyToken] = $this->splitUrlAndToken(
            (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history')
        );

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'module.title'), $languageService->sL(self::LLL . 'history.title'));
        $moduleTemplate->assignMultiple([
            'backUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator'),
            'bulkUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_bulk'),
            'renameUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename'),
            'settingsUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
            'retryUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history.retry'),
            'updateAltTextAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_history_update'),
            'updateTitleAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_history_update_title'),
            'updateDescriptionAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_history_update_description'),
            'historyPath' => $historyPath,
            'historyToken' => $historyToken,
            'entries' => array_map(
                fn(HistoryEntry $entry): array => $this->buildEntryViewData(
                    $entry,
                    (string)$request->getUri(),
                    $languageService
                ),
                $result->items
            ),
            'total' => $result->total,
            'successCount' => $result->successCount,
            'failedCount' => $result->failedCount,
            'page' => $result->page,
            'totalPages' => $result->totalPages,
            'hasPrevious' => $result->page > 1,
            'hasNext' => $result->page < $result->totalPages,
            'previousUrl' => $this->buildPageUrl($result->page - 1, $statusFilter, $sourceFilter, $search, $languageFilter),
            'nextUrl' => $this->buildPageUrl($result->page + 1, $statusFilter, $sourceFilter, $search, $languageFilter),
            'statusFilter' => $statusFilter,
            'sourceFilter' => $sourceFilter,
            'search' => $search,
            'languageFilter' => $languageFilter,
            'statusOptions' => $this->buildOptions([
                '' => $languageService->sL(self::LLL . 'history.filter.status.all'),
                'success' => $languageService->sL(self::LLL . 'history.filter.status.success'),
                'failed' => $languageService->sL(self::LLL . 'history.filter.status.failed'),
            ], $statusFilter),
            'sourceOptions' => $this->buildOptions([
                '' => $languageService->sL(self::LLL . 'history.filter.source.all'),
                'bulk' => $languageService->sL(self::LLL . 'history.filter.source.bulk'),
                'upload' => $languageService->sL(self::LLL . 'history.filter.source.upload'),
                'manual' => $languageService->sL(self::LLL . 'history.filter.source.manual'),
                'selection' => $languageService->sL(self::LLL . 'history.filter.source.selection'),
            ], $sourceFilter),
            'languageOptions' => $this->buildOptions(
                ['' => $languageService->sL(self::LLL . 'history.filter.language.all')]
                + $this->buildLanguageOptions($availableLanguages),
                $languageFilter
            ),
            'retryMessage' => $this->buildRetryMessage($languageService, $request),
            'canEditHistoryMetadata' => $this->canEditHistoryMetadata(),
        ]);

        return $moduleTemplate->renderResponse('History/Index');
    }

    public function retryEntry(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->canEditHistoryMetadata()) {
            return new RedirectResponse(
                (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history', [
                    'permissionDenied' => 1,
                ]),
                303
            );
        }

        $body = $request->getParsedBody();
        $uid = (int)($body['uid'] ?? 0);
        $entry = $uid > 0 ? $this->historyService->findByUid($uid) : null;
        $retried = $entry !== null && $entry->fileUid > 0 && !$entry->isLocalizedTranslation() && $this->regenerate($entry);

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history', [
                'retried' => $retried ? 1 : 0,
            ]),
            303
        );
    }

    private function regenerate(HistoryEntry $entry): bool
    {
        $configuration = $this->getExtensionConfiguration();
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            return false;
        }

        try {
            $file = $this->resourceFactory->getFileObject($entry->fileUid);
        } catch (FileDoesNotExistException) {
            return false;
        }
        if (!$this->fileAccessService->canGenerateForFile($this->getBackendUser(), $file)) {
            return false;
        }

        $languageSelection = $this->siteLanguageResolver->resolveForFile($entry->fileUid);
        $language = $languageSelection?->source->languageCode ?? $this->languageToApiCode($entry->language);
        $historyLanguage = $languageSelection?->source->historyLanguage() ?? $entry->language;
        if ($historyLanguage === '') {
            $historyLanguage = $language;
        }
        $websiteDomain = $this->generateRequestFactory->resolveWebsiteDomain();
        $metadata = [];

        try {
            $metadata = $this->metadataWriterService->resolveMetadata($entry->fileUid, $entry->metadataUid);
            $result = $this->autoAltApiService->generateAltText(
                $this->generateRequestFactory->buildFromFile($file, $configuration, $websiteDomain, languageOverride: $language)
            );
            $altText = trim($result->altText);
            if ($altText === '') {
                throw new \RuntimeException('AutoAlt.ai returned an empty alt text response.');
            }
            $title = $this->isEnabled($configuration['generateTitle'] ?? true) ? trim($result->title) : '';
            $description = $this->isEnabled($configuration['generateDescription'] ?? true) ? trim($result->description) : '';

            $metadata = $this->metadataWriterService->updateGeneratedFields(
                $entry->fileUid,
                (int)($metadata['uid'] ?? $entry->metadataUid),
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
                    source: 'manual',
                    websiteDomain: $websiteDomain,
                    overwriteGeneratedTranslations: true,
                    logFailures: $this->isEnabled($configuration['logApiErrors'] ?? true),
                );
            }

            $this->historyService->recordSuccess(
                fileUid: $entry->fileUid,
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

            return true;
        } catch (\Throwable $exception) {
            $this->logRetryFailure($configuration, $file->getUid(), $file->getName(), $exception);
            $this->recordRetryFailureSafely($entry, $metadata, $file->getIdentifier(), $file->getName(), $historyLanguage, $websiteDomain, $exception);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function logRetryFailure(array $configuration, int $fileUid, string $fileName, \Throwable $exception): void
    {
        if (!$this->isEnabled($configuration['logApiErrors'] ?? true)) {
            return;
        }

        $this->logger->error('AutoAlt.ai retry-generation failed.', [
            'fileUid' => $fileUid,
            'fileName' => $fileName,
            'exception' => $exception,
        ]);
        try {
            $this->errorLogService->record('error', 'Retry generation failed for "' . $fileName . '": ' . $exception->getMessage(), [
                'fileUid' => $fileUid,
            ]);
        } catch (\Throwable $loggingException) {
            $this->logger->warning('AutoAlt.ai retry failure could not be written to the extension error log.', [
                'fileUid' => $fileUid,
                'fileName' => $fileName,
                'exception' => $loggingException,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function recordRetryFailureSafely(
        HistoryEntry $entry,
        array $metadata,
        string $fileIdentifier,
        string $fileName,
        string $language,
        string $websiteDomain,
        \Throwable $exception,
    ): void {
        try {
            $this->historyService->recordFailure(
                fileUid: $entry->fileUid,
                metadataUid: (int)($metadata['uid'] ?? 0),
                fileIdentifier: $fileIdentifier,
                fileName: $fileName,
                source: 'manual',
                language: $language,
                errorMessage: $exception->getMessage(),
                websiteDomain: $websiteDomain,
            );
        } catch (\Throwable $historyException) {
            $this->logger->warning('AutoAlt.ai retry failure could not be written to generation history.', [
                'fileUid' => $entry->fileUid,
                'fileName' => $fileName,
                'exception' => $historyException,
            ]);
        }
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

    /**
     * @return array<string, mixed>
     */
    private function buildEntryViewData(HistoryEntry $entry, string $returnUrl, LanguageService $languageService): array
    {
        return [
            'uid' => $entry->uid,
            'fileUid' => $entry->fileUid,
            'fileName' => $entry->fileName,
            'fileIdentifier' => $entry->fileIdentifier,
            'thumbnailUrl' => $this->resolveThumbnailUrl($entry->fileUid),
            'editUrl' => $this->resolveEditUrl($entry->fileUid, $entry->metadataUid, $returnUrl),
            'source' => $entry->source,
            'sourceLabel' => $languageService->sL(self::LLL . 'history.filter.source.' . $entry->source),
            'language' => $entry->language,
            // Retry regenerates the language-0 source and then refreshes its
            // translations. A localized history row is independently editable
            // but must never expose an action that cannot be completed.
            'isLocalizedTranslation' => $entry->isLocalizedTranslation(),
            ...$this->buildLanguagePresentation($entry->language),
            'status' => $entry->status,
            'isSuccessful' => $entry->isSuccessful(),
            'generatedAltText' => $entry->generatedAltText,
            'generatedTitle' => $entry->generatedTitle,
            'generatedDescription' => $entry->generatedDescription,
            'errorMessage' => $entry->errorMessage,
            'crdate' => $entry->crdate,
        ];
    }

    private function languageToApiCode(string $historyLanguage): string
    {
        $locale = str_contains($historyLanguage, ':')
            ? (string)explode(':', $historyLanguage, 2)[1]
            : $historyLanguage;
        $languageCode = (string)preg_split('/[-_]/', $locale)[0];

        return trim($languageCode) !== '' ? trim($languageCode) : 'en';
    }

    /**
     * @param array<int, string> $historyLanguages
     * @return array<string, string>
     */
    private function buildLanguageOptions(array $historyLanguages): array
    {
        $options = [];
        foreach ($historyLanguages as $historyLanguage) {
            $presentation = $this->buildLanguagePresentation($historyLanguage);
            $options[$historyLanguage] = $presentation['languageFlag'] . ' ' . $presentation['languageLabel'];
        }

        return $options;
    }

    /**
     * Turns stored history values such as "1:de-DE" into a presentation
     * suitable for the backend. The technical sys_language_uid remains in
     * storage and filtering, but is never shown in the history row.
     *
     * @return array{languageLabel: string, languageFlag: string}
     */
    private function buildLanguagePresentation(string $historyLanguage): array
    {
        $locale = trim(str_contains($historyLanguage, ':')
            ? (string)explode(':', $historyLanguage, 2)[1]
            : $historyLanguage);
        if ($locale === '') {
            return ['languageLabel' => 'Language', 'languageFlag' => '🌐'];
        }

        $locale = str_replace('_', '-', $locale);
        $languageLabel = \Locale::getDisplayLanguage($locale, 'en');
        if (!is_string($languageLabel) || $languageLabel === '') {
            $languageLabel = $locale;
        }

        return [
            'languageLabel' => $languageLabel,
            'languageFlag' => $this->countryFlag(\Locale::getRegion($locale)),
        ];
    }

    private function countryFlag(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($countryCode[0]) - ord('A'))
            . mb_chr(0x1F1E6 + ord($countryCode[1]) - ord('A'));
    }

    private function resolveThumbnailUrl(int $fileUid): ?string
    {
        if ($fileUid <= 0) {
            return null;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            if (!$file->isImage()) {
                return null;
            }

            $publicUrl = $file->getPublicUrl();
        } catch (\Throwable) {
            return null;
        }

        return $publicUrl !== null && $publicUrl !== '' ? $publicUrl : null;
    }

    private function resolveEditUrl(int $fileUid, int $metadataUid, string $returnUrl): ?string
    {
        if ($fileUid <= 0) {
            return null;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            if (!$this->fileAccessService->canGenerateForFile($this->getBackendUser(), $file)) {
                return null;
            }

            $metadata = $this->metadataWriterService->findExistingMetadata($fileUid, $metadataUid);
            $metadataUid = (int)($metadata['uid'] ?? 0);
            if ($metadataUid <= 0) {
                return null;
            }

            return (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_file_metadata' => [$metadataUid => 'edit']],
                'returnUrl' => $returnUrl,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $options
     * @return array<int, array<string, mixed>>
     */
    private function buildOptions(array $options, string $selectedValue): array
    {
        $items = [];
        foreach ($options as $value => $label) {
            $items[] = [
                'value' => $value,
                'label' => $label,
                'selectedAttribute' => $value === $selectedValue ? ' selected="selected"' : '',
            ];
        }

        return $items;
    }

    /**
     * @param array<int, string> $allowedValues
     */
    private function sanitizeFilter(string $value, array $allowedValues): string
    {
        return in_array($value, $allowedValues, true) ? $value : '';
    }

    private function buildPageUrl(int $page, string $statusFilter, string $sourceFilter, string $search = '', string $languageFilter = ''): string
    {
        $arguments = ['page' => max(1, $page)];
        if ($statusFilter !== '') {
            $arguments['status'] = $statusFilter;
        }
        if ($sourceFilter !== '') {
            $arguments['source'] = $sourceFilter;
        }
        if ($search !== '') {
            $arguments['search'] = $search;
        }
        if ($languageFilter !== '') {
            $arguments['language'] = $languageFilter;
        }

        return (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history', $arguments);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUrlAndToken(string $url): array
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? $url);
        $query = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
        parse_str($query, $queryParams);

        return [$path, (string)($queryParams['token'] ?? '')];
    }

    /**
     * @return array<string, string>|null
     */
    private function buildRetryMessage(LanguageService $languageService, ServerRequestInterface $request): ?array
    {
        $queryParams = $request->getQueryParams();
        if ((bool)($queryParams['permissionDenied'] ?? false)) {
            return [
                'state' => 'warning',
                'message' => $languageService->sL(self::LLL . 'history.retry.noPermission'),
            ];
        }

        if (!array_key_exists('retried', $queryParams)) {
            return null;
        }

        $retried = (bool)$queryParams['retried'];

        return [
            'state' => $retried ? 'success' : 'warning',
            'message' => $retried
                ? $languageService->sL(self::LLL . 'history.retry.success')
                : $languageService->sL(self::LLL . 'history.retry.failure'),
        ];
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
