<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\CreditSummaryService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use AutoAltAi\AltTextGenerator\Service\KeywordValidationService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
final readonly class SettingsController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';
    private const CUSTOM_PROMPT_MAX_LENGTH = 500;

    /** @var array<string, string> */
    private const WRITING_STYLE_OPTIONS = [
        '' => 'default',
        'Neutral – Clear, balanced, and objective' => 'neutral',
        'Friendly – Warm, approachable, and informal' => 'friendly',
        'Professional – Polished and business-appropriate' => 'professional',
        'Casual – Laid-back and conversational' => 'casual',
        'Witty – Clever, playful, with light humor' => 'witty',
        'Confident – Assertive and bold' => 'confident',
        'Empathetic – Sensitive and understanding' => 'empathetic',
        'Inspiring – Motivational and uplifting' => 'inspiring',
        'Technical – Precise, jargon-friendly, and expert-level' => 'technical',
        'Minimalist – Simple, clean, and concise' => 'minimalist',
        'Luxury – Elegant and premium-sounding' => 'luxury',
        'Youthful – Trendy, fresh, and informal' => 'youthful',
        'Quirky – Fun, offbeat, and creative' => 'quirky',
        'Persuasive – Convincing and sales-driven' => 'persuasive',
        'Playful – Light, cheerful, and energetic' => 'playful',
        'Storytelling – Narrative and engaging' => 'storytelling',
        'Elegant – Refined and graceful' => 'elegant',
        'Bold – Strong, impactful, and direct' => 'bold',
        'Humorous – Funny and entertaining' => 'humorous',
        'Descriptive – Rich in detail and imagery' => 'descriptive',
        'Brand-centric – Tailored to your brand voice' => 'brandCentric',
    ];

    /** @var array<string, string> */
    private const LEGACY_WRITING_STYLE_VALUES = [
        'default' => '',
        'neutral' => 'Neutral – Clear, balanced, and objective',
        'friendly' => 'Friendly – Warm, approachable, and informal',
        'professional' => 'Professional – Polished and business-appropriate',
        'casual' => 'Casual – Laid-back and conversational',
        'witty' => 'Witty – Clever, playful, with light humor',
        'confident' => 'Confident – Assertive and bold',
        'empathetic' => 'Empathetic – Sensitive and understanding',
        'inspiring' => 'Inspiring – Motivational and uplifting',
        'technical' => 'Technical – Precise, jargon-friendly, and expert-level',
        'minimalist' => 'Minimalist – Simple, clean, and concise',
        'luxury' => 'Luxury – Elegant and premium-sounding',
        'youthful' => 'Youthful – Trendy, fresh, and informal',
        'quirky' => 'Quirky – Fun, offbeat, and creative',
        'sales' => 'Persuasive – Convincing and sales-driven',
        'informative' => 'Descriptive – Rich in detail and imagery',
        'formal' => 'Professional – Polished and business-appropriate',
        'authoritative' => 'Confident – Assertive and bold',
        'seo' => '',
    ];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private ConfigurationService $configurationService,
        private UriBuilder $uriBuilder,
        // Retained in this position for deployments with a warmed TYPO3 DI
        // container from the previous controller signature. The connection
        // card now owns API-key validation, so this controller no longer uses
        // the service directly.
        AutoAltApiService $legacyAutoAltApiService,
        private CreditSummaryService $creditSummaryService,
        private ErrorLogService $errorLogService,
        private KeywordValidationService $keywordValidationService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');

        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $storedConfiguration = $this->configurationService->getStored();
        $currentConfiguration = $storedConfiguration;
        $validationResult = null;
        $canManageSettings = $this->permissionsService->canManageSettings($this->getBackendUser());

        if ($request->getMethod() === 'POST' && $canManageSettings && $this->getSubmittedAction($request) === 'clearErrorLog') {
            $this->errorLogService->clear();

            return new RedirectResponse(
                (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings', ['logsCleared' => 1]),
                303
            );
        }

        if ($request->getMethod() === 'POST' && $canManageSettings) {
            $submittedConfiguration = $this->buildConfigurationFromRequest($request, $storedConfiguration);
            $keywordValidationError = $this->keywordValidationService->validate(
                (string)$submittedConfiguration['seoKeywords'],
                (string)$submittedConfiguration['negativeKeywords']
            );
            if ($keywordValidationError !== null) {
                $validationResult = [
                    'state' => 'warning',
                    'message' => vsprintf(
                        $languageService->sL(self::LLL . $keywordValidationError['key']),
                        $keywordValidationError['arguments']
                    ),
                ];
                $currentConfiguration = $submittedConfiguration;
            } elseif (mb_strlen((string)$submittedConfiguration['customPrompt']) > self::CUSTOM_PROMPT_MAX_LENGTH) {
                $validationResult = [
                    'state' => 'warning',
                    'message' => sprintf(
                        $languageService->sL(self::LLL . 'settings.validation.customPromptTooLong'),
                        self::CUSTOM_PROMPT_MAX_LENGTH
                    ),
                ];
                $currentConfiguration = $submittedConfiguration;
            } else {
                $this->configurationService->set($submittedConfiguration);

                return new RedirectResponse(
                    (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings', ['saved' => 1]),
                    303
                );
            }
        }

        if ($request->getMethod() === 'POST' && !$canManageSettings) {
            $validationResult = [
                'state' => 'warning',
                'message' => $languageService->sL(self::LLL . 'settings.noPermission'),
            ];
        }

        $settings = $this->normalizeConfiguration($currentConfiguration);
        $settingsForTemplate = $settings;
        unset($settingsForTemplate['apiKey']);
        $connectionState = $this->buildConnectionState($languageService, $settings, $validationResult);
        $writingStyleOptions = $this->buildWritingStyleOptions($languageService, (string)$settings['writingStyle']);
        $recentErrorLogs = $this->buildRecentErrorLogs();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($languageService->sL(self::LLL . 'module.title'), $languageService->sL(self::LLL . 'settings.title'));
        $moduleTemplate->assignMultiple([
            'actionUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_settings'),
            'backUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator'),
            'bulkUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_bulk'),
            'renameUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_rename'),
            'historyUrl' => (string)$this->uriBuilder->buildUriFromRoute('media_autoalt_alt_text_generator_history'),
            'apiKeyPlaceholder' => (string)$settings['apiKey'] !== ''
                ? $languageService->sL(self::LLL . 'settings.apiKeyPlaceholder.configured')
                : $languageService->sL(self::LLL . 'settings.apiKeyPlaceholder.empty'),
            'hasApiKey' => trim((string)$settings['apiKey']) !== '',
            'connectApiKeyAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_connect_api_key'),
            'connectClearAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_connect_clear'),
            'connectSendOtpAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_connect_send_otp'),
            'connectVerifyOtpAjaxUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_alt_text_generator_connect_verify_otp'),
            'canSave' => $canManageSettings,
            'checked' => $this->buildCheckedAttributes($settings),
            'disabledAttribute' => $canManageSettings ? '' : ' disabled="disabled"',
            'saved' => (bool)($request->getQueryParams()['saved'] ?? false),
            'logsCleared' => (bool)($request->getQueryParams()['logsCleared'] ?? false),
            'settings' => $settingsForTemplate,
            'validationResult' => $validationResult,
            'customPromptMaxLength' => self::CUSTOM_PROMPT_MAX_LENGTH,
            'connectionState' => $connectionState,
            'settingsSummary' => $this->buildSettingsSummary($languageService, $settings, $connectionState, $writingStyleOptions),
            'creditSummary' => $this->creditSummaryService->build($languageService, $settings, $this->resolveWebsiteDomain($request)),
            'writingStyleOptions' => $writingStyleOptions,
            'shortAltTextLengthOptions' => $this->buildShortAltTextLengthOptions($languageService, (int)$settings['shortAltTextLength']),
            'recentErrorLogs' => $recentErrorLogs,
            'recentErrorLogCount' => count($recentErrorLogs),
        ]);

        return $moduleTemplate->renderResponse('Settings/Index');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildRecentErrorLogs(): array
    {
        $entries = [];
        foreach ($this->errorLogService->getRecent(20) as $row) {
            $timestamp = (int)($row['crdate'] ?? 0);
            $level = strtolower((string)($row['level'] ?? 'error'));
            if (!in_array($level, ['error', 'warning', 'info'], true)) {
                $level = 'error';
            }
            $entries[] = [
                'level' => $level,
                'message' => (string)($row['message'] ?? ''),
                'date' => date('d M Y', $timestamp),
                'time' => date('H:i:s', $timestamp),
                'datetime' => date(DATE_ATOM, $timestamp),
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array{state: string, message: string}|null $validationResult
     * @return array{state: string, label: string}
     */
    private function buildConnectionState(LanguageService $languageService, array $settings, ?array $validationResult): array
    {
        if (trim((string)$settings['apiKey']) === '') {
            return [
                'state' => 'warning',
                'label' => $languageService->sL(self::LLL . 'settings.connectionState.needsApiKey'),
            ];
        }

        if ($validationResult !== null && $validationResult['state'] === 'danger') {
            return [
                'state' => 'danger',
                'label' => $languageService->sL(self::LLL . 'settings.connectionState.invalid'),
            ];
        }

        return [
            'state' => 'success',
            'label' => $languageService->sL(self::LLL . 'settings.connectionState.connected'),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array{state: string, label: string} $connectionState
     * @param array<int, array<string, string>> $writingStyleOptions
     * @return array<string, string>
     */
    private function buildSettingsSummary(
        LanguageService $languageService,
        array $settings,
        array $connectionState,
        array $writingStyleOptions,
    ): array {
        $writingStyle = (string)($settings['writingStyle'] ?? 'default');
        foreach ($writingStyleOptions as $option) {
            if (($option['value'] ?? '') === $writingStyle) {
                $writingStyle = (string)($option['label'] ?? $writingStyle);
                break;
            }
        }

        $formats = str_replace(',', ', ', trim((string)($settings['allowedImageExtensions'] ?? '')));

        return [
            'connection' => $connectionState['label'],
            'connectionState' => $connectionState['state'],
            'writingStyle' => $writingStyle,
            'formats' => $formats !== '' ? $formats : $languageService->sL(self::LLL . 'settings.summary.allFormats'),
            'notifications' => $languageService->sL(
                self::LLL . ($this->isEnabled($settings['notifyOnBulkComplete'] ?? false)
                    ? 'dashboard.card.extension.enabled'
                    : 'dashboard.card.extension.disabled')
            ),
        ];
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

    private function getSubmittedAction(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return 'save';
        }

        return (string)($body['settingsAction'] ?? 'save');
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function normalizeConfiguration(array $configuration): array
    {
        $settings = array_replace($this->configurationService->getDefaultConfiguration(), $configuration);
        $settings['writingStyle'] = $this->normalizeWritingStyle($settings['writingStyle'] ?? '');

        foreach (['enabled', 'autoGenerateOnUpload', 'overwriteExistingAltText', 'usePublicImageUrls', 'logApiErrors', 'notifyOnBulkComplete', 'ignoreMissingImages', 'generateTitle', 'generateDescription'] as $key) {
            $settings[$key] = $this->isEnabled($settings[$key] ?? false);
        }

        foreach (['altTextMinLength', 'altTextMaxLength', 'requestTimeout', 'shortAltTextLength'] as $key) {
            $settings[$key] = max(0, (int)($settings[$key] ?? 0));
        }
        unset($settings['apiBaseUrl'], $settings['batchSize'], $settings['defaultLanguage']);

        return $settings;
    }

    /**
     * @param array<string, mixed> $currentConfiguration
     * @return array<string, mixed>
     */
    private function buildConfigurationFromRequest(ServerRequestInterface $request, array $currentConfiguration): array
    {
        $body = $request->getParsedBody();
        $submitted = is_array($body) && is_array($body['settings'] ?? null) ? $body['settings'] : [];
        $settings = $this->normalizeConfiguration($currentConfiguration);
        $settings['enabled'] = $this->isEnabled($submitted['enabled'] ?? false) ? '1' : '0';
        $settings['writingStyle'] = $this->normalizeWritingStyle($submitted['writingStyle'] ?? $settings['writingStyle']);
        $settings['altTextMinLength'] = (string)$this->clampInteger($submitted['altTextMinLength'] ?? $settings['altTextMinLength'], 0, 250);
        $settings['altTextMaxLength'] = (string)$this->clampInteger($submitted['altTextMaxLength'] ?? $settings['altTextMaxLength'], 0, 300);
        $settings['altTextPrefix'] = mb_substr($this->sanitizeString($submitted['altTextPrefix'] ?? $settings['altTextPrefix']), 0, 60);
        $settings['altTextSuffix'] = mb_substr($this->sanitizeString($submitted['altTextSuffix'] ?? $settings['altTextSuffix']), 0, 60);
        $settings['generateTitle'] = $this->isEnabled($submitted['generateTitle'] ?? false) ? '1' : '0';
        $settings['generateDescription'] = $this->isEnabled($submitted['generateDescription'] ?? false) ? '1' : '0';
        $settings['seoKeywords'] = $this->sanitizeString($submitted['seoKeywords'] ?? $settings['seoKeywords']);
        $settings['negativeKeywords'] = $this->sanitizeString($submitted['negativeKeywords'] ?? $settings['negativeKeywords']);
        $settings['customPrompt'] = $this->sanitizeTextarea($submitted['customPrompt'] ?? $settings['customPrompt']);
        $settings['autoGenerateOnUpload'] = $this->isEnabled($submitted['autoGenerateOnUpload'] ?? false) ? '1' : '0';
        $settings['overwriteExistingAltText'] = $this->isEnabled($submitted['overwriteExistingAltText'] ?? false) ? '1' : '0';
        $settings['usePublicImageUrls'] = '0';
        $settings['allowedImageExtensions'] = $this->sanitizeExtensions($submitted['allowedImageExtensions'] ?? $settings['allowedImageExtensions']);
        $settings['requestTimeout'] = '30';
        $settings['shortAltTextLength'] = (string)$this->clampInteger($submitted['shortAltTextLength'] ?? $settings['shortAltTextLength'], 20, 50);
        $settings['logApiErrors'] = '1';
        $settings['notifyOnBulkComplete'] = $this->isEnabled($submitted['notifyOnBulkComplete'] ?? false) ? '1' : '0';
        $settings['notificationEmail'] = $this->sanitizeString($submitted['notificationEmail'] ?? $settings['notificationEmail']);
        $settings['ignoreMissingImages'] = '1';
        unset($settings['defaultLanguage']);

        return $settings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWritingStyleOptions(LanguageService $languageService, string $selectedValue): array
    {
        $options = [];
        foreach (self::WRITING_STYLE_OPTIONS as $value => $labelKey) {
            $options[$value] = $languageService->sL(self::LLL . 'settings.writingStyle.' . $labelKey);
        }

        return $this->buildOptions($options, $this->normalizeWritingStyle($selectedValue));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildShortAltTextLengthOptions(LanguageService $languageService, int $selectedValue): array
    {
        $options = [];
        foreach (['20', '30', '40', '50'] as $value) {
            $options[$value] = $languageService->sL(self::LLL . 'settings.shortAltTextLength.' . $value);
        }

        return $this->buildOptions($options, (string)$selectedValue);
    }

    /**
     * @param array<string, string> $options
     * @return array<int, array<string, mixed>>
     */
    private function buildOptions(array $options, string $selectedValue): array
    {
        $items = [];
        foreach ($options as $value => $label) {
            $value = (string)$value;
            $items[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $value === $selectedValue,
                'selectedAttribute' => $value === $selectedValue ? ' selected="selected"' : '',
            ];
        }

        return $items;
    }

    private function sanitizeString(mixed $value): string
    {
        return trim(strip_tags((string)$value));
    }

    private function normalizeWritingStyle(mixed $value): string
    {
        $writingStyle = $this->sanitizeString($value);
        if (array_key_exists($writingStyle, self::WRITING_STYLE_OPTIONS)) {
            return $writingStyle;
        }

        return self::LEGACY_WRITING_STYLE_VALUES[strtolower($writingStyle)] ?? '';
    }

    private function sanitizeTextarea(mixed $value): string
    {
        return trim(strip_tags((string)$value));
    }

    private function sanitizeExtensions(mixed $value): string
    {
        $extensions = preg_split('/[,\\s]+/', strtolower((string)$value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $extensions = array_map(static fn(string $extension): string => preg_replace('/[^a-z0-9]/', '', $extension) ?? '', $extensions);
        $extensions = array_filter(array_unique($extensions));

        return implode(',', $extensions);
    }

    private function clampInteger(mixed $value, int $min, int $max): int
    {
        return min($max, max($min, (int)$value));
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private function buildCheckedAttributes(array $settings): array
    {
        $attributes = [];
        foreach (['enabled', 'autoGenerateOnUpload', 'overwriteExistingAltText', 'usePublicImageUrls', 'logApiErrors', 'notifyOnBulkComplete', 'ignoreMissingImages', 'generateTitle', 'generateDescription'] as $key) {
            $attributes[$key] = $this->isEnabled($settings[$key] ?? false) ? ' checked="checked"' : '';
        }

        return $attributes;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
