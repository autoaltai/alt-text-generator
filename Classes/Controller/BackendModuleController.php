<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;
use AutoAltAi\AltTextGenerator\Service\BulkGenerationService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\CreditSummaryService;
use AutoAltAi\AltTextGenerator\Service\ImageScannerService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
final readonly class BackendModuleController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private ConfigurationService $configurationService,
        private UriBuilder $uriBuilder,
        private ImageScannerService $imageScannerService,
        private BulkGenerationService $bulkGenerationService,
        private CreditSummaryService $creditSummaryService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    public function dashboardAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');

        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $configuration = $this->getExtensionConfiguration();
        $creditSummary = $this->creditSummaryService->build($languageService, $configuration, $this->resolveWebsiteDomain($request));
        $imageScanSummary = $this->buildImageScanSummary($languageService, $configuration);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'module.title'));
        $moduleTemplate->assignMultiple([
            'configuration' => $this->buildConfigurationSummary($languageService, $configuration),
            'creditSummary' => $creditSummary,
            'overviewCards' => $this->buildOverviewCards($languageService, $configuration, $creditSummary, $imageScanSummary),
            'quickLinks' => $this->buildQuickLinks($languageService),
            'bulkUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_bulk'),
            'renameUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename'),
            'historyUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history'),
            'settingsUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
        ]);

        return $moduleTemplate->renderResponse('Module/Dashboard');
    }

    public function bulkGenerateAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');

        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $configuration = $this->getExtensionConfiguration();
        $canRunBulkGeneration = $this->permissionsService->canUseBulkGeneration($this->getBackendUser());

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'module.title'), $languageService->sL(self::LLL . 'module.bulk.title'));
        $moduleTemplate->assignMultiple([
            'imageScanSummary' => $this->buildImageScanSummary($languageService, $configuration),
            'creditSummary' => $this->creditSummaryService->build($languageService, $configuration, $this->resolveWebsiteDomain($request)),
            'canRunBulkGeneration' => $canRunBulkGeneration,
            'defaultSeoKeywords' => trim((string)($configuration['seoKeywords'] ?? '')),
            'defaultNegativeKeywords' => trim((string)($configuration['negativeKeywords'] ?? '')),
            'checkedOverwriteExisting' => $this->isEnabled($configuration['overwriteExistingAltText'] ?? false) ? ' checked="checked"' : '',
            'shortAltTextLength' => max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
            'alreadyProcessedCount' => $this->bulkGenerationService->countSuccessfullyProcessed(),
            'bulkPreviewAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_bulk_preview'),
            'bulkProcessAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_bulk_process'),
            'dashboardUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator'),
            'renameUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename'),
            'historyUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history'),
            'settingsUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
        ]);

        return $moduleTemplate->renderResponse('Module/BulkGenerate');
    }

    /**
     * @return array<string, mixed>
     */
    private function getExtensionConfiguration(): array
    {
        return $this->configurationService->get();
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, string>
     */
    private function buildConfigurationSummary(LanguageService $languageService, array $configuration): array
    {
        $allowedExtensions = trim((string)($configuration['allowedImageExtensions'] ?? ''));

        return [
            'apiKeyState' => trim((string)($configuration['apiKey'] ?? '')) !== ''
                ? $languageService->sL(self::LLL . 'dashboard.defaults.configured')
                : $languageService->sL(self::LLL . 'dashboard.defaults.missing'),
            'writingStyle' => trim((string)($configuration['writingStyle'] ?? 'default')),
            'allowedImageExtensions' => $allowedExtensions !== '' ? $allowedExtensions : $languageService->sL(self::LLL . 'dashboard.defaults.allExtensions'),
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $creditSummary
     * @param array<string, mixed> $imageScanSummary
     * @return array<int, array<string, string>>
     */
    private function buildOverviewCards(
        LanguageService $languageService,
        array $configuration,
        array $creditSummary,
        array $imageScanSummary,
    ): array
    {
        $apiKeyConfigured = trim((string)($configuration['apiKey'] ?? '')) !== '';
        $enabled = $this->isEnabled($configuration['enabled'] ?? true);
        $autoGenerateOnUpload = $this->isEnabled($configuration['autoGenerateOnUpload'] ?? true);

        return [
            [
                'label' => $languageService->sL(self::LLL . 'dashboard.card.connection.label'),
                'value' => $apiKeyConfigured
                    ? $languageService->sL(self::LLL . 'dashboard.card.connection.ready')
                    : $languageService->sL(self::LLL . 'dashboard.card.connection.needsApiKey'),
                'state' => $apiKeyConfigured ? 'success' : 'warning',
                'detail' => $apiKeyConfigured
                    ? $languageService->sL(self::LLL . 'dashboard.card.connection.detail.ready')
                    : $languageService->sL(self::LLL . 'dashboard.card.connection.detail.needsApiKey'),
            ],
            [
                'label' => $languageService->sL(self::LLL . 'dashboard.card.extension.label'),
                'value' => $enabled
                    ? $languageService->sL(self::LLL . 'dashboard.card.extension.enabled')
                    : $languageService->sL(self::LLL . 'dashboard.card.extension.disabled'),
                'state' => $enabled ? 'success' : 'muted',
                'detail' => $enabled
                    ? $languageService->sL(self::LLL . 'dashboard.card.extension.detail.enabled')
                    : $languageService->sL(self::LLL . 'dashboard.card.extension.detail.disabled'),
            ],
            [
                'label' => $languageService->sL(self::LLL . 'dashboard.card.uploadAutomation.label'),
                'value' => $autoGenerateOnUpload
                    ? $languageService->sL(self::LLL . 'dashboard.card.extension.enabled')
                    : $languageService->sL(self::LLL . 'dashboard.card.extension.disabled'),
                'state' => $autoGenerateOnUpload ? 'success' : 'muted',
                'detail' => $autoGenerateOnUpload
                    ? $languageService->sL(self::LLL . 'dashboard.card.uploadAutomation.detail.enabled')
                    : $languageService->sL(self::LLL . 'dashboard.card.uploadAutomation.detail.disabled'),
            ],
            [
                'label' => $languageService->sL(self::LLL . 'dashboard.card.credits.label'),
                'value' => (string)$creditSummary['cardValue'],
                'state' => (string)$creditSummary['state'],
                'detail' => (string)$creditSummary['cardDetail'],
            ],
            [
                'label' => $languageService->sL(self::LLL . 'dashboard.card.imagesMissingAlt.label'),
                'value' => (string)$imageScanSummary['missingAltText'],
                'state' => (string)$imageScanSummary['state'],
                'detail' => (string)$imageScanSummary['cardDetail'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildQuickLinks(LanguageService $languageService): array
    {
        return [
            [
                'title' => $languageService->sL(self::LLL . 'module.bulk.title'),
                'description' => $languageService->sL(self::LLL . 'dashboard.quickLinks.bulk.description'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_bulk'),
            ],
            [
                'title' => $languageService->sL(self::LLL . 'module.rename.title'),
                'description' => $languageService->sL(self::LLL . 'module.rename.description'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename'),
            ],
            [
                'title' => $languageService->sL(self::LLL . 'module.history.title'),
                'description' => $languageService->sL(self::LLL . 'dashboard.quickLinks.history.description'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history'),
            ],
            [
                'title' => $languageService->sL(self::LLL . 'module.settings.title'),
                'description' => $languageService->sL(self::LLL . 'dashboard.quickLinks.settings.description'),
                'url' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function buildImageScanSummary(LanguageService $languageService, array $configuration): array
    {
        try {
            $scanResult = $this->imageScannerService->scan(
                allowedImageExtensions: trim((string)($configuration['allowedImageExtensions'] ?? '')),
                limit: 5,
                shortAltTextLength: max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('AutoAlt.ai dashboard could not scan images for missing alt text.', ['exception' => $exception]);

            return [
                'totalImages' => '-',
                'missingAltText' => $languageService->sL(self::LLL . 'dashboard.imageScan.unavailable'),
                'withAltText' => '-',
                'shortAltText' => '-',
                'items' => [],
                'hasItems' => false,
                'state' => 'warning',
                'cardDetail' => $languageService->sL(self::LLL . 'dashboard.imageScan.failed'),
            ];
        }

        return $this->formatImageScanSummary($languageService, $scanResult);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatImageScanSummary(LanguageService $languageService, ImageScanResult $scanResult): array
    {
        $state = $scanResult->missingAltText > 0 ? 'warning' : 'success';

        return [
            'totalImages' => $this->formatNumber($scanResult->totalImages),
            'missingAltText' => $this->formatNumber($scanResult->missingAltText),
            'withAltText' => $this->formatNumber($scanResult->withAltText),
            'shortAltText' => $this->formatNumber($scanResult->shortAltText),
            'items' => $scanResult->items,
            'hasItems' => $scanResult->items !== [],
            'state' => $state,
            'cardDetail' => $scanResult->missingAltText > 0
                ? $languageService->sL(self::LLL . 'dashboard.imageScan.detail.hasMissing')
                : $languageService->sL(self::LLL . 'dashboard.imageScan.detail.none'),
        ];
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
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

    private function formatNumber(int $value): string
    {
        return number_format($value);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
