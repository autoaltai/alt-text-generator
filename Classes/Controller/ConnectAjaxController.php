<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Exception\AutoAltApiException;
use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\CreditSummaryService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use AutoAltAi\AltTextGenerator\Service\PluginDataSyncService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backs the "Welcome to AutoAlt.ai" connect panel on the Settings page: pasting
 * an existing API key, the email/OTP login-or-register flow, and clearing the
 * key - all persisted immediately via ConfigurationService, independently of
 * the rest of the settings form's full-page save.
 */
#[AsController]
final readonly class ConnectAjaxController
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private AutoAltApiService $autoAltApiService,
        private CreditSummaryService $creditSummaryService,
        private ConfigurationService $configurationService,
        private PermissionsService $permissionsService,
        private LanguageServiceFactory $languageServiceFactory,
        private ?PluginDataSyncService $pluginDataSyncService = null,
    ) {}

    public function connectApiKeyAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        if (!$this->permissionsService->canManageSettings($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'settings.noPermission'),
            ], 403);
        }

        $apiKey = trim((string)($this->parsedBody($request)['apiKey'] ?? ''));
        if ($apiKey === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.emptyKey'),
            ], 400);
        }

        $websiteDomain = $this->resolveWebsiteDomain($request);

        try {
            $isValid = $this->autoAltApiService->verifyApiKey(apiKey: $apiKey, websiteDomain: $websiteDomain);
        } catch (AutoAltApiException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        if (!$isValid) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.invalidKey'),
            ], 400);
        }

        $this->persistApiKey($apiKey);
        $this->synchronizePluginData($websiteDomain);

        return new JsonResponse([
            'success' => true,
            'message' => $languageService->sL(self::LLL . 'connect.apiKeyConnected'),
            'credit' => $this->buildCreditPayload($languageService, $websiteDomain),
        ]);
    }

    public function clearApiKeyAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        if (!$this->permissionsService->canManageSettings($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'settings.noPermission'),
            ], 403);
        }

        $this->persistApiKey('');

        return new JsonResponse([
            'success' => true,
            'message' => $languageService->sL(self::LLL . 'connect.apiKeyCleared'),
        ]);
    }

    public function sendOtpAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        if (!$this->permissionsService->canManageSettings($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'settings.noPermission'),
            ], 403);
        }

        $email = trim((string)($this->parsedBody($request)['email'] ?? ''));
        if (!$this->isValidEmail($email)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.invalidEmail'),
            ], 400);
        }

        try {
            $this->autoAltApiService->sendOtp($email, $this->resolveWebsiteDomain($request));
        } catch (AutoAltApiException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $languageService->sL(self::LLL . 'connect.otpSent'),
        ]);
    }

    public function verifyOtpAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        if (!$this->permissionsService->canManageSettings($this->getBackendUser())) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'settings.noPermission'),
            ], 403);
        }

        $body = $this->parsedBody($request);
        $email = trim((string)($body['email'] ?? ''));
        $otp = trim((string)($body['otp'] ?? ''));

        if ($email === '' || $otp === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.otpRequired'),
            ], 400);
        }
        if (!preg_match('/^\d{6}$/', $otp)) {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.otpFormat'),
            ], 400);
        }

        $websiteDomain = $this->resolveWebsiteDomain($request);

        try {
            $response = $this->autoAltApiService->verifyOtp($email, $otp, $websiteDomain);
        } catch (AutoAltApiException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        $apiKey = trim((string)($response['api_key'] ?? ''));
        if ($apiKey === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $languageService->sL(self::LLL . 'connect.error.otpVerifyFailed'),
            ], 400);
        }

        $this->persistApiKey($apiKey);
        $this->synchronizePluginData($websiteDomain);

        return new JsonResponse([
            'success' => true,
            'message' => $languageService->sL(self::LLL . 'connect.otpVerified'),
            'credit' => $this->buildCreditPayload($languageService, $websiteDomain),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function persistApiKey(string $apiKey): void
    {
        $configuration = $this->configurationService->getStored();
        $configuration['apiKey'] = $apiKey;
        $this->configurationService->set($configuration);
    }

    /**
     * @return array<string, mixed>
     */
    private function getExtensionConfiguration(): array
    {
        return $this->configurationService->get();
    }

    /**
     * Reads the freshly-persisted key back via CreditSummaryService, so the
     * response reflects the account actually now connected.
     *
     * @return array<string, mixed>|null
     */
    private function buildCreditPayload(LanguageService $languageService, string $websiteDomain): ?array
    {
        try {
            return $this->creditSummaryService->build($languageService, $this->getExtensionConfiguration(), $websiteDomain);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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

    private function synchronizePluginData(string $websiteDomain): void
    {
        ($this->pluginDataSyncService ?? GeneralUtility::makeInstance(PluginDataSyncService::class))
            ->synchronize($websiteDomain);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
