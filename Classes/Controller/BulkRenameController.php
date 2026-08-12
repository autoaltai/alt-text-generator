<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Service\AiFilenameService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\CreditSummaryService;
use AutoAltAi\AltTextGenerator\Service\FileAccessService;
use AutoAltAi\AltTextGenerator\Service\FileRenameService;
use AutoAltAi\AltTextGenerator\Service\MetadataWriterService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use AutoAltAi\AltTextGenerator\Service\RenameFileQueryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;

#[AsController]
final readonly class BulkRenameController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';
    private const ITEMS_PER_PAGE = 25;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private UriBuilder $uriBuilder,
        private ConfigurationService $configurationService,
        private CreditSummaryService $creditSummaryService,
        private RenameFileQueryService $queryService,
        private FileAccessService $fileAccessService,
        private MetadataWriterService $metadataWriterService,
        private FileRenameService $fileRenameService,
        private AiFilenameService $aiFilenameService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $configuration = $this->configurationService->get();
        $filter = $this->sanitizeFilter($request->getQueryParams());
        $allowedExtensions = trim((string)($configuration['allowedImageExtensions'] ?? ''));
        $result = $this->queryService->find($filter, $allowedExtensions, $this->getBackendUser());
        $statistics = $this->queryService->getStatistics($allowedExtensions);
        $statistics['poorPercent'] = $statistics['total'] > 0 ? min(100, (int)round(($statistics['poor'] / $statistics['total']) * 100)) : 0;
        $statistics['renamedPercent'] = $statistics['total'] > 0 ? min(100, (int)round(($statistics['renamed'] / $statistics['total']) * 100)) : 0;
        $canRename = $this->permissionsService->canUseBulkGeneration($this->getBackendUser());
        $moduleUrl = (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename');
        $returnUrl = $this->filterUrl($filter, []);

        foreach ($result['items'] as &$item) {
            if (array_key_exists('canRename', $item)) {
                $item['canRename'] = $canRename && (bool)$item['canRename'];
            }
            if (($item['file'] ?? null) !== null) {
                $item['editUrl'] = $this->resolveEditUrl($item['file'], $returnUrl);
            }
            $item['statusLabelKey'] = self::LLL . 'rename.status.' . ($item['status'] ?? 'error');
        }
        unset($item);
        $visibleEligibleCount = count(array_filter($result['items'], static fn(array $item): bool => (bool)($item['canRename'] ?? false)));

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'module.title'), $languageService->sL(self::LLL . 'module.rename.title'));
        $moduleTemplate->assignMultiple([
            'filter' => $filter,
            'result' => $result,
            'statistics' => $statistics,
            'creditSummary' => $this->creditSummaryService->build($languageService, $configuration, $this->resolveWebsiteDomain($request)),
            'canRename' => $canRename,
            'showBulkActions' => $canRename && $filter['tab'] !== 'history',
            'visibleEligibleCount' => $visibleEligibleCount,
            'tabs' => $this->buildTabs($filter, $statistics),
            'pages' => $this->buildPages($filter, $result['pages']),
            'pagination' => $this->buildPagination($filter, $result),
            'formUrl' => $moduleUrl,
            'searchNavigationUrl' => $this->filterUrl($filter, ['search' => '$[value]', 'page' => 1]),
            'manualRenameUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_rename_manual'),
            'aiRenameUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_rename_ai'),
            'undoRenameUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_rename_undo'),
            'dashboardUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator'),
            'bulkUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_bulk'),
            'historyUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history'),
            'settingsUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
        ]);

        return $moduleTemplate->renderResponse('Module/BulkRename');
    }

    public function manualAction(ServerRequestInterface $request): ResponseInterface
    {
        if (($denied = $this->denyUnlessAllowed()) !== null) {
            return $denied;
        }
        $body = $this->body($request);
        $result = $this->fileRenameService->rename((int)($body['fileUid'] ?? 0), (string)($body['filename'] ?? ''), 'manual', $this->getBackendUser());
        return $this->resultResponse($result, 'rename.message.manualSuccess');
    }

    public function aiAction(ServerRequestInterface $request): ResponseInterface
    {
        if (($denied = $this->denyUnlessAllowed()) !== null) {
            return $denied;
        }
        $body = $this->body($request);
        $result = $this->aiFilenameService->rename((int)($body['fileUid'] ?? 0), $this->resolveWebsiteDomain($request), $this->getBackendUser());
        return $this->resultResponse($result, 'rename.message.aiSuccess');
    }

    public function undoAction(ServerRequestInterface $request): ResponseInterface
    {
        if (($denied = $this->denyUnlessAllowed()) !== null) {
            return $denied;
        }
        $body = $this->body($request);
        $result = $this->fileRenameService->undo((int)($body['fileUid'] ?? 0), $this->getBackendUser());
        return $this->resultResponse($result, 'rename.message.undoSuccess');
    }

    private function resultResponse(\AutoAltAi\AltTextGenerator\Dto\FileRenameResult $result, string $successKey): JsonResponse
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        return new JsonResponse([
            'success' => $result->success,
            'skipped' => $result->skipped,
            'fileUid' => $result->fileUid,
            'oldFilename' => $result->oldFilename,
            'newFilename' => $result->newFilename,
            'message' => $result->success ? $languageService->sL(self::LLL . $successKey) : $result->message,
            'creditExhausted' => !$result->success && preg_match('/credit|balance|quota/i', $result->message) === 1,
        ]);
    }

    private function denyUnlessAllowed(): ?JsonResponse
    {
        if ($this->permissionsService->canUseBulkGeneration($this->getBackendUser())) {
            return null;
        }
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        return new JsonResponse(['success' => false, 'message' => $languageService->sL(self::LLL . 'ajax.noPermission')], 403);
    }

    /** @return array<string,mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    /** @param array<string,mixed> $query @return array{tab:string,search:string,referencedOnly:bool,skipProcessed:bool,page:int,limit:int} */
    private function sanitizeFilter(array $query): array
    {
        $tab = (string)($query['tab'] ?? 'poor');
        if (!in_array($tab, ['poor', 'all', 'history'], true)) {
            $tab = 'poor';
        }
        return [
            'tab' => $tab,
            'search' => mb_substr(trim((string)($query['search'] ?? '')), 0, 255),
            'referencedOnly' => false,
            'skipProcessed' => false,
            'page' => max(1, (int)($query['page'] ?? 1)),
            'limit' => self::ITEMS_PER_PAGE,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function buildTabs(array $filter, array $statistics): array
    {
        $counts = [
            'poor' => (int)$statistics['poor'],
            'all' => (int)$statistics['total'],
            'history' => (int)$statistics['history'],
        ];
        return array_map(fn(string $tab): array => [
            'key' => $tab,
            'active' => $filter['tab'] === $tab,
            'labelKey' => self::LLL . 'rename.tab.' . $tab,
            'count' => $counts[$tab],
            'url' => $this->filterUrl($filter, ['tab' => $tab, 'page' => 1]),
        ], ['poor', 'all', 'history']);
    }

    /** @return list<array{number:int,current:bool,url:string}> */
    private function buildPages(array $filter, int $pageCount): array
    {
        $start = max(1, $filter['page'] - 2);
        $end = min($pageCount, $start + 4);
        $start = max(1, $end - 4);
        $pages = [];
        for ($page = $start; $page <= $end; ++$page) {
            $pages[] = ['number' => $page, 'current' => $page === $filter['page'], 'url' => $this->filterUrl($filter, ['page' => $page])];
        }
        return $pages;
    }

    /** @return array{start:int,end:int,total:int,hasPrevious:bool,hasNext:bool,previousUrl:string,nextUrl:string} */
    private function buildPagination(array $filter, array $result): array
    {
        $total = (int)$result['total'];
        $page = (int)$result['page'];
        $pageCount = (int)$result['pages'];
        $limit = (int)$result['limit'];
        return [
            'start' => $total > 0 ? (($page - 1) * $limit) + 1 : 0,
            'end' => $total > 0 ? min($total, $page * $limit) : 0,
            'total' => $total,
            'hasPrevious' => $page > 1,
            'hasNext' => $page < $pageCount,
            'previousUrl' => $this->filterUrl($filter, ['page' => max(1, $page - 1)]),
            'nextUrl' => $this->filterUrl($filter, ['page' => min($pageCount, $page + 1)]),
        ];
    }

    private function filterUrl(array $filter, array $overrides): string
    {
        $parameters = array_replace($filter, $overrides);
        unset($parameters['referencedOnly'], $parameters['skipProcessed']);
        return (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename', $parameters);
    }

    private function resolveEditUrl(File $file, string $returnUrl): ?string
    {
        try {
            if (!$this->fileAccessService->canGenerateForFile($this->getBackendUser(), $file)) {
                return null;
            }
            $metadata = $this->metadataWriterService->findExistingMetadata($file->getUid());
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

    private function resolveWebsiteDomain(ServerRequestInterface $request): string
    {
        $host = $request->getUri()->getHost() ?: (string)($request->getServerParams()['HTTP_HOST'] ?? '');
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
